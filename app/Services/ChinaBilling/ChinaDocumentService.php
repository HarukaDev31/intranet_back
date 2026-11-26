<?php

namespace App\Services\ChinaBilling;

use App\Exceptions\ChinaBillingException;
use App\Models\ChinaBilling\ChinaDocument;
use App\Models\ChinaBilling\ChinaOperation;
use App\Traits\FileTrait;
use App\Traits\UsesObjectStorage;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Facturas y balances de pago: el PDF siempre se genera en el backend y cada emisión queda en el historial.
 * El snapshot guarda los datos calculados en ese momento; el PDF se dibuja solo con él.
 */
class ChinaDocumentService
{
    use FileTrait;
    use UsesObjectStorage;

    public function __construct(
        private readonly ChinaBillingReader $reader,
        private readonly ChinaBankAccountService $bankAccount,
    ) {
    }

    /**
     * Emite una versión nueva de la factura y la deja vigente (la vigente anterior pasa a reemplazada).
     * Desde la segunda versión el motivo es obligatorio. Con la operación en caja la vigente queda cerrada:
     * hay que anularla antes de emitir otra.
     *
     * @param  array{id: int|null, name: string, organization_ids: array<int, int>}  $actor
     */
    public function issueInvoice(int $operationId, array $actor, ?string $reason = null): ChinaDocument
    {
        $operation = $this->operation($operationId, $actor);
        $detail = $this->reader->operationDetail($operationId);
        $current = $this->currentInvoice($operationId);
        if ($current && $detail['status'] === ChinaBillingCalculator::STATUS_IN_CASH) {
            throw new ChinaBillingException('La operación está en caja: anula la factura vigente con motivo y genera otra.', 422);
        }

        $previous = (int) ChinaDocument::query()->where('type', ChinaDocument::TYPE_INVOICE)->where('operation_id', $operationId)->max('version');
        $reason = trim((string) $reason);
        if ($previous > 0 && $reason === '') {
            throw new ChinaBillingException('Escribe el motivo de la nueva versión.', 422);
        }
        $version = $previous + 1;

        $snapshot = [
            'issued_on' => now()->toDateString(),
            'number' => $operation->code.'-v'.$version,
            'bank' => $this->bankAccount->active(),
            'operation' => $detail,
            'contact' => $this->contact((int) $operation->organization_id),
        ];

        return $this->store(
            ChinaDocument::TYPE_INVOICE,
            (int) $operation->organization_id,
            $operationId,
            $snapshot,
            'factura-'.$operation->code.'-v'.$version,
            'china-billing.invoice',
            $actor,
            ['version' => $version, 'status' => ChinaDocument::STATUS_CURRENT, 'reason' => mb_substr($reason !== '' ? $reason : 'Emisión inicial', 0, 255)]
        );
    }

    /**
     * El socio no versiona: descarga la vigente o, si aún no hay, emite la primera.
     *
     * @param  array{id: int|null, name: string, organization_ids: array<int, int>}  $actor
     */
    public function currentOrIssueInvoice(int $operationId, array $actor): ChinaDocument
    {
        $this->operation($operationId, $actor);

        $current = $this->currentInvoice($operationId);
        if ($current) {
            return $current;
        }
        if (ChinaDocument::query()->where('type', ChinaDocument::TYPE_INVOICE)->where('operation_id', $operationId)->exists()) {
            throw new ChinaBillingException('Esta operación aún no tiene una factura vigente.', 422);
        }

        return $this->issueInvoice($operationId, $actor);
    }

    /**
     * Marca una versión anterior como vigente; la vigente actual pasa a reemplazada.
     *
     * @param  array{id: int|null, name: string, organization_ids: array<int, int>}  $actor
     */
    public function markCurrent(int $documentId, array $actor, ?string $reason): ChinaDocument
    {
        $document = $this->invoice($documentId, $actor);
        if ($document->status === ChinaDocument::STATUS_CURRENT) {
            return $document;
        }
        if ($document->status === ChinaDocument::STATUS_ANNULLED) {
            throw new ChinaBillingException('Una factura anulada no puede volver a ser vigente.', 422);
        }
        if ($this->reader->operationDetail((int) $document->operation_id)['status'] === ChinaBillingCalculator::STATUS_IN_CASH
            && $this->currentInvoice((int) $document->operation_id)) {
            throw new ChinaBillingException('La operación está en caja: la factura vigente queda cerrada.', 422);
        }

        return DB::transaction(function () use ($document, $actor, $reason) {
            $this->replaceCurrent((int) $document->operation_id, $actor);
            $this->changeStatus($document, ChinaDocument::STATUS_CURRENT, $reason, $actor);

            return $document;
        });
    }

