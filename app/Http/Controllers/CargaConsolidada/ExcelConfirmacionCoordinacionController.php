<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Services\CargaConsolidada\Clientes\ExcelConfirmacionDocumentosService;
use App\Services\CargaConsolidada\Clientes\ExcelConfirmacionFormService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class ExcelConfirmacionCoordinacionController extends Controller
{
    public function __construct(
        private ExcelConfirmacionFormService $formService,
        private ExcelConfirmacionDocumentosService $excelService
    ) {
    }

    public function labels(): JsonResponse
    {
        if ($denied = $this->authorizeCoordinacion()) {
            return $denied;
        }

        return response()->json([
            'success' => true,
            'data' => $this->excelService->getLabelsPorTipoProductoFiltradas(),
        ]);
    }

    public function show(string $uuid): JsonResponse
    {
        if ($denied = $this->authorizeCoordinacion()) {
            return $denied;
        }

        try {
            $payload = $this->formService->buildShowPayload($uuid);
            if (!$payload) {
                return response()->json(['success' => false, 'message' => 'Cotización no encontrada'], 404);
            }

            return response()->json(['success' => true, 'data' => $payload]);
        } catch (\Throwable $e) {
            Log::error('ExcelConfirmacionCoordinacionController::show — ' . $e->getMessage(), ['uuid' => $uuid]);

            return response()->json(['success' => false, 'message' => 'Error al obtener confirmación'], 500);
        }
    }

    public function update(string $uuid, Request $request): JsonResponse
    {
        if ($denied = $this->authorizeCoordinacion()) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'proveedores' => 'required|array|min:1',
            'proveedores.*.id' => 'required|integer',
            'proveedores.*.items' => 'required|array|min:1',
            'proveedores.*.items.*.id' => 'required|integer',
            'proveedores.*.items.*.is_new' => 'nullable|boolean',
            'proveedores.*.items.*.tipo_producto' => 'nullable|string|max:64',
            'proveedores.*.items.*.caracteristicas' => 'nullable|array',
            'proveedores.*.items.*.qty' => 'nullable|numeric|min:0',
            'proveedores.*.items.*.precio_unitario' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->formService->saveConfirmation(
            $uuid,
            $request->input('proveedores', []),
            ignoreCerrado: true
        );

        return $this->saveResultResponse($result);
    }

    private function saveResultResponse(array $result): JsonResponse
    {
        $messages = [
            'COTIZACION_NOT_FOUND' => 'Cotización no encontrada',
            'PROVEEDOR_INVALIDO' => 'Proveedor no válido para esta cotización',
            'ITEM_INVALIDO' => 'Producto no válido para este proveedor',
            'FORMULARIO_CERRADO' => 'Formulario cerrado para el cliente',
            'GUARDADO_OK' => 'Confirmación actualizada correctamente.',
            'ERROR_INTERNO' => 'Error al guardar la confirmación',
        ];

        $code = (string) ($result['code'] ?? 'ERROR_INTERNO');
        $status = (int) ($result['status'] ?? ($result['success'] ? 200 : 500));

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'code' => $code,
                'message' => $messages[$code] ?? $messages['ERROR_INTERNO'],
            ], $status);
        }

        return response()->json([
            'success' => true,
            'code' => $code,
            'message' => $messages['GUARDADO_OK'],
        ], $status);
    }

    public function cerrar(int $idProveedor): JsonResponse
    {
        if ($denied = $this->authorizeCoordinacion()) {
            return $denied;
        }

        if (!$this->formService->setProveedorCerrado($idProveedor, true)) {
            return response()->json(['success' => false, 'message' => 'Proveedor no encontrado'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Formulario cerrado. El cliente ya no puede editarlo.',
            'data' => ['excel_conf_form_cerrado' => true],
        ]);
    }

    public function reabrir(int $idProveedor): JsonResponse
    {
        if ($denied = $this->authorizeCoordinacion()) {
            return $denied;
        }

        if (!$this->formService->setProveedorCerrado($idProveedor, false)) {
            return response()->json(['success' => false, 'message' => 'Proveedor no encontrado'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Formulario reabierto para el cliente.',
            'data' => ['excel_conf_form_cerrado' => false],
        ]);
    }

    public function exportExcel(int $idProveedor)
    {
        if ($denied = $this->authorizeCoordinacion()) {
            return $denied;
        }

        try {
            $payload = $this->formService->buildProveedorExportPayload($idProveedor);
            if (!$payload) {
                return response()->json(['success' => false, 'message' => 'Proveedor no encontrado'], 404);
            }

            $templatePath = $this->formService->resolveTemplatePath();
            if ($templatePath === null) {
                return response()->json(['success' => false, 'message' => 'Plantilla de Excel no disponible'], 500);
            }

            $outputDir = storage_path('app/temp/excel-confirmacion');
            if (!is_dir($outputDir)) {
                @mkdir($outputDir, 0775, true);
            }

            $codeSupplier = (string) ($payload['code_supplier'] ?? '');
            $suffix = $codeSupplier !== '' ? $codeSupplier : ('prov_' . $idProveedor);
            $fileName = 'excel_confirmacion_' . $suffix . '.xlsx';
            $fullPath = $outputDir . DIRECTORY_SEPARATOR . $fileName;

            $ok = $this->excelService->generarArchivoPorProveedor($templatePath, $fullPath, $payload);

            if (!$ok || !file_exists($fullPath)) {
                return response()->json(['success' => false, 'message' => 'No se pudo generar el Excel'], 500);
            }

            return response()->download($fullPath, $fileName)->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            Log::error('ExcelConfirmacionCoordinacionController::exportExcel — ' . $e->getMessage(), [
                'id_proveedor' => $idProveedor,
            ]);

            return response()->json(['success' => false, 'message' => 'Error al generar Excel'], 500);
        }
    }

    public function exportExcelGeneral(string $uuid)
    {
        if ($denied = $this->authorizeCoordinacion()) {
            return $denied;
        }

        try {
            $exportData = $this->formService->buildCotizacionExportPayload($uuid);
            if (!$exportData) {
                return response()->json(['success' => false, 'message' => 'Cotización no encontrada o sin proveedores'], 404);
            }

            $templatePath = $this->formService->resolveTemplatePath();
            if ($templatePath === null) {
                return response()->json(['success' => false, 'message' => 'Plantilla de Excel no disponible'], 500);
            }

            $outputDir = storage_path('app/temp/excel-confirmacion');
            if (!is_dir($outputDir)) {
                @mkdir($outputDir, 0775, true);
            }

            $clientSlug = preg_replace('/[^\w\-]+/u', '_', (string) ($exportData['nombre_cliente'] ?? 'cliente'));
            $clientSlug = trim($clientSlug, '_') ?: 'cliente';
            $fileName = 'excel_confirmacion_general_' . $clientSlug . '.xlsx';
            $fullPath = $outputDir . DIRECTORY_SEPARATOR . uniqid('excel_conf_general_', true) . '.xlsx';

            $ok = $this->excelService->generarArchivoGeneralPorCotizacion(
                $templatePath,
                $fullPath,
                $exportData['proveedores']
            );

            if (!$ok || !file_exists($fullPath)) {
                return response()->json(['success' => false, 'message' => 'No se pudo generar el Excel general'], 500);
            }

            return response()->download($fullPath, $fileName)->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            Log::error('ExcelConfirmacionCoordinacionController::exportExcelGeneral — ' . $e->getMessage(), [
                'uuid' => $uuid,
            ]);

            return response()->json(['success' => false, 'message' => 'Error al generar Excel general'], 500);
        }
    }

    private function authorizeCoordinacion(): ?JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'No autenticado'], 401);
        }

        $allowed = [
            Usuario::ROL_COORDINACION,
            Usuario::ROL_JEFE_IMPORTACION,
            Usuario::ROL_ADMINISTRACION,
            Usuario::ROL_CONTABILIDAD,
        ];

        if (!in_array($user->getNombreGrupo(), $allowed, true)) {
            return response()->json(['success' => false, 'message' => 'Sin permisos'], 403);
        }

        return null;
    }
}
