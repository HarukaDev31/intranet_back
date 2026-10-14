<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\CotizacionProveedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * API de integración: actualizar el code_supplier de un proveedor de cotización resumen.
 *
 * Reglas (alineadas con la lógica existente de códigos):
 * - Solo proveedores de la organización del token (modo resumen, cotización no eliminada).
 * - Formato [A-Z0-9] separado por guiones (se normaliza a mayúsculas).
 * - El código no puede existir en NINGÚN otro proveedor del sistema (ni de la misma organización
 *   ni de otras) ni en la calculadora. Si lo usa una cotización resumen CONFIRMADA no se puede
 *   reutilizar.
 * - Queda en auditoría el valor anterior y el nuevo.
 */
class ProveedorCodigoIntegracionController extends Controller
{
    private const FORMATO = '/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/';

    // PUT /api/integration/v1/proveedores/{id}/code-supplier
    public function actualizar(Request $request, $id): JsonResponse
    {
        /** @var ApiClient $client */
        $client = $request->attributes->get('api_client');
        $orgId = (int) $client->organizacion_id;

        $v = Validator::make($request->all(), ['code_supplier' => 'required|string|min:3|max:60']);
        if ($v->fails()) {
            return $this->error('Datos inválidos.', 422, $v->errors()->toArray());
        }
        if ($extra = array_diff(array_keys($request->all()), ['code_supplier'])) {
            return $this->error('Campos no permitidos: ' . implode(', ', array_slice($extra, 0, 5)), 422);
        }

        $nuevo = strtoupper(trim((string) $request->input('code_supplier')));
        if (!preg_match(self::FORMATO, $nuevo)) {
            return $this->error('Formato inválido: solo letras mayúsculas, números y guiones (ej. BOL2-JUPE1-1).', 422);
        }

        try {
            return DB::transaction(function () use ($request, $orgId, $id, $nuevo) {
                // Todo filtrado por la organización del token: un id de otra org responde igual que uno inexistente.
                $proveedor = CotizacionProveedor::query()
                    ->where('id', (int) $id)
                    ->where('organizacion_id', $orgId)
                    ->where('modo_cotizacion', 'resumen')
                    ->lockForUpdate()
                    ->first();
                $cotizacion = $proveedor
                    ? Cotizacion::query()->where('id', $proveedor->id_cotizacion)->where('organizacion_id', $orgId)->first()
                    : null;
                if (!$proveedor || !$cotizacion) {
                    return $this->error('Proveedor no encontrado.', 404);
                }

                $anterior = trim((string) $proveedor->code_supplier);
                if ($anterior === $nuevo) {
                    $request->attributes->set('audit_meta', ['id_proveedor' => $proveedor->id, 'sin_cambios' => true]);

                    return response()->json([
                        'success' => true,
                        'message' => 'El proveedor ya tenía ese código.',
                        'data' => ['id' => $proveedor->id, 'code_supplier' => $nuevo],
                    ]);
                }

                $conflicto = $this->conflicto($nuevo, (int) $proveedor->id);
                if ($conflicto !== null) {
                    $request->attributes->set('audit_error', 'code_en_uso');
                    $request->attributes->set('audit_meta', ['id_proveedor' => $proveedor->id, 'rechazado' => $conflicto['motivo']]);

                    return $this->error($conflicto['mensaje'], 409);
                }

                $proveedor->code_supplier = $nuevo;
                $proveedor->save();

                $request->attributes->set('audit_meta', [
                    'id_proveedor' => $proveedor->id,
                    'id_cotizacion' => $proveedor->id_cotizacion,
                    'code_anterior' => $anterior !== '' ? $anterior : null,
                    'code_nuevo' => $nuevo,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Código actualizado.',
                    'data' => ['id' => $proveedor->id, 'code_supplier' => $nuevo],
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('api.integration actualizar code_supplier: ' . $e->getMessage(), ['api_client_id' => $client->id]);
            $request->attributes->set('audit_error', 'excepcion_code');

            return $this->error('No se pudo actualizar el código.', 500);
        }
    }

    /**
     * ¿El código ya lo usa otro proveedor o la calculadora, en cualquier organización?
     *
     * @return array{motivo: string, mensaje: string}|null
     */
    private function conflicto(string $codigo, int $idProveedor): ?array
    {
        // Proveedores de cualquier organización (sin scopes: aquí se valida contra TODO el sistema).
        $uso = DB::table('contenedor_consolidado_cotizacion_proveedores as p')
            ->leftJoin('contenedor_consolidado_cotizacion as q', 'q.id', '=', 'p.id_cotizacion')
            ->where('p.id', '!=', $idProveedor)
            ->whereRaw('UPPER(TRIM(p.code_supplier)) = ?', [$codigo])
            ->orderByRaw("CASE WHEN q.estado_resumen = 'CONFIRMADO' AND q.deleted_at IS NULL THEN 0 ELSE 1 END")
            ->first(['p.id', 'q.estado_resumen', 'q.deleted_at']);

        if ($uso) {
            $confirmada = $uso->estado_resumen === 'CONFIRMADO' && $uso->deleted_at === null;

            return $confirmada
                ? ['motivo' => 'cotizacion_confirmada', 'mensaje' => 'El código está en uso por una cotización confirmada y no se puede reutilizar.']
                : ['motivo' => 'code_existente', 'mensaje' => 'El código ya existe en el sistema.'];
        }

        $enCalculadora = DB::table('calculadora_importacion_proveedores')
            ->where(function ($q) use ($idProveedor) {
                $q->whereNull('id_proveedor')->orWhere('id_proveedor', '!=', $idProveedor);
            })
            ->whereRaw('UPPER(TRIM(code_supplier)) = ?', [$codigo])
            ->exists();
        if ($enCalculadora) {
            return ['motivo' => 'code_calculadora', 'mensaje' => 'El código ya existe en el sistema.'];
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $errores
     */
    private function error(string $mensaje, int $status, ?array $errores = null): JsonResponse
    {
        $cuerpo = ['success' => false, 'message' => $mensaje];
        if ($errores !== null) {
            $cuerpo['errors'] = $errores;
        }

        return response()->json($cuerpo, $status);
    }
}