    /**
     * Anula la factura vigente con motivo. La operación queda sin vigente hasta generar otra o marcar una anterior.
     *
     * @param  array{id: int|null, name: string, organization_ids: array<int, int>}  $actor
     */
    public function annul(int $documentId, array $actor, ?string $reason): ChinaDocument
    {
        $document = $this->invoice($documentId, $actor);
        if (trim((string) $reason) === '') {
            throw new ChinaBillingException('Escribe el motivo de la anulación.', 422);
        }
        if ($document->status !== ChinaDocument::STATUS_CURRENT) {
            throw new ChinaBillingException('Solo se anula la factura vigente.', 422);
        }
        $this->changeStatus($document, ChinaDocument::STATUS_ANNULLED, $reason, $actor);

        return $document;
    }

    /**
     * @param  array{id: int|null, name: string, organization_ids: array<int, int>}  $actor
     */
    public function issueBalance(int $organizationId, array $actor): ChinaDocument
    {
        if (!in_array($organizationId, $actor['organization_ids'], true)) {
            throw new ChinaBillingException('No tienes acceso a ese cliente.', 403);
        }

        $snapshot = [
            'issued_on' => now()->toDateString(),
            'bank' => $this->bankAccount->active(),
            'balance' => $this->reader->balance($organizationId),
            'contact' => $this->contact($organizationId),
        ];

        return $this->store(
            ChinaDocument::TYPE_BALANCE,
            $organizationId,
            null,
            $snapshot,
            'balance-'.Str::slug($snapshot['balance']['organization']['name']),
            'china-billing.balance',
            $actor
        );
    }

    /**
     * Contacto del cliente para el documento (contacto y WhatsApp de la organización).
     */
    private function contact(int $organizationId): string
    {
        $org = \Illuminate\Support\Facades\DB::table('organizacion')->where('ID_Organizacion', $organizationId)->first(['contacto', 'whatsapp']);

        return trim(implode(' · ', array_filter([trim((string) ($org->contacto ?? '')), trim((string) ($org->whatsapp ?? ''))])));
    }

