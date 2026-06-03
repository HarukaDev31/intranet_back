<?php

namespace App\Http\Controllers;

use App\Traits\UsesObjectStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class FileController extends Controller
{
    use UsesObjectStorage;
    /**
     * @OA\Get(
     *     path="/files/{path}",
     *     tags={"Archivos"},
     *     summary="Servir archivo",
     *     description="Sirve un archivo desde el almacenamiento con headers CORS apropiados",
     *     operationId="serveFile",
     *     @OA\Parameter(name="path", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Archivo servido exitosamente"),
     *     @OA\Response(response=404, description="Archivo no encontrado")
     * )
     */
    public function serveFile($path)
    {
        try {
            // Decodificar path (viene codificado para soportar #, espacios, etc.)
            $path = rawurldecode($path);
            // Normalizar dobles barras y barras iniciales
            $path = preg_replace('#/+#', '/', trim($path, '/'));

            if (!$this->objectStorage()->exists($path)) {
                abort(404, 'Archivo no encontrado');
            }

            $publicUrl = $this->objectStorage()->url($path);
            if ($publicUrl !== null && config('object_storage.cdn_base_url') !== '') {
                return redirect()->away($publicUrl);
            }

            $filePath = $this->storageLocalPath($path);
            
            // CORS
            $origin = request()->header('origin');
            $allowedOrigin = '*';
            
            if ($origin) {
                if (preg_match('#^https?://(.*\.)?probusiness\.pe(:\d+)?$#i', $origin) ||
                    preg_match('#^http://localhost(:\d+)?$#i', $origin)) {
                    $allowedOrigin = $origin;
                }
            }
            
            $response = response()->file($filePath, [
                'Content-Type' => mime_content_type($filePath),
                'Access-Control-Allow-Origin' => $allowedOrigin,
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, Range',
                'Access-Control-Allow-Credentials' => 'true',
                'Cache-Control' => 'public, max-age=3600',
            ]);
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error('Error: ' . $e->getMessage());
            abort(500);
        }
    }
}
