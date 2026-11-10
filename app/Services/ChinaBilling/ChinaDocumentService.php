<?php

namespace App\Services\ChinaBilling;

use App\Exceptions\ChinaBillingException;
use App\Models\ChinaBilling\ChinaDocument;
use App\Models\ChinaBilling\ChinaOperation;
use App\Traits\FileTrait;
use App\Traits\UsesObjectStorage;
use Dompdf\Dompdf;
use Dompdf\Options;
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
     * @param  array{id: int|null, name: string, organization_ids: array<int, int>}  $actor
     */
    public function issueInvoice(int $operationId, array $actor): ChinaDocument
    {
        $operation = ChinaOperation::query()->whereIn('organization_id', $actor['organization_ids'])->find($operationId);
        if (!$operation) {
            throw new ChinaBillingException('La operación no existe o no tienes acceso.', 404);
        }

        $snapshot = [
            'issued_on' => now()->toDateString(),
            'bank' => $this->bankAccount->active(),
            'operation' => $this->reader->operationDetail($operationId),
        ];

        return $this->store(
            ChinaDocument::TYPE_INVOICE,
            (int) $operation->organization_id,
            $operationId,
            $snapshot,
            'factura-'.$operation->code,
            'china-billing.invoice',
            $actor
        );
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
            'total' => $document->type === ChinaDocument::TYPE_INVOICE
                ? ($document->snapshot['operation']['total'] ?? null)
                : ($document->snapshot['balance']['total'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array{id: int|null, name: string, organization_ids: array<int, int>}  $actor
     */
    private function store(string $type, int $organizationId, ?int $operationId, array $snapshot, string $baseName, string $view, array $actor): ChinaDocument
    {
        $binary = $this->render($view, $snapshot);
        $fileName = $baseName.'-'.now()->format('Ymd-His').'.pdf';
        $path = $this->storagePutContentsForCdn('china-billing/documentos/'.$type.'/'.$organizationId.'/'.$fileName, $binary);

        return ChinaDocument::query()->create([
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
     * @param  array<string, mixed>  $snapshot
     */
    private function render(string $view, array $snapshot): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view($view, $snapshot)->render());
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
