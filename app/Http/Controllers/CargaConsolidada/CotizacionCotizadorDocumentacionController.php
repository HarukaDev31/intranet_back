<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\CotizacionCotizadorDocumento;
use App\Models\CargaConsolidada\CotizacionCotizadorProveedorDocumento;
use App\Models\CargaConsolidada\CotizacionProveedor;

/**
 * Documentación de cotización para perfil cotizador.
 * Tablas: contenedor_consolidado_cotizacion_cotizador_documentos, contenedor_consolidado_cotizacion_cotizador_proveedor_documentos
 */
class CotizacionCotizadorDocumentacionController extends Controller
{
    /**
     * Obtiene la documentación completa de una cotización (vista cotizador).
     */
    public function show($idCotizacion)
    {
        try {
            $main = DB::table('contenedor_consolidado_cotizacion as main')
                ->select([
                    'main.*',
                    DB::raw("(
                        SELECT JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'id', d.id,
                                'id_proveedor', d.id_proveedor,
                                'tipo_documento', d.tipo_documento,
                                'folder_name', d.folder_name,
                                'file_url', d.file_url
                            )
                        )
                        FROM contenedor_consolidado_cotizacion_cotizador_documentos d
                        WHERE d.id_cotizacion = main.id
                    ) as files"),
                    DB::raw("(
                        SELECT JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'code_supplier', p.code_supplier,
                                'id', p.id,
                                'products', p.products
                            )
                        )
                        FROM contenedor_consolidado_cotizacion_proveedores p
                        WHERE p.id_cotizacion = main.id
                    ) as providers"),
                    DB::raw("(
                        SELECT JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'id_proveedor', pd.id_proveedor,
                                'id', pd.id,
                                'file_url', pd.file_url,
                                'orden', pd.orden
                            )
                        )
                        FROM contenedor_consolidado_cotizacion_cotizador_proveedor_documentos pd
                        WHERE pd.id_cotizacion = main.id
                    ) as proveedor_documentos")
                ])
                ->where('main.id', $idCotizacion)
                ->whereNotNull('main.estado')
                ->first();

            if (!$main) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cotización no encontrada'
                ], 404);
            }

            if ($main->files) {
                $main->files = json_decode($main->files, true) ?: [];
            } else {
                $main->files = [];
            }
            if ($main->providers) {
                $main->providers = json_decode($main->providers, true) ?: [];
            } else {
                $main->providers = [];
            }
            if ($main->proveedor_documentos) {
                $main->proveedor_documentos = json_decode($main->proveedor_documentos, true) ?: [];
            } else {
                $main->proveedor_documentos = [];
            }

            foreach ($main->files as &$f) {
                if (!empty($f['file_url'])) {
                    $f['file_url'] = $this->generateImageUrl($f['file_url']);
                }
            }
            foreach ($main->proveedor_documentos as &$f) {
                if (!empty($f['file_url'])) {
                    $f['file_url'] = $this->generateImageUrl($f['file_url']);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $main,
                'message' => 'Documentación obtenida correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener documentación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sube un documento general (Proforma, Packing List, Ficha Técnica o custom).
     */
    public function uploadDocumento(Request $request)
    {
        try {
            $request->validate([
                'id_cotizacion' => 'required|integer',
                'tipo_documento' => 'required|string|max:100',
                'file' => 'required|file',
                'folder_name' => 'nullable|string|max:255'
            ]);

            $cotizacion = Cotizacion::find($request->id_cotizacion);
            if (!$cotizacion) {
                return response()->json(['success' => false, 'message' => 'Cotización no encontrada'], 404);
            }

            $file = $request->file('file');
            $fileUrl = $this->storeFile($file);
            if (!$fileUrl) {
                return response()->json(['success' => false, 'message' => 'Error al guardar el archivo'], 500);
            }

            $doc = CotizacionCotizadorDocumento::create([
                'id_cotizacion' => $request->id_cotizacion,
                'tipo_documento' => $request->tipo_documento,
                'folder_name' => $request->folder_name,
                'file_url' => $fileUrl
            ]);

            $doc->file_url = $this->generateImageUrl($doc->file_url);

            return response()->json([
                'success' => true,
                'message' => 'Documento subido correctamente',
                'data' => $doc
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al subir documento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Elimina un documento general.
     */
    public function deleteDocumento($id)
    {
        try {
            $doc = CotizacionCotizadorDocumento::find($id);
            if (!$doc) {
                return response()->json(['success' => false, 'message' => 'Documento no encontrado'], 404);
            }
            if ($doc->file_url && Storage::disk('public')->exists($doc->file_url)) {
                Storage::disk('public')->delete($doc->file_url);
            }
            $doc->delete();
            return response()->json([
                'success' => true,
                'message' => 'Documento eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sube un documento por proveedor (máximo 4 por proveedor).
     */
    public function uploadProveedorDocumento(Request $request)
    {
        try {
            $request->validate([
                'id_cotizacion' => 'required|integer',
                'id_proveedor' => 'required|integer',
                'file' => 'required|file'
            ]);

            $proveedor = CotizacionProveedor::where('id', $request->id_proveedor)
                ->where('id_cotizacion', $request->id_cotizacion)
                ->first();
            if (!$proveedor) {
                return response()->json(['success' => false, 'message' => 'Proveedor no encontrado'], 404);
            }

            $count = CotizacionCotizadorProveedorDocumento::where('id_cotizacion', $request->id_cotizacion)
                ->where('id_proveedor', $request->id_proveedor)
                ->count();
            if ($count >= 4) {
                return response()->json([
                    'success' => false,
                    'message' => 'Máximo 4 documentos por proveedor'
                ], 422);
            }

            $file = $request->file('file');
            $fileUrl = $this->storeFile($file);
            if (!$fileUrl) {
                return response()->json(['success' => false, 'message' => 'Error al guardar el archivo'], 500);
            }

            $orden = $count + 1;
            $doc = CotizacionCotizadorProveedorDocumento::create([
                'id_cotizacion' => $request->id_cotizacion,
                'id_proveedor' => $request->id_proveedor,
                'file_url' => $fileUrl,
                'orden' => $orden
            ]);

            $doc->file_url = $this->generateImageUrl($doc->file_url);

            return response()->json([
                'success' => true,
                'message' => 'Documento subido correctamente',
                'data' => $doc
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al subir documento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Elimina un documento por proveedor.
     */
    public function deleteProveedorDocumento($id)
    {
        try {
            $doc = CotizacionCotizadorProveedorDocumento::find($id);
            if (!$doc) {
                return response()->json(['success' => false, 'message' => 'Documento no encontrado'], 404);
            }
            if ($doc->file_url && Storage::disk('public')->exists($doc->file_url)) {
                Storage::disk('public')->delete($doc->file_url);
            }
            $doc->delete();
            return response()->json([
                'success' => true,
                'message' => 'Documento eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Elimina en lote documentos generales y por proveedor en una sola petición.
     * Body: { document_ids: number[], proveedor_document_ids: number[] }
     */
    public function batchDelete(Request $request)
    {
        try {
            $request->validate([
                'document_ids' => 'nullable|array',
                'document_ids.*' => 'integer',
                'proveedor_document_ids' => 'nullable|array',
                'proveedor_document_ids.*' => 'integer',
            ]);

            $documentIds = $request->input('document_ids', []);
            $proveedorDocumentIds = $request->input('proveedor_document_ids', []);

            DB::transaction(function () use ($documentIds, $proveedorDocumentIds) {
                if (!empty($documentIds)) {
                    $docs = CotizacionCotizadorDocumento::whereIn('id', $documentIds)->get();
                    foreach ($docs as $doc) {
                        if ($doc->file_url && Storage::disk('public')->exists($doc->file_url)) {
                            Storage::disk('public')->delete($doc->file_url);
                        }
                    }
                    CotizacionCotizadorDocumento::whereIn('id', $documentIds)->delete();
                }
                if (!empty($proveedorDocumentIds)) {
                    $docs = CotizacionCotizadorProveedorDocumento::whereIn('id', $proveedorDocumentIds)->get();
                    foreach ($docs as $doc) {
                        if ($doc->file_url && Storage::disk('public')->exists($doc->file_url)) {
                            Storage::disk('public')->delete($doc->file_url);
                        }
                    }
                    CotizacionCotizadorProveedorDocumento::whereIn('id', $proveedorDocumentIds)->delete();
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Eliminación en lote completada',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sincroniza documentación e imágenes en una sola petición: elimina los IDs de BD indicados y sube los nuevos archivos.
     * Body (multipart): id_cotizacion, document_ids_to_delete (JSON array), proveedor_document_ids_to_delete (JSON array),
     * document_meta (JSON array [{tipo_documento, folder_name?}]), document_file_0, document_file_1, ...
     * proveedor_meta (JSON array [{id_proveedor}]), proveedor_file_0, proveedor_file_1, ...
     */
    public function sync(Request $request)
    {
        try {
            $request->validate([
                'id_cotizacion' => 'required|integer',
                'document_ids_to_delete' => 'nullable|string',
                'proveedor_document_ids_to_delete' => 'nullable|string',
                'document_meta' => 'nullable|string',
                'proveedor_meta' => 'nullable|string',
            ]);

            $idCotizacion = (int) $request->id_cotizacion;
            $cotizacion = Cotizacion::find($idCotizacion);
            if (!$cotizacion) {
                return response()->json(['success' => false, 'message' => 'Cotización no encontrada'], 404);
            }

            $documentIdsToDelete = json_decode($request->input('document_ids_to_delete', '[]'), true) ?: [];
            $proveedorIdsToDelete = json_decode($request->input('proveedor_document_ids_to_delete', '[]'), true) ?: [];
            $documentMeta = json_decode($request->input('document_meta', '[]'), true) ?: [];
            $proveedorMeta = json_decode($request->input('proveedor_meta', '[]'), true) ?: [];

            DB::transaction(function () use ($idCotizacion, $documentIdsToDelete, $proveedorIdsToDelete, $request, $documentMeta, $proveedorMeta) {
                // 1) Eliminar documentos e imágenes por ID (solo los que vienen de la BD)
                if (!empty($documentIdsToDelete)) {
                    $docs = CotizacionCotizadorDocumento::where('id_cotizacion', $idCotizacion)
                        ->whereIn('id', $documentIdsToDelete)->get();
                    foreach ($docs as $doc) {
                        if ($doc->file_url && Storage::disk('public')->exists($doc->file_url)) {
                            Storage::disk('public')->delete($doc->file_url);
                        }
                    }
                    CotizacionCotizadorDocumento::whereIn('id', $documentIdsToDelete)->delete();
                }
                if (!empty($proveedorIdsToDelete)) {
                    $docs = CotizacionCotizadorProveedorDocumento::where('id_cotizacion', $idCotizacion)
                        ->whereIn('id', $proveedorIdsToDelete)->get();
                    foreach ($docs as $doc) {
                        if ($doc->file_url && Storage::disk('public')->exists($doc->file_url)) {
                            Storage::disk('public')->delete($doc->file_url);
                        }
                    }
                    CotizacionCotizadorProveedorDocumento::whereIn('id', $proveedorIdsToDelete)->delete();
                }

                // 2) Nuevos documentos por proveedor (document_meta con id_proveedor + document_file_0, document_file_1, ...)
                foreach ($documentMeta as $i => $meta) {
                    $fileKey = 'document_file_' . $i;
                    if (!$request->hasFile($fileKey)) {
                        continue;
                    }
                    $idProveedor = isset($meta['id_proveedor']) ? (int) $meta['id_proveedor'] : null;
                    if ($idProveedor) {
                        $exists = CotizacionProveedor::where('id', $idProveedor)->where('id_cotizacion', $idCotizacion)->exists();
                        if (!$exists) {
                            continue;
                        }
                    }
                    $file = $request->file($fileKey);
                    $fileUrl = $this->storeFile($file);
                    if (!$fileUrl) {
                        continue;
                    }
                    CotizacionCotizadorDocumento::create([
                        'id_cotizacion' => $idCotizacion,
                        'id_proveedor' => $idProveedor,
                        'tipo_documento' => $meta['tipo_documento'] ?? 'custom',
                        'folder_name' => $meta['folder_name'] ?? null,
                        'file_url' => $fileUrl,
                    ]);
                }

                // 3) Nuevos documentos por proveedor (proveedor_meta + proveedor_file_0, proveedor_file_1, ...)
                foreach ($proveedorMeta as $i => $meta) {
                    $fileKey = 'proveedor_file_' . $i;
                    if (!$request->hasFile($fileKey)) {
                        continue;
                    }
                    $idProveedor = (int) ($meta['id_proveedor'] ?? 0);
                    if (!$idProveedor) {
                        continue;
                    }
                    $exists = CotizacionProveedor::where('id', $idProveedor)->where('id_cotizacion', $idCotizacion)->exists();
                    if (!$exists) {
                        continue;
                    }
                    $count = CotizacionCotizadorProveedorDocumento::where('id_cotizacion', $idCotizacion)
                        ->where('id_proveedor', $idProveedor)->count();
                    if ($count >= 4) {
                        continue;
                    }
                    $file = $request->file($fileKey);
                    $fileUrl = $this->storeFile($file);
                    if (!$fileUrl) {
                        continue;
                    }
                    CotizacionCotizadorProveedorDocumento::create([
                        'id_cotizacion' => $idCotizacion,
                        'id_proveedor' => $idProveedor,
                        'file_url' => $fileUrl,
                        'orden' => $count + 1,
                    ]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Documentación sincronizada correctamente',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al sincronizar: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function storeFile($file): ?string
    {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, $allowed)) {
            return null;
        }
        $maxSize = 50 * 1024 * 1024; // 50MB
        if ($file->getSize() > $maxSize) {
            return null;
        }
        $filename = time() . '_' . uniqid() . '.' . $ext;
        $path = 'assets/images/agentecompra/';
        return $file->storeAs($path, $filename, 'public') ?: null;
    }

    private function generateImageUrl(?string $ruta): ?string
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
