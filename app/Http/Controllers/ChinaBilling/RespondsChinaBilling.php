<?php

namespace App\Http\Controllers\ChinaBilling;

use App\Exceptions\ChinaBillingException;
use App\Http\Controllers\PagoProveedor\RespondeConResource;
use App\Http\Resources\ChinaBilling\ChinaBillingRowResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Respuestas y manejo de errores compartidos por los controladores de Operaciones/Pagos de China.
 */
trait RespondsChinaBilling
{
    use RespondeConResource;

    /**
     * @param  array<string, mixed>  $result  page (LengthAwarePaginator) + datos extra (counts, summary…)
     */
    protected function listResponse(array $result): JsonResponse
    {
        $page = $result['page'];

        return ChinaBillingRowResource::collection($page->items())->additional(array_merge(
            ['success' => true, 'pagination' => [
                'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(), 'total' => $page->total(),
            ]],
            array_diff_key($result, ['page' => true])
        ))->response();
    }

    protected function responder(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (ChinaBillingException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], $exception->status);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Operaciones China: '.$exception->getMessage(), ['exception' => $exception]);

            return response()->json(['success' => false, 'message' => 'No se pudo completar la operación.'], 500);
        }
    }
}