    /**
     * Historial de emisiones, de la más reciente a la más antigua.
     *
     * @param  array{id: int|null, name: string, organization_ids: array<int, int>}  $actor
     * @return array<int, array<string, mixed>>
     */
    public function history(string $type, ?int $operationId, ?int $organizationId, array $actor): array
    {
        return ChinaDocument::query()
            ->where('type', $type)
            ->whereIn('organization_id', $actor['organization_ids'])
            ->when($operationId, fn ($q) => $q->where('operation_id', $operationId))
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ChinaDocument $document) => $this->present($document))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(ChinaDocument $document): array
    {
        return [
            'id' => (int) $document->id,
            'type' => $document->type,
            'issued_on' => $document->issued_on->toDateString(),
            'created_at' => $document->created_at?->toIso8601String(),
            'file_name' => $document->file_name,
            'url' => $this->cdnStorageUrl($document->path),
            'generated_by' => $document->generated_by_name,
            'version' => $document->version,
            'number' => $document->type === ChinaDocument::TYPE_INVOICE ? $document->number() : null,
            'status' => $document->status,
            'reason' => $document->reason,
            'status_reason' => $document->status_reason,
            'status_changed_at' => $document->status_changed_at?->toIso8601String(),
            'status_changed_by' => $document->status_changed_by_name,
            'total' => $document->type === ChinaDocument::TYPE_INVOICE
                ? ($document->snapshot['operation']['total'] ?? null)
                : ($document->snapshot['balance']['total'] ?? null),
        ];
    }

    /**
     * @param  array{id: int|null, name: string, organization_ids: array<int, int>}  $actor
     */
    public function download(int $documentId, array $actor): \Symfony\Component\HttpFoundation\Response
    {
        $document = ChinaDocument::query()
            ->whereIn('organization_id', $actor['organization_ids'])
            ->find($documentId);
        if (!$document) {
            throw new ChinaBillingException('El documento no existe o no tienes acceso.', 404);
        }

        $fileName = $document->file_name ?: ('documento-'.$document->id.'.pdf');

        // Las versiones no vigentes salen con marca de agua (el PDF guardado es el de su emisión).
        $watermark = match ($document->status) {
            ChinaDocument::STATUS_REPLACED => 'NO VIGENTE',
            ChinaDocument::STATUS_ANNULLED => 'ANULADA',
            default => null,
        };
        if ($watermark !== null && $document->type === ChinaDocument::TYPE_INVOICE) {
            $binary = $this->render('china-billing.invoice', ['watermark' => $watermark, 'number' => $document->number()] + $document->snapshot);

            return response()->streamDownload(fn () => print($binary), $fileName, ['Content-Type' => 'application/pdf']);
        }

        $relativePath = $this->storageUploadPathFromDb($document->path);
        if (!$relativePath) {
            throw new ChinaBillingException('No se encontró el archivo del documento.', 404);
        }

        $stream = $this->objectStorage()->readStream($relativePath);
        if (!$stream) {
            throw new ChinaBillingException('No se pudo leer el archivo del documento.', 404);
        }

        return response()->streamDownload(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, $fileName, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array{id: int|null, name: string, organization_ids: array<int, int>}  $actor
     */
    private function store(string $type, int $organizationId, ?int $operationId, array $snapshot, string $baseName, string $view, array $actor, array $extra = []): ChinaDocument
    {
        $binary = $this->render($view, $snapshot);
        $fileName = $baseName.'-'.now()->format('Ymd-His').'.pdf';
        $path = $this->storagePutContentsForCdn('china-billing/documentos/'.$type.'/'.$organizationId.'/'.$fileName, $binary);

        return DB::transaction(function () use ($type, $organizationId, $operationId, $snapshot, $path, $fileName, $actor, $extra) {
            if ($operationId !== null && ($extra['status'] ?? null) === ChinaDocument::STATUS_CURRENT) {
                $this->replaceCurrent($operationId, $actor);
            }

            return $this->createDocument($type, $organizationId, $operationId, $snapshot, $path, $fileName, $actor, $extra);
        });
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array{id: int|null, name: string, organization_ids: array<int, int>}  $actor
     * @param  array<string, mixed>  $extra
     */
    private function createDocument(string $type, int $organizationId, ?int $operationId, array $snapshot, string $path, string $fileName, array $actor, array $extra): ChinaDocument
    {
        return ChinaDocument::query()->create([
            'version' => $extra['version'] ?? null,
            'status' => $extra['status'] ?? null,
            'reason' => $extra['reason'] ?? null,
            'type' => $type,
            'organization_id' => $organizationId,
            'operation_id' => $operationId,
            'issued_on' => $snapshot['issued_on'],
            'snapshot' => $snapshot,
            'path' => $path,
            'file_name' => $fileName,
            'generated_by' => $actor['id'],
            'generated_by_name' => $actor['name'],
        ]);
    }

    /**
     * @param  array{id: int|null, name: string, organization_ids: array<int, int>}  $actor
     */
    private function operation(int $operationId, array $actor): ChinaOperation
    {
        $operation = ChinaOperation::query()->whereIn('organization_id', $actor['organization_ids'])->find($operationId);
        if (!$operation) {
            throw new ChinaBillingException('La operación no existe o no tienes acceso.', 404);
        }

        return $operation;
    }

    /**
     * @param  array{id: int|null, name: string, organization_ids: array<int, int>}  $actor
     */
    private function invoice(int $documentId, array $actor): ChinaDocument
    {
        $document = ChinaDocument::query()->where('type', ChinaDocument::TYPE_INVOICE)
            ->whereIn('organization_id', $actor['organization_ids'])->find($documentId);
        if (!$document || !$document->operation_id) {
            throw new ChinaBillingException('La factura no existe o no tienes acceso.', 404);
        }

        return $document;
    }

    private function currentInvoice(int $operationId): ?ChinaDocument
    {
        return ChinaDocument::query()->where('type', ChinaDocument::TYPE_INVOICE)->where('operation_id', $operationId)
            ->where('status', ChinaDocument::STATUS_CURRENT)->orderByDesc('version')->first();
    }

    /**
     * @param  array{id: int|null, name: string, organization_ids: array<int, int>}  $actor
     */
    private function replaceCurrent(int $operationId, array $actor): void
    {
        ChinaDocument::query()->where('type', ChinaDocument::TYPE_INVOICE)->where('operation_id', $operationId)
            ->where('status', ChinaDocument::STATUS_CURRENT)
            // Al pasar a reemplazada se conserva el motivo con el que fue vigente.
            ->update([
                'status' => ChinaDocument::STATUS_REPLACED,
                'status_changed_at' => now(),
                'status_changed_by' => $actor['id'],
                'status_changed_by_name' => $actor['name'],
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  array{id: int|null, name: string, organization_ids: array<int, int>}  $actor
     */
    private function changeStatus(ChinaDocument $document, string $status, ?string $reason, array $actor): void
    {
        $reason = trim((string) $reason);
        $document->status = $status;
        $document->status_reason = $reason !== '' ? mb_substr($reason, 0, 255) : null;
        $document->status_changed_at = now();
        $document->status_changed_by = $actor['id'];
        $document->status_changed_by_name = $actor['name'];
        $document->save();
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function render(string $view, array $snapshot): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        // Noto Sans SC (versionada en storage/fonts) para la dirección en chino de la oficina.
        $options->set('fontDir', storage_path('fonts'));
        $options->set('fontCache', storage_path('fonts'));
        $options->set('chroot', [public_path(), storage_path('fonts')]);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view($view, $snapshot)->render());
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
