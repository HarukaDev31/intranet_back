<?php

namespace App\Http\Controllers;

use App\Traits\UsesObjectStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

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
            $path = rawurldecode($path);
            $path = preg_replace('#/+#', '/', trim($path, '/'));

            if (!$this->objectStorage()->exists($path)) {
                abort(404, 'Archivo no encontrado');
            }

            $response = $this->objectStorage()->fileResponse($path, null, 'inline');

            return $this->withCorsHeaders($response);
        } catch (\Exception $e) {
            Log::error('FileController: serveFile', [
                'path' => $path ?? null,
                'error' => $e->getMessage(),
            ]);
            abort(500);
        }
    }

    private function withCorsHeaders(Response $response): Response
    {
        $origin = request()->header('origin');
        $allowedOrigin = '*';

        if ($origin) {
            if (preg_match('#^https?://(.*\.)?probusiness\.pe(:\d+)?$#i', $origin) ||
                preg_match('#^http://localhost(:\d+)?$#i', $origin)) {
                $allowedOrigin = $origin;
            }
        }

        $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, Range');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Cache-Control', 'public, max-age=3600');

        return $response;
    }
}
