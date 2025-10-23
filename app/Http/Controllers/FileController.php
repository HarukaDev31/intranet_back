<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function serveFile($path)
    {
        try {
            // Intentar múltiples ubicaciones posibles
            $possiblePaths = [
                public_path('storage/' . $path),           // Para archivos en public/storage/
                storage_path('app/public/' . $path),       // Para archivos en storage/app/public/
                public_path($path),                        // Para archivos directamente en public/
            ];
            
            $filePath = null;
            foreach ($possiblePaths as $possiblePath) {
                if (file_exists($possiblePath)) {
                    $filePath = $possiblePath;
                    break;
                }
            }
            
            if (!$filePath) {
                \Log::error('Archivo no encontrado en ninguna ubicación', [
                    'path' => $path,
                    'attempted_paths' => $possiblePaths
                ]);
                abort(404, 'Archivo no encontrado');
            }
            
            $file = file_get_contents($filePath);
            $mimeType = mime_content_type($filePath);
            $fileName = basename($filePath);
            
            \Log::info('Sirviendo archivo con CORS', [
                'file_path' => $filePath,
                'mime_type' => $mimeType,
                'file_size' => strlen($file)
            ]);
            
            return response($file, 200)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'inline; filename="' . $fileName . '"')
                ->header('Access-Control-Allow-Origin', 'http://localhost:3001')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Cache-Control', 'public, max-age=3600');
                
        } catch (\Exception $e) {
            \Log::error('Error al servir archivo: ' . $e->getMessage(), [
                'path' => $path,
                'trace' => $e->getTraceAsString()
            ]);
            abort(500, 'Error al servir el archivo');
        }
    }
}
