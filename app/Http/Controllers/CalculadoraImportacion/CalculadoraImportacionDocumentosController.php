<?php

namespace App\Http\Controllers\CalculadoraImportacion;

use App\Http\Controllers\Controller;
use App\Models\CalculadoraImportacion;
use App\Models\CalculadoraImportacionDocumento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Documentos asociados a una cotización de la calculadora de importación.
 */
class CalculadoraImportacionDocumentosController extends Controller
{
    /**
     * Lista los documentos de una cotización calculadora.
     */
    public function index(int $id)
    {
        $cotizacion = CalculadoraImportacion::find($id);
        if (!$cotizacion) {
            return response()->json(['success' => false, 'message' => 'Cotización no encontrada'], 404);
        }

        $documentos = CalculadoraImportacionDocumento::where('id_calculadora_importacion', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        $items = $documentos->map(function ($doc) {
            return [
                'id' => $doc->id,
                'file_url' => $this->generateFileUrl($doc->file_url),
                'file_name' => $doc->file_name ?? basename($doc->file_url),
                'size' => $doc->size ?? 0,
                'created_at' => $doc->created_at ? $doc->created_at->toIso8601String() : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'cotizacion' => [
                'id' => $cotizacion->id,
                'cod_cotizacion' => $cotizacion->cod_cotizacion,
                'nombre_cliente' => $cotizacion->nombre_cliente,
            ],
        ]);
    }

    /**
     * Sube un documento para una cotización calculadora.
     */
    public function store(Request $request, int $id)
    {
        $request->validate([
            'file' => 'required|file',
        ]);

        $cotizacion = CalculadoraImportacion::find($id);
        if (!$cotizacion) {
            return response()->json(['success' => false, 'message' => 'Cotización no encontrada'], 404);
        }

        $file = $request->file('file');
        $fileUrl = $this->storeFile($file);
        if (!$fileUrl) {
            return response()->json([
                'success' => false,
                'message' => 'Tipo de archivo no permitido o archivo demasiado grande',
            ], 422);
        }

        $doc = CalculadoraImportacionDocumento::create([
            'id_calculadora_importacion' => $id,
            'file_url' => $fileUrl,
            'file_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ]);

        $doc->file_url = $this->generateFileUrl($doc->file_url);

        return response()->json([
            'success' => true,
            'message' => 'Documento subido correctamente',
            'data' => [
                'id' => $doc->id,
                'file_url' => $doc->file_url,
                'file_name' => $doc->file_name,
                'size' => $doc->size,
                'created_at' => $doc->created_at ? $doc->created_at->toIso8601String() : null,
            ],
        ]);
    }

    /**
     * Elimina un documento.
     */
    public function destroy(int $idDocumento)
    {
        $doc = CalculadoraImportacionDocumento::find($idDocumento);
        if (!$doc) {
            return response()->json(['success' => false, 'message' => 'Documento no encontrado'], 404);
        }

        if ($doc->file_url && Storage::disk('public')->exists($doc->file_url)) {
            Storage::disk('public')->delete($doc->file_url);
        }
        $doc->delete();

        return response()->json([
            'success' => true,
            'message' => 'Documento eliminado correctamente',
        ]);
    }

    private function storeFile($file): ?string
    {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'xlsm', 'txt', 'zip', 'rar'];
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, $allowed)) {
            return null;
        }
        $maxSize = 50 * 1024 * 1024; // 50MB
        if ($file->getSize() > $maxSize) {
            return null;
        }
        $filename = time() . '_' . uniqid() . '.' . $ext;
        $path = 'assets/images/calculadora-documentos/';
        return $file->storeAs($path, $filename, 'public') ?: null;
    }

    private function generateFileUrl(?string $ruta): ?string
    {
        if (empty($ruta)) {
            return null;
        }
        if (filter_var($ruta, FILTER_VALIDATE_URL)) {
            return $ruta;
        }
        $ruta = ltrim($ruta, '/');
        $baseUrl = rtrim(config('app.url'), '/');
        $storagePath = 'storage';
        return $baseUrl . '/' . $storagePath . '/' . $ruta;
    }
}
