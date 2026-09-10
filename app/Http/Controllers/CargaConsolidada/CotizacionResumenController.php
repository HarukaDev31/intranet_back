<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Contracts\ObjectStorageConnectorInterface;
use App\Http\Controllers\Controller;
use App\Services\CargaConsolidada\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Flujo "resumen": organizaciones sin items (calculadora), que suben un
 * documento y lo leen con IA en vez de cargar producto por producto.
 *
 * La extracción ocurre ANTES de que exista Cotizacion/CotizacionProveedor
 * (se crean recien al finalizar el wizard), asi que el archivo se guarda en
 * una ruta de staging por organizacion; el registro de auditoria
 * (CotizacionProveedorArchivoIa) se crea recien al finalizar, cuando ya
 * existen los ids reales de contenedor/cotizacion/proveedor.
 */
class CotizacionResumenController extends Controller
{
    private const GEMINI_SUPPORTED_MIMES = [
        'application/pdf',
    ];

    private const MAX_FILE_SIZE_KB = 10240; // 10 MB

    private function objectStorage(): ObjectStorageConnectorInterface
    {
        return app(ObjectStorageConnectorInterface::class);
    }

    /**
     * Sube el documento de la cotización y lo procesa con IA.
     * POST /api/carga-consolidada/cotizacion-resumen/extraer-documento
     */
    public function extraerDocumento(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:' . self::MAX_FILE_SIZE_KB,
        ]);

        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return response()->json(['success' => false, 'message' => 'Archivo inválido'], 422);
        }

        $authUser = auth()->user();
        $orgId = (int) $authUser->getAttribute('ID_Organizacion');

        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();

        $storedPath = $this->objectStorage()->storeUploadedFile(
            $file,
            'cargaconsolidada/cotizacion-resumen/staging/' . $orgId,
            Str::uuid()->toString() . '-' . $originalName
        );

        $data = null;
        $extractedByAi = false;
        $error = null;

        if (in_array($mimeType, self::GEMINI_SUPPORTED_MIMES, true)) {
            $gemini = new GeminiService();
            $filePath = $this->objectStorage()->localPath($storedPath);
            $result = $gemini->extractFromCotizacionResumen($filePath, $mimeType);

            if ($result['success']) {
                $data = $result['data'];
                $extractedByAi = true;
            } else {
                $error = $result['error'];
                Log::warning('CotizacionResumenController: Gemini no pudo extraer datos', [
                    'organizacion_id' => $orgId,
                    'mime_type' => $mimeType,
                    'error' => $error,
                ]);
            }
        } else {
            Log::info('CotizacionResumenController: mime no soportado para extracción IA', [
                'mime_type' => $mimeType,
            ]);
        }

        return response()->json([
            'success' => true,
            'extracted_by_ai' => $extractedByAi,
            'message' => $extractedByAi
                ? null
                : ($error ?? 'No se pudo leer el documento automáticamente; completa los datos a mano.'),
            'data' => $data,
            'archivo' => [
                'path' => $storedPath,
                'nombre_original' => $originalName,
                'mime_type' => $mimeType,
                'size' => $file->getSize(),
            ],
        ]);
    }
}
