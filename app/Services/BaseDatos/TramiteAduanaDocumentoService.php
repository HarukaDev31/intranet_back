<?php

namespace App\Services\BaseDatos;

use App\Models\CargaConsolidada\TramiteAduanaDocumento;
use App\Models\CargaConsolidada\TramiteAduanaCategoria;
use App\Models\CargaConsolidada\ConsolidadoCotizacionAduanaTramite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TramiteAduanaDocumentoService
{
    public function listarPorTramite(int $idTramite): array
    {
        try {
            $tramite = ConsolidadoCotizacionAduanaTramite::with([
                'consolidado', 'entidad', 'tipoPermiso', 'cliente', 'cotizacion'
            ])->find($idTramite);

            if (!$tramite) {
                return ['success' => false, 'error' => 'Trámite no encontrado'];
            }

            $documentos = TramiteAduanaDocumento::with('categoria')
                ->where('id_tramite', $idTramite)
                ->orderBy('id_categoria')
                ->orderBy('created_at', 'desc')
                ->get();

            $data = $documentos->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'id_tramite' => $doc->id_tramite,
                    'id_categoria' => $doc->id_categoria,
                    'categoria' => $doc->categoria ? $doc->categoria->nombre : null,
                    'nombre_documento' => $doc->nombre_documento,
                    'extension' => $doc->extension,
                    'peso' => $doc->peso,
                    'nombre_original' => $doc->nombre_original,
                    'ruta' => $doc->ruta,
                    'url' => $doc->url,
                    'created_at' => $doc->created_at ? $doc->created_at->toIso8601String() : null,
                ];
            })->all();

            return [
                'success' => true,
                'data' => $data,
                'tramite' => [
                    'id' => $tramite->id,
                    'estado' => $tramite->estado,
                    'entidad' => $tramite->entidad ? $tramite->entidad->nombre : null,
                    'tipo_permiso' => $tramite->tipoPermiso ? $tramite->tipoPermiso->nombre : null,
                    'consolidado' => $tramite->consolidado ? ($tramite->consolidado->carga ?? null) : null,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error al listar documentos del trámite: ' . $e->getMessage());
            return ['success' => false, 'data' => [], 'error' => $e->getMessage()];
        }
    }

    public function crear(Request $request, int $idTramite): array
    {
        $request->validate([
            'categoria' => 'required|string|max:255',
            'nombre_documento' => 'required|string|max:255',
            'archivo' => 'required|file|max:204800',
            'id_categoria' => 'sometimes|nullable|integer|exists:tramite_aduana_categorias,id',
        ]);

        try {
            $tramite = ConsolidadoCotizacionAduanaTramite::find($idTramite);
            if (!$tramite) {
                return ['success' => false, 'error' => 'Trámite no encontrado'];
            }

            $nombreCategoria = $request->categoria;
            $idCategoria = $request->input('id_categoria');

            if ($idCategoria) {
                $categoria = TramiteAduanaCategoria::where('id', $idCategoria)
                    ->where('id_tramite', $idTramite)
                    ->first();
                if (!$categoria) {
                    return ['success' => false, 'error' => 'Categoría no pertenece al trámite'];
                }
            } else {
                $categoria = TramiteAduanaCategoria::firstOrCreate(
                    [
                        'id_tramite' => $idTramite,
                        'nombre' => $nombreCategoria,
                    ]
                );
            }

            $archivo = $request->file('archivo');
            $filename = time() . '_' . uniqid() . '.' . $archivo->getClientOriginalExtension();
            $path = $archivo->storeAs('tramites/documentos', $filename, 'public');

            $documento = TramiteAduanaDocumento::create([
                'id_tramite' => $idTramite,
                'id_categoria' => $categoria->id,
                'nombre_documento' => $request->nombre_documento,
                'extension' => $archivo->getClientOriginalExtension(),
                'peso' => $archivo->getSize(),
                'nombre_original' => $archivo->getClientOriginalName(),
                'ruta' => $path,
            ]);

            $documento->load('categoria');

            $this->actualizarEstadoTramitePorCategoria($tramite, $nombreCategoria);

            return [
                'success' => true,
                'data' => [
                    'id' => $documento->id,
                    'id_tramite' => $documento->id_tramite,
                    'id_categoria' => $documento->id_categoria,
                    'categoria' => $documento->categoria ? $documento->categoria->nombre : $nombreCategoria,
                    'nombre_documento' => $documento->nombre_documento,
                    'extension' => $documento->extension,
                    'peso' => $documento->peso,
                    'nombre_original' => $documento->nombre_original,
                    'ruta' => $documento->ruta,
                    'url' => $documento->url,
                    'created_at' => $documento->created_at ? $documento->created_at->toIso8601String() : null,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error al crear documento del trámite: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function eliminar(int $id): array
    {
        try {
            $documento = TramiteAduanaDocumento::find($id);
            if (!$documento) {
                return ['success' => false, 'error' => 'Documento no encontrado'];
            }

            if (Storage::disk('public')->exists($documento->ruta)) {
                Storage::disk('public')->delete($documento->ruta);
            }

            $documento->delete();

            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('Error al eliminar documento del trámite: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function descargar(int $id): array
    {
        try {
            $documento = TramiteAduanaDocumento::find($id);
            if (!$documento) {
                return ['success' => false, 'error' => 'Documento no encontrado'];
            }

            $filePath = storage_path('app/public/' . $documento->ruta);
            if (!file_exists($filePath)) {
                return ['success' => false, 'error' => 'Archivo no encontrado en el servidor'];
            }

            return [
                'success' => true,
                'filePath' => $filePath,
                'nombre_original' => $documento->nombre_original,
            ];
        } catch (\Exception $e) {
            Log::error('Error al descargar documento del trámite: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Listar categorías (carpetas) de un trámite. Siempre incluye las 3 por defecto si el trámite existe.
     */
    public function listarCategorias(int $idTramite): array
    {
        try {
            $tramite = ConsolidadoCotizacionAduanaTramite::find($idTramite);
            if (!$tramite) {
                return ['success' => false, 'data' => [], 'error' => 'Trámite no encontrado'];
            }

            $categorias = TramiteAduanaCategoria::where('id_tramite', $idTramite)
                ->orderBy('nombre')
                ->get();

            $data = $categorias->map(function ($c) {
                return [
                    'id' => $c->id,
                    'id_tramite' => $c->id_tramite,
                    'nombre' => $c->nombre,
                ];
            })->all();

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            Log::error('Error al listar categorías del trámite: ' . $e->getMessage());
            return ['success' => false, 'data' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Crear una categoría (carpeta) para un trámite. firstOrCreate por (id_tramite, nombre).
     */
    public function crearCategoria(Request $request, int $idTramite): array
    {
        $request->validate(['nombre' => 'required|string|max:255']);

        try {
            $tramite = ConsolidadoCotizacionAduanaTramite::find($idTramite);
            if (!$tramite) {
                return ['success' => false, 'error' => 'Trámite no encontrado'];
            }

            $categoria = TramiteAduanaCategoria::firstOrCreate(
                [
                    'id_tramite' => $idTramite,
                    'nombre' => $request->nombre,
                ]
            );

            return [
                'success' => true,
                'data' => [
                    'id' => $categoria->id,
                    'id_tramite' => $categoria->id_tramite,
                    'nombre' => $categoria->nombre,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error al crear categoría del trámite: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Nivel del estado para no degradar: solo se actualiza si el nuevo nivel es mayor.
     * PENDIENTE/RECHAZADO < SD/PAGADO < EN_TRAMITE < COMPLETADO
     */
    private function nivelEstado($estado)
    {
        $niveles = [
            'PENDIENTE' => 0,
            'RECHAZADO' => 0,
            'SD' => 1,
            'PAGADO' => 1,
            'EN_TRAMITE' => 2,
            'COMPLETADO' => 3,
        ];

        return isset($niveles[$estado]) ? $niveles[$estado] : 0;
    }

    /**
     * Actualiza estado y fechas del trámite según la categoría del documento subido.
     * - No se degrada: si ya está EN_TRAMITE y se sube algo que sería SD, se mantiene EN_TRAMITE.
     * - Documento resolutivo → COMPLETADO + f_termino = hoy
     * - CPB de tramite → EN_TRAMITE (solo si nivel actual es menor)
     * - Cualquier otro → SD (solo si nivel actual es menor)
     * - f_inicio: se establece cuando se sube cualquier archivo (si aún es null).
     */
    private function actualizarEstadoTramitePorCategoria(ConsolidadoCotizacionAduanaTramite $tramite, string $nombreCategoria): void
    {
        $nombreCategoria = trim($nombreCategoria);
        if ($nombreCategoria === '') {
            return;
        }

        $nuevoEstado = null;
        if ($nombreCategoria === 'Documento resolutivo') {
            $nuevoEstado = 'COMPLETADO';
        } elseif ($nombreCategoria === 'CPB de tramite') {
            $nuevoEstado = 'EN_TRAMITE';
        } else {
            $nuevoEstado = 'SD';
        }

        $actualizado = false;
        $nivelActual = $this->nivelEstado($tramite->estado ?? 'PENDIENTE');
        $nivelNuevo = $this->nivelEstado($nuevoEstado);

        if (in_array($nuevoEstado, ConsolidadoCotizacionAduanaTramite::ESTADOS, true) && $nivelNuevo > $nivelActual) {
            $tramite->estado = $nuevoEstado;
            $actualizado = true;
        }

        // f_inicio: al subir cualquier archivo, si aún no tiene fecha de inicio
        if ($tramite->f_inicio === null) {
            $tramite->f_inicio = now()->toDateString();
            $actualizado = true;
        }

        // f_termino: al subir el documento resolutivo
        if ($nombreCategoria === 'Documento resolutivo') {
            $tramite->f_termino = now()->toDateString();
            $actualizado = true;
        }

        if ($actualizado) {
            $tramite->save();
        }
    }
}
