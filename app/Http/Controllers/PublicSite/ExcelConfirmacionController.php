<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Services\CargaConsolidada\Clientes\ExcelConfirmacionDocumentosService;
use App\Services\CargaConsolidada\Clientes\ExcelConfirmacionFormService;
use App\Support\ExcelConfirmacion\ExcelConfirmacionClientResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ExcelConfirmacionController extends Controller
{
    public function __construct(
        private ExcelConfirmacionDocumentosService $excelService,
        private ExcelConfirmacionFormService $formService
    ) {
    }

    public function labels(): JsonResponse
    {
        try {
            return ExcelConfirmacionClientResponse::data(
                $this->excelService->getLabelsPorTipoProductoFiltradas()
            );
        } catch (\Throwable $e) {
            Log::error('ExcelConfirmacionController::labels — ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return ExcelConfirmacionClientResponse::error('LABELS_NO_DISPONIBLES', 500);
        }
    }

    public function show(string $uuid): JsonResponse
    {
        try {
            $payload = $this->formService->buildShowPayload($uuid);
            if (!$payload) {
                return ExcelConfirmacionClientResponse::error('COTIZACION_NOT_FOUND', 404);
            }

            return ExcelConfirmacionClientResponse::data($payload);
        } catch (\Throwable $e) {
            Log::error('ExcelConfirmacionController::show — ' . $e->getMessage(), [
                'uuid' => $uuid,
                'exception' => $e,
            ]);

            return ExcelConfirmacionClientResponse::error('DATOS_NO_DISPONIBLES', 500);
        }
    }

    public function update(string $uuid, Request $request): JsonResponse
    {
        $proveedores = $request->input('proveedores');
        if (is_string($proveedores)) {
            $decoded = json_decode($proveedores, true);
            $proveedores = is_array($decoded) ? $decoded : null;
        }

        $validator = Validator::make(
            array_merge($request->all(), ['proveedores' => $proveedores]),
            [
                'proveedores' => 'required|array|min:1',
                'proveedores.*.id' => 'required|integer',
                'proveedores.*.items' => 'required|array|min:1',
                'proveedores.*.items.*.id' => 'required|integer',
                'proveedores.*.items.*.is_new' => 'nullable|boolean',
                'proveedores.*.items.*.tipo_producto' => 'nullable|string|max:64',
                'proveedores.*.items.*.caracteristicas' => 'nullable|array',
                'proveedores.*.items.*.qty' => 'nullable|numeric|min:0',
                'proveedores.*.items.*.precio_unitario' => 'nullable|numeric|min:0',
                'fotos' => 'nullable|array',
                'fotos.*.*' => 'nullable|file|image|max:5120',
            ]
        );

        if ($validator->fails()) {
            return ExcelConfirmacionClientResponse::validationFailed($validator->errors());
        }

        try {
            $proveedores = $this->formService->attachFotoFilesFromRequest($proveedores, $request);

            $result = $this->formService->saveConfirmation(
                $uuid,
                $proveedores,
                ignoreCerrado: false
            );

            if (!$result['success']) {
                return ExcelConfirmacionClientResponse::error(
                    (string) ($result['code'] ?? 'ERROR_INTERNO'),
                    (int) ($result['status'] ?? 500)
                );
            }

            return ExcelConfirmacionClientResponse::success([], 'GUARDADO_OK', 200);
        } catch (\Throwable $e) {
            Log::error('ExcelConfirmacionController::update — ' . $e->getMessage(), [
                'uuid' => $uuid,
                'exception' => $e,
            ]);

            return ExcelConfirmacionClientResponse::error('ERROR_INTERNO', 500);
        }
    }
}
