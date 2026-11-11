<?php

namespace App\Http\Controllers\ChinaBilling;

use App\Http\Controllers\Controller;
use App\Models\ChinaBilling\ChinaDocument;
use App\Services\ChinaBilling\ChinaBillingAuthorization;
use App\Services\ChinaBilling\ChinaDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Facturas y balances de pago en PDF (siempre generados por el backend) con su historial de emisiones.
 * Almacén China los emite de cualquier cliente que gestione; el socio, solo de su organización.
 */
class ChinaBillingDocumentController extends Controller
{
    use RespondsChinaBilling;

    public function __construct(
        private readonly ChinaBillingAuthorization $authorization,
        private readonly ChinaDocumentService $documents,
    ) {
    }

    public function invoiceHistory(Request $request, int $operationId): JsonResponse
    {
        return $this->responder(fn () => $this->history($request, ChinaDocument::TYPE_INVOICE, $operationId, null));
    }

    public function issueInvoice(Request $request, int $operationId): JsonResponse
    {
        return $this->responder(function () use ($request, $operationId) {
            $document = $this->documents->issueInvoice($operationId, $this->actor($request));

            return response()->json(['success' => true, 'data' => $this->documents->present($document)], 201);
        });
    }

    public function balanceHistory(Request $request): JsonResponse
    {
        return $this->responder(fn () => $this->history($request, ChinaDocument::TYPE_BALANCE, null, $this->routeOrganizationId($request)));
    }

    public function issueBalance(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $actor = $this->actor($request);
            $organizationId = $this->routeOrganizationId($request);
            $document = $this->documents->issueBalance($organizationId ?? $actor['organization_ids'][0], $actor);

            return response()->json(['success' => true, 'data' => $this->documents->present($document)], 201);
        });
    }

    private function history(Request $request, string $type, ?int $operationId, ?int $organizationId): JsonResponse
    {
        $actor = $this->actor($request);

        return response()->json(['success' => true, 'data' => $this->documents->history($type, $operationId, $organizationId, $actor)]);
    }

    /**
     * Las rutas del socio no traen organizationId: el default `scope` se inyectaría por posición en un parámetro tipado.
     */
    private function routeOrganizationId(Request $request): ?int
    {
        $id = $request->route('organizationId');

        return $id !== null ? (int) $id : null;
    }

    /**
     * Las rutas del socio declaran scope=partner; el resto usa la autorización de Almacén China.
     *
     * @return array{id: int|null, name: string, organization_ids: array<int, int>}
     */
    private function actor(Request $request): array
    {
        return $request->route('scope') === 'partner'
            ? $this->authorization->requirePartner($request->user())
            : $this->authorization->requireWarehouse($request->user());
    }
}
