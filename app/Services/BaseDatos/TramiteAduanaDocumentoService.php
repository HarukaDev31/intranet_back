<?php

namespace App\Services\BaseDatos;

use App\Models\CargaConsolidada\TramiteAduanaDocumento;
use App\Models\CargaConsolidada\TramiteAduanaCategoria;
use App\Models\CargaConsolidada\TramiteAduanaPago;
use App\Models\CargaConsolidada\PagoPermisoDerechoTramite;
use App\Models\CargaConsolidada\PagoPermisoTramite;
use App\Models\CargaConsolidada\ConsolidadoCotizacionAduanaTramite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TramiteAduanaDocumentoService
{
    /** Secciones válidas */
    const SECCIONES = ['documentos_tramite', 'fotos', 'pago_servicio', 'seguimiento'];

    public function listarPorTramite(int $idTramite): array
    {
        try {
            $tramite = ConsolidadoCotizacionAduanaTramite::with([
                'consolidado', 'entidad', 'tiposPermiso', 'cliente', 'cotizacion'
            ])->find($idTramite);

            if (!$tramite) {
                return ['success' => false, 'error' => 'Trámite no encontrado'];
            }

            // Asegurar categorías para los tipos actuales del trámite
            $tipoIds = $tramite->tiposPermiso->pluck('id')->all();
            TramiteAduanaCategoria::asegurarCategoriasParaTramite($idTramite, $tipoIds);

            $categorias = TramiteAduanaCategoria::where('id_tramite', $idTramite)
                ->orderBy('seccion')
                ->orderBy('id_tipo_permiso')
                ->orderBy('nombre')
                ->get();
            $catSeguimiento = $categorias->firstWhere('nombre', TramiteAduanaCategoria::NOMBRE_SEGUIMIENTO_COMPARTIDO);
            $idCategoriaRH = $catSeguimiento ? $catSeguimiento->id : null;
            $categoriasPorTipo = $categorias->groupBy('id_tipo_permiso'); // null = compartidas

            $documentos = TramiteAduanaDocumento::where('id_tramite', $idTramite)
                ->orderBy('seccion')
                ->orderBy('created_at', 'asc')
                ->get();

            $mapDoc = function ($doc) {
                return [
                    'id'               => $doc->id,
                    'id_tramite'       => $doc->id_tramite,
                    'id_tipo_permiso'  => $doc->id_tipo_permiso,
                    'seccion'          => $doc->seccion ?? 'documentos_tramite',
                    'id_categoria'     => $doc->id_categoria,
                    'nombre_documento' => $doc->nombre_documento,
                    'extension'        => $doc->extension,
                    'peso'             => $doc->peso,
                    'nombre_original'  => $doc->nombre_original,
                    'ruta'             => $doc->ruta,
                    'url'              => $doc->url,
                    'created_at'       => $doc->created_at ? $doc->created_at->toIso8601String() : null,
                ];
            };

            $categoriaIdToTipo = $categorias->keyBy('id')->map(function ($c) { return $c->id_tipo_permiso; })->all();
            $docsSeguimiento = $documentos->filter(function ($d) { return ($d->seccion ?? '') === 'seguimiento'; });
            $seguimientoCompartido = $docsSeguimiento->filter(function ($d) use ($categoriaIdToTipo) { return ($categoriaIdToTipo[$d->id_categoria] ?? null) === null; })->values()->map($mapDoc)->all();
            $seguimientoPorTipo = $tramite->tiposPermiso->mapWithKeys(function ($tp) use ($docsSeguimiento, $categoriaIdToTipo, $mapDoc) {
                $docs = $docsSeguimiento->filter(function ($d) use ($categoriaIdToTipo, $tp) { return (int)($categoriaIdToTipo[$d->id_categoria] ?? 0) === (int)$tp->id; });
                return [$tp->id => $docs->values()->map($mapDoc)->all()];
            })->all();

            $pagoServicio = $documentos->filter(function ($d) { return is_null($d->id_tipo_permiso) && ($d->seccion ?? '') === 'pago_servicio'; })->values()->map($mapDoc)->all();

            $pagosRegistros = TramiteAduanaPago::where('id_tramite', $idTramite)->with('documento')->orderBy('id')->get();
            $docIdsConDatos = $pagosRegistros->pluck('id_documento')->all();
            $pagosConDatos = $pagosRegistros->map(function ($pago) use ($mapDoc) {
                $doc = $pago->documento;
                if (!$doc) return null;
                $estado = in_array($pago->estado_administracion ?? '', ['PENDIENTE', 'CONFIRMADO', 'OBSERVADO'], true)
                    ? $pago->estado_administracion
                    : 'PENDIENTE';
                return [
                    'document'            => $mapDoc($doc),
                    'monto'              => $pago->monto !== null ? (string) $pago->monto : null,
                    'fecha_pago'         => $pago->fecha_pago ? $pago->fecha_pago->format('Y-m-d') : null,
                    'banco'              => $pago->observacion ?: null,
                    'estado_verificacion' => $estado,
                    'estado'             => $estado,
                    'status'             => $estado,
                ];
            })->filter()->values()->all();
            foreach ($pagoServicio as $doc) {
                if (!in_array($doc['id'], $docIdsConDatos, true)) {
                    $pagosConDatos[] = [
                        'document'            => $doc,
                        'monto'               => null,
                        'fecha_pago'          => null,
                        'banco'               => null,
                        'estado_verificacion' => 'PENDIENTE',
                        'estado'              => 'PENDIENTE',
                        'status'              => 'PENDIENTE',
                    ];
                }
            }

            $tiposPermisoSections = $tramite->tiposPermiso->map(function ($tp) use ($documentos, $mapDoc, $seguimientoPorTipo) {
                $docsPermiso = $documentos->filter(function ($d) use ($tp) { return (int)$d->id_tipo_permiso === (int)$tp->id; });
                $fCaducidad = $tp->pivot->f_caducidad ?? null;
                return [
                    'id_tipo_permiso'     => $tp->id,
                    'nombre'              => $tp->nombre,
                    'estado'              => $tp->pivot->estado ?? 'PENDIENTE',
                    'f_caducidad'         => $fCaducidad ? (\Carbon\Carbon::parse($fCaducidad)->format('Y-m-d')) : null,
                    'documentos_tramite'  => $docsPermiso->filter(function ($d) { return ($d->seccion ?? 'documentos_tramite') === 'documentos_tramite'; })->values()->map($mapDoc)->all(),
                    'fotos'               => $docsPermiso->filter(function ($d) { return ($d->seccion ?? '') === 'fotos'; })->values()->map($mapDoc)->all(),
                    'seguimiento'         => $seguimientoPorTipo[$tp->id] ?? [],
                ];
            })->all();

            $categoriasPayload = $categorias->map(function ($c) {
                return [
                    'id'              => $c->id,
                    'id_tramite'      => $c->id_tramite,
                    'nombre'          => $c->nombre,
                    'seccion'         => $c->seccion ?? 'documentos_tramite',
                    'id_tipo_permiso' => $c->id_tipo_permiso,
                ];
            })->all();

            $clienteNombre = null;
            if ($tramite->cliente) {
                $clienteNombre = $tramite->cliente->nombre ?? $tramite->cliente->documento ?? null;
            } elseif ($tramite->cotizacion) {
                $clienteNombre = $tramite->cotizacion->nombre ?? $tramite->cotizacion->documento ?? null;
            }
            $carga = $tramite->consolidado ? ($tramite->consolidado->carga ?? null) : null;
            if ($carga !== null && $tramite->consolidado && $tramite->consolidado->f_inicio) {
                $anio = \Carbon\Carbon::parse($tramite->consolidado->f_inicio)->format('Y');
                $carga = '#' . $carga . ' - ' . $anio;
            } elseif ($carga !== null) {
                $carga = '#' . $carga;
            }

            $comprobantesDerecho = PagoPermisoDerechoTramite::where('id_tramite', $idTramite)->orderBy('id_tipo_permiso')->orderBy('id')->get();
            $mapComprobanteDerecho = function ($c) {
                return [
                    'id'              => $c->id,
                    'id_tipo_permiso' => $c->id_tipo_permiso,
                    'url'             => $c->url,
                    'nombre_original'  => $c->nombre_original,
                    'extension'       => $c->extension,
                    'peso'            => $c->peso,
                    'monto'           => $c->monto !== null ? (string) $c->monto : null,
                    'banco'           => $c->banco,
                    'fecha_cierre'    => $c->fecha_cierre ? $c->fecha_cierre->format('Y-m-d') : null,
                ];
            };
            $comprobantesDerechoPorTipo = [];
            foreach ($comprobantesDerecho as $c) {
                $idTipo = (int) $c->id_tipo_permiso;
                if (!isset($comprobantesDerechoPorTipo[$idTipo])) {
                    $comprobantesDerechoPorTipo[$idTipo] = [];
                }
                $comprobantesDerechoPorTipo[$idTipo][] = $mapComprobanteDerecho($c);
            }

            $comprobantesTramitador = PagoPermisoTramite::where('id_tramite', $idTramite)->orderBy('id')->get()->map(function ($c) {
                return [
                    'id'             => $c->id,
                    'url'            => $c->url,
                    'nombre_original' => $c->nombre_original,
                    'extension'      => $c->extension,
                    'peso'           => $c->peso,
                    'monto'          => $c->monto !== null ? (string) $c->monto : null,
                    'banco'          => $c->banco,
                    'fecha_cierre'   => $c->fecha_cierre ? $c->fecha_cierre->format('Y-m-d') : null,
                ];
            })->all();

            return [
                'success'              => true,
                'tramite'              => [
                    'id'            => $tramite->id,
                    'estado'        => $tramite->estado,
                    'entidad'       => $tramite->entidad ? $tramite->entidad->nombre : null,
                    'cliente'       => $clienteNombre,
                    'tipos_permiso' => $tramite->tiposPermiso->pluck('nombre')->all(),
                    'consolidado'   => $carga,
                    'f_caducidad'   => $tramite->f_caducidad ? $tramite->f_caducidad->format('Y-m-d') : null,
                ],
                'categorias'                   => $categoriasPayload,
                'tipos_permiso_sections'      => $tiposPermisoSections,
                'pago_servicio'                => $pagoServicio,
                'pagos_con_datos'              => $pagosConDatos,
                'seguimiento_compartido'       => $seguimientoCompartido,
                'seguimiento_por_tipo'         => $seguimientoPorTipo,
                'comprobantes_derecho_por_tipo' => $comprobantesDerechoPorTipo,
                'comprobantes_tramitador'      => $comprobantesTramitador,
                'data'                         => $documentos->map($mapDoc)->all(),
            ];
        } catch (\Exception $e) {
            Log::error('Error al listar documentos del trámite: ' . $e->getMessage());
            return ['success' => false, 'data' => [], 'error' => $e->getMessage()];
        }
    }

    public function crear(Request $request, int $idTramite): array
    {
        $request->validate([
            'nombre_documento' => 'required|string|max:255',
            'archivo'          => 'required|file|max:204800',
            'seccion'          => 'nullable|string|in:documentos_tramite,fotos,pago_servicio,seguimiento',
            'id_tipo_permiso'  => 'nullable|integer',
            // legacy
            'categoria'        => 'nullable|string|max:255',
            'id_categoria'     => 'sometimes|nullable|integer', // -1 = crear nueva categoría con el nombre en categoria
        ]);

        try {
            $tramite = ConsolidadoCotizacionAduanaTramite::find($idTramite);
            if (!$tramite) {
                return ['success' => false, 'error' => 'Trámite no encontrado'];
            }

            $seccion       = $request->input('seccion', 'documentos_tramite');
            $idTipoPermiso = $request->input('id_tipo_permiso');
            $idCategoria   = $request->input('id_categoria');
            $nombreCategoria = $request->input('categoria', $seccion);
            $crearCategoria = ($idCategoria === null || (int) $idCategoria === -1) && $nombreCategoria;

            if ($idCategoria && (int) $idCategoria > 0) {
                $cat = TramiteAduanaCategoria::find($idCategoria);
                if ($cat && $cat->id_tramite == $idTramite) {
                    if ($seccion === 'seguimiento' && $cat->id_tipo_permiso !== null) {
                        $idTipoPermiso = $cat->id_tipo_permiso;
                    }
                    if ($seccion === 'documentos_tramite' && $cat->id_tipo_permiso !== null) {
                        $idTipoPermiso = $cat->id_tipo_permiso;
                    }
                }
            }
            if ($crearCategoria) {
                $cat = TramiteAduanaCategoria::firstOrCreate([
                    'id_tramite'      => $idTramite,
                    'nombre'          => $nombreCategoria,
                    'seccion'         => $seccion,
                    'id_tipo_permiso' => $idTipoPermiso,
                ]);
                $idCategoria = $cat->id;
            }

            $archivo  = $request->file('archivo');
            $filename = time() . '_' . uniqid() . '.' . $archivo->getClientOriginalExtension();
            $path     = $archivo->storeAs('tramites/documentos', $filename, 'public');

            $documento = TramiteAduanaDocumento::create([
                'id_tramite'       => $idTramite,
                'id_categoria'     => $idCategoria,
                'id_tipo_permiso'  => $idTipoPermiso ?: null,
                'seccion'          => $seccion,
                'nombre_documento' => $request->nombre_documento,
                'extension'        => $archivo->getClientOriginalExtension(),
                'peso'             => $archivo->getSize(),
                'nombre_original'  => $archivo->getClientOriginalName(),
                'ruta'             => $path,
            ]);

            return [
                'success' => true,
                'data'    => [
                    'id'               => $documento->id,
                    'id_tramite'       => $documento->id_tramite,
                    'id_tipo_permiso'  => $documento->id_tipo_permiso,
                    'seccion'          => $documento->seccion,
                    'id_categoria'     => $documento->id_categoria,
                    'nombre_documento' => $documento->nombre_documento,
                    'extension'        => $documento->extension,
                    'peso'             => $documento->peso,
                    'nombre_original'  => $documento->nombre_original,
                    'ruta'             => $documento->ruta,
                    'url'              => $documento->url,
                    'created_at'       => $documento->created_at ? $documento->created_at->toIso8601String() : null,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error al crear documento del trámite: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Crea múltiples documentos en una sola petición.
     * FormData: id_tipo_permiso[] (int), archivo[] (file), seccion[] (string), id_categoria[] (int, -1 = crear nueva), categoria[] (string nombre).
     */
    public function crearBatch(Request $request, int $idTramite): array
    {
        $tramite = ConsolidadoCotizacionAduanaTramite::find($idTramite);
        if (!$tramite) {
            return ['success' => false, 'error' => 'Trámite no encontrado'];
        }

        $idTipoPermisos = $request->input('id_tipo_permiso', []);
        $archivos        = $request->file('archivo', []);
        $secciones       = $request->input('seccion', []);
        $idCategorias    = $request->input('id_categoria', []);
        $categorias      = $request->input('categoria', []);

        if (!is_array($archivos)) {
            $archivos = $archivos ? [$archivos] : [];
        }
        $n = count($archivos);
        if ($n === 0) {
            return ['success' => true, 'data' => []];
        }

        $idTipoPermisos = is_array($idTipoPermisos) ? array_values($idTipoPermisos) : [];
        $secciones      = is_array($secciones) ? array_values($secciones) : [];
        $idCategorias   = is_array($idCategorias) ? array_values($idCategorias) : [];
        $categorias     = is_array($categorias) ? array_values($categorias) : [];

        $created = [];
        try {
            for ($i = 0; $i < $n; $i++) {
                $archivo = $archivos[$i] ?? null;
                if (!$archivo || !$archivo->isValid()) {
                    continue;
                }
                $idTipoPermiso = (int) ($idTipoPermisos[$i] ?? 0);
                $seccion      = $secciones[$i] ?? 'documentos_tramite';
                if (!in_array($seccion, self::SECCIONES, true)) {
                    $seccion = 'documentos_tramite';
                }
                $idCategoria   = isset($idCategorias[$i]) ? (int) $idCategorias[$i] : null;
                $nombreCategoria = $categorias[$i] ?? $seccion;
                $crearCategoria = ($idCategoria === null || $idCategoria === -1) && $nombreCategoria;

                if ($idCategoria > 0) {
                    $cat = TramiteAduanaCategoria::find($idCategoria);
                    if ($cat && $cat->id_tramite == $idTramite) {
                        if ($seccion === 'seguimiento' && $cat->id_tipo_permiso !== null) {
                            $idTipoPermiso = $cat->id_tipo_permiso;
                        }
                        if ($seccion === 'documentos_tramite' && $cat->id_tipo_permiso !== null) {
                            $idTipoPermiso = $cat->id_tipo_permiso;
                        }
                    }
                }
                if ($crearCategoria) {
                    $cat = TramiteAduanaCategoria::firstOrCreate([
                        'id_tramite'      => $idTramite,
                        'nombre'          => $nombreCategoria,
                        'seccion'         => $seccion,
                        'id_tipo_permiso' => $idTipoPermiso ?: null,
                    ]);
                    $idCategoria = $cat->id;
                }

                $filename = time() . '_' . uniqid() . '_' . $i . '.' . $archivo->getClientOriginalExtension();
                $path     = $archivo->storeAs('tramites/documentos', $filename, 'public');

                $documento = TramiteAduanaDocumento::create([
                    'id_tramite'       => $idTramite,
                    'id_categoria'     => $idCategoria ?? null,
                    'id_tipo_permiso'  => $idTipoPermiso ?: null,
                    'seccion'          => $seccion,
                    'nombre_documento' => $archivo->getClientOriginalName(),
                    'extension'        => $archivo->getClientOriginalExtension(),
                    'peso'             => $archivo->getSize(),
                    'nombre_original'  => $archivo->getClientOriginalName(),
                    'ruta'             => $path,
                ]);

                $created[] = [
                    'id'               => $documento->id,
                    'id_tramite'       => $documento->id_tramite,
                    'id_tipo_permiso'  => $documento->id_tipo_permiso,
                    'seccion'          => $documento->seccion,
                    'id_categoria'     => $documento->id_categoria,
                    'nombre_documento' => $documento->nombre_documento,
                    'extension'        => $documento->extension,
                    'peso'             => $documento->peso,
                    'nombre_original'  => $documento->nombre_original,
                    'ruta'             => $documento->ruta,
                    'url'              => $documento->url,
                    'created_at'       => $documento->created_at ? $documento->created_at->toIso8601String() : null,
                ];
            }
            return ['success' => true, 'data' => $created];
        } catch (\Exception $e) {
            Log::error('Error al crear batch de documentos: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'data' => []];
        }
    }

    /**
     * Guardar todo en una petición: crear documentos (batch) + guardar tipos permiso + f_caducidad por tipo (pivot).
     * FormData: id_tipo_permiso[], archivo[], seccion[], id_categoria[], categoria[] (opcional),
     *           guardar_tipos (JSON: array de { id_tipo_permiso, documentos_tramite_ids, fotos_ids, seguimiento_ids, f_caducidad? }).
     */
    public function guardarTodo(Request $request, int $idTramite): array
    {
        $tramite = ConsolidadoCotizacionAduanaTramite::with('tiposPermiso')->find($idTramite);
        if (!$tramite) {
            return ['success' => false, 'error' => 'Trámite no encontrado', 'data' => []];
        }

        $guardarTiposJson = $request->input('guardar_tipos');
        if ($guardarTiposJson === null || $guardarTiposJson === '') {
            return ['success' => false, 'error' => 'guardar_tipos es requerido', 'data' => []];
        }

        $guardarTipos = json_decode($guardarTiposJson, true);
        if (!is_array($guardarTipos)) {
            return ['success' => false, 'error' => 'guardar_tipos debe ser un JSON válido', 'data' => []];
        }

        // 1) Crear documentos (misma lógica que crearBatch)
        $created = [];
        $archivos = $request->file('archivo', []);
        if (!is_array($archivos)) {
            $archivos = $archivos ? [$archivos] : [];
        }
        $n = count($archivos);

        if ($n > 0) {
            $idTipoPermisos = is_array($request->input('id_tipo_permiso', [])) ? array_values($request->input('id_tipo_permiso', [])) : [];
            $secciones      = is_array($request->input('seccion', [])) ? array_values($request->input('seccion', [])) : [];
            $idCategorias   = is_array($request->input('id_categoria', [])) ? array_values($request->input('id_categoria', [])) : [];
            $categorias     = is_array($request->input('categoria', [])) ? array_values($request->input('categoria', [])) : [];

            for ($i = 0; $i < $n; $i++) {
                $archivo = $archivos[$i] ?? null;
                if (!$archivo || !$archivo->isValid()) {
                    continue;
                }
                $idTipoPermiso = (int) ($idTipoPermisos[$i] ?? 0);
                $seccion      = $secciones[$i] ?? 'documentos_tramite';
                if (!in_array($seccion, self::SECCIONES, true)) {
                    $seccion = 'documentos_tramite';
                }
                $idCategoria   = isset($idCategorias[$i]) ? (int) $idCategorias[$i] : null;
                $nombreCategoria = $categorias[$i] ?? $seccion;
                $crearCategoria = ($idCategoria === null || $idCategoria === -1) && $nombreCategoria;

                if ($idCategoria > 0) {
                    $cat = TramiteAduanaCategoria::find($idCategoria);
                    if ($cat && $cat->id_tramite == $idTramite) {
                        if ($seccion === 'seguimiento' && $cat->id_tipo_permiso !== null) {
                            $idTipoPermiso = $cat->id_tipo_permiso;
                        }
                        if ($seccion === 'documentos_tramite' && $cat->id_tipo_permiso !== null) {
                            $idTipoPermiso = $cat->id_tipo_permiso;
                        }
                    }
                }
                if ($crearCategoria) {
                    $cat = TramiteAduanaCategoria::firstOrCreate([
                        'id_tramite'      => $idTramite,
                        'nombre'          => $nombreCategoria,
                        'seccion'         => $seccion,
                        'id_tipo_permiso' => $idTipoPermiso ?: null,
                    ]);
                    $idCategoria = $cat->id;
                }

                $filename = time() . '_' . uniqid() . '_' . $i . '.' . $archivo->getClientOriginalExtension();
                $path     = $archivo->storeAs('tramites/documentos', $filename, 'public');

                $documento = TramiteAduanaDocumento::create([
                    'id_tramite'       => $idTramite,
                    'id_categoria'     => $idCategoria ?? null,
                    'id_tipo_permiso'  => $idTipoPermiso ?: null,
                    'seccion'          => $seccion,
                    'nombre_documento' => $archivo->getClientOriginalName(),
                    'extension'        => $archivo->getClientOriginalExtension(),
                    'peso'             => $archivo->getSize(),
                    'nombre_original'  => $archivo->getClientOriginalName(),
                    'ruta'             => $path,
                ]);

                $created[] = [
                    'id'               => $documento->id,
                    'id_tramite'       => $documento->id_tramite,
                    'id_tipo_permiso'  => $documento->id_tipo_permiso,
                    'seccion'          => $documento->seccion,
                    'id_categoria'     => $documento->id_categoria,
                    'categoria'        => $nombreCategoria,
                    'nombre_documento' => $documento->nombre_documento,
                    'extension'        => $documento->extension,
                    'peso'             => $documento->peso,
                    'nombre_original'  => $documento->nombre_original,
                    'ruta'             => $documento->ruta,
                    'url'              => $documento->url,
                    'created_at'       => $documento->created_at ? $documento->created_at->toIso8601String() : null,
                ];

                // 2) Añadir id del documento creado al guardar_tipos correspondiente
                foreach ($guardarTipos as &$item) {
                    $tipoId = (int) ($item['id_tipo_permiso'] ?? 0);
                    if ($tipoId !== (int) $documento->id_tipo_permiso) {
                        continue;
                    }
                    $item['documentos_tramite_ids'] = $item['documentos_tramite_ids'] ?? [];
                    $item['fotos_ids'] = $item['fotos_ids'] ?? [];
                    $item['seguimiento_ids'] = $item['seguimiento_ids'] ?? [];
                    if ($seccion === 'documentos_tramite') {
                        $item['documentos_tramite_ids'][] = $documento->id;
                    } elseif ($seccion === 'fotos') {
                        $item['fotos_ids'][] = $documento->id;
                    } elseif ($seccion === 'seguimiento') {
                        $item['seguimiento_ids'][] = $documento->id;
                    }
                    break;
                }
                unset($item);

                // 2b) Actualizar f_inicio / f_termino del tipo_permiso según categoría (todo en guardar-todo)
                if ($documento->id_tipo_permiso) {
                    $this->aplicarFechasPorCategoria($idTramite, (int) $documento->id_tipo_permiso, $nombreCategoria);
                }
                // 2c) Actualizar estado del tipo_permiso según reglas: SD / En trámite / Completado (por categoría)
                if ($documento->id_tipo_permiso) {
                    $this->aplicarEstadoPorCategoria($tramite, (int) $documento->id_tipo_permiso, $nombreCategoria, $seccion);
                }
            }
        }

        // 3) Guardar cada tipo permiso (sincronizar ids por sección) y actualizar f_caducidad por tipo (pivot)
        foreach ($guardarTipos as $item) {
            $idTipoPermiso = (int) ($item['id_tipo_permiso'] ?? 0);
            $docIds = array_map('intval', array_filter($item['documentos_tramite_ids'] ?? [], function ($id) { return is_numeric($id); }));
            $fotoIds = array_map('intval', array_filter($item['fotos_ids'] ?? [], function ($id) { return is_numeric($id); }));
            $segIds = array_map('intval', array_filter($item['seguimiento_ids'] ?? [], function ($id) { return is_numeric($id); }));

            $result = $this->guardarTipoPermiso($idTramite, $idTipoPermiso, $docIds, $fotoIds, $segIds);
            if (!$result['success']) {
                Log::warning('guardarTodo: fallo guardarTipoPermiso tipo ' . $idTipoPermiso . ': ' . ($result['error'] ?? ''));
                // Continuamos con el resto
            }

            // f_caducidad por tipo_permiso (pivot)
            $fCaducidad = $item['f_caducidad'] ?? null;
            if ($fCaducidad !== null && $fCaducidad !== '') {
                $tramite->tiposPermiso()->updateExistingPivot($idTipoPermiso, ['f_caducidad' => $fCaducidad]);
            }
        }

        // 4) Pagos (vouchers): acepta array pago_voucher[], pago_monto[], etc.; crea un documento y un registro por cada uno
        $vouchers = $request->file('pago_voucher');
        if (!is_array($vouchers)) {
            $vouchers = $request->file('pago_voucher') ? [$request->file('pago_voucher')] : [];
        }
        $montos = $request->input('pago_monto', []);
        $bancos = $request->input('pago_banco', []);
        $fechas = $request->input('pago_fecha_cierre', []);
        if (!is_array($montos)) {
            $montos = $montos !== null && $montos !== '' ? [$montos] : [];
        }
        if (!is_array($bancos)) {
            $bancos = $bancos !== null && $bancos !== '' ? [$bancos] : [];
        }
        if (!is_array($fechas)) {
            $fechas = $fechas !== null && $fechas !== '' ? [$fechas] : [];
        }
        $primerTipoPermiso = $tramite->tiposPermiso->first();
        $idTipoPermisoPago = $primerTipoPermiso ? (int) $primerTipoPermiso->id : null;
        $categoriaPago = null;
        $n = count($vouchers);
        for ($i = 0; $i < $n; $i++) {
            $voucher = $vouchers[$i] ?? null;
            if (!$voucher || !$voucher->isValid()) {
                continue;
            }
            $montoVal = isset($montos[$i]) ? trim((string) $montos[$i]) : '';
            if ($montoVal === '') {
                continue;
            }
            if ($categoriaPago === null) {
                $categoriaPago = TramiteAduanaCategoria::firstOrCreate(
                    [
                        'id_tramite'      => $idTramite,
                        'nombre'          => TramiteAduanaCategoria::NOMBRE_PAGO_SERVICIO,
                        'seccion'         => TramiteAduanaCategoria::SECCION_PAGO_SERVICIO,
                        'id_tipo_permiso' => null,
                    ],
                    ['id_tramite' => $idTramite, 'nombre' => TramiteAduanaCategoria::NOMBRE_PAGO_SERVICIO, 'seccion' => TramiteAduanaCategoria::SECCION_PAGO_SERVICIO, 'id_tipo_permiso' => null]
                );
            }
            $filename = time() . '_' . uniqid() . '_pago_' . $i . '.' . $voucher->getClientOriginalExtension();
            $path = $voucher->storeAs('tramites/documentos', $filename, 'public');
            $documentoPago = TramiteAduanaDocumento::create([
                'id_tramite'       => $idTramite,
                'id_categoria'     => $categoriaPago->id,
                'id_tipo_permiso'  => null,
                'seccion'          => 'pago_servicio',
                'nombre_documento' => $voucher->getClientOriginalName(),
                'extension'        => $voucher->getClientOriginalExtension(),
                'peso'             => $voucher->getSize(),
                'nombre_original'  => $voucher->getClientOriginalName(),
                'ruta'             => $path,
            ]);
            $created[] = [
                'id'               => $documentoPago->id,
                'id_tramite'       => $documentoPago->id_tramite,
                'id_tipo_permiso'  => $documentoPago->id_tipo_permiso,
                'seccion'          => $documentoPago->seccion,
                'id_categoria'     => $documentoPago->id_categoria,
                'categoria'        => TramiteAduanaCategoria::NOMBRE_PAGO_SERVICIO,
                'nombre_documento' => $documentoPago->nombre_documento,
                'extension'        => $documentoPago->extension,
                'peso'             => $documentoPago->peso,
                'nombre_original'  => $documentoPago->nombre_original,
                'ruta'             => $documentoPago->ruta,
                'url'              => $documentoPago->url,
                'created_at'       => $documentoPago->created_at ? $documentoPago->created_at->toIso8601String() : null,
            ];
            $fechaPago = isset($fechas[$i]) && trim((string) $fechas[$i]) !== '' ? $fechas[$i] : now()->format('Y-m-d');
            $observacion = isset($bancos[$i]) ? trim((string) $bancos[$i]) : '';
            if ($idTipoPermisoPago !== null) {
                TramiteAduanaPago::create([
                    'id_tramite'      => $idTramite,
                    'id_tipo_permiso' => $idTipoPermisoPago,
                    'id_documento'   => $documentoPago->id,
                    'monto'          => $montoVal,
                    'fecha_pago'     => $fechaPago,
                    'observacion'    => $observacion,
                ]);
            }
        }

        // 5) Actualizar datos de pagos ya subidos (monto, banco, fecha)
        $pagoActualizacionesJson = $request->input('pago_actualizaciones');
        if ($pagoActualizacionesJson !== null && $pagoActualizacionesJson !== '') {
            $pagoActualizaciones = json_decode($pagoActualizacionesJson, true);
            if (is_array($pagoActualizaciones)) {
                $primerTipo = $tramite->tiposPermiso->first();
                $idTipoPermisoPago = $primerTipo ? (int) $primerTipo->id : null;
                foreach ($pagoActualizaciones as $up) {
                    $idDoc = isset($up['id_documento']) ? (int) $up['id_documento'] : 0;
                    if ($idDoc <= 0) {
                        continue;
                    }
                    $monto = isset($up['monto']) ? (trim((string) $up['monto']) ?: null) : null;
                    $banco = isset($up['banco']) ? (trim((string) $up['banco']) ?: null) : null;
                    $fechaPago = isset($up['fecha_cierre']) && trim((string) $up['fecha_cierre']) !== '' ? $up['fecha_cierre'] : null;
                    $pago = TramiteAduanaPago::where('id_tramite', $idTramite)->where('id_documento', $idDoc)->first();
                    if ($pago) {
                        $pago->monto = $monto;
                        $pago->observacion = $banco;
                        $pago->fecha_pago = $fechaPago;
                        $pago->save();
                    } elseif ($idTipoPermisoPago !== null) {
                        TramiteAduanaPago::updateOrCreate(
                            [
                                'id_tramite'      => $idTramite,
                                'id_tipo_permiso' => $idTipoPermisoPago,
                            ],
                            [
                                'id_documento' => $idDoc,
                                'monto'        => $monto,
                                'observacion'  => $banco,
                                'fecha_pago'   => $fechaPago,
                            ]
                        );
                    }
                }
            }
        }

        return ['success' => true, 'data' => $created];
    }

    /**
     * Si la categoría es Expediente/CPB → actualiza f_inicio; Decreto o Hoja resumen → f_termino. Días lo calcula el servicio de trámite.
     */
    private function aplicarFechasPorCategoria(int $idTramite, int $idTipoPermiso, string $nombreCategoria): void
    {
        $n = strtolower(trim($nombreCategoria));
        $hoy = now()->format('Y-m-d');
        $tramiteService = app(TramiteAduanaService::class);

        if (((strpos($n, 'expediente') !== false) && (strpos($n, 'cpb') !== false)) || $n === 'expediente cpb' || $n === 'expediente o cpb') {
            $tramiteService->actualizarFechasTipoPermiso($idTramite, $idTipoPermiso, $hoy, null);
        } elseif (strpos($n, 'decreto') !== false || strpos($n, 'hoja resumen') !== false) {
            $tramiteService->actualizarFechasTipoPermiso($idTramite, $idTipoPermiso, null, $hoy);
        }
    }

    /** Jerarquía de estados automáticos: solo se asciende, no se retrocede al subir. Al borrar un archivo se recalcula. */
    private const ESTADO_NIVEL = [
        'PENDIENTE'  => 0,
        'SD'         => 1,
        'PAGADO'     => 1,
        'EN_TRAMITE' => 2,
        'COMPLETADO' => 3,
        'RECHAZADO'  => -1, // manual, no se sobrescribe
    ];

    /**
     * Cambios de estado por tipo_permiso según subida de documentos (solo ascender, jerarquía):
     * - Rechazado: solo manual (no se sobrescribe).
     * - PENDIENTE < SD < EN_TRAMITE < COMPLETADO: si ya está COMPLETADO, subir otro doc no retrocede.
     * - Cualquier "documentos para tramite" → SD.
     * - Expediente o CPB → EN_TRAMITE.
     * - Decreto resolutivo u hoja resumen → COMPLETADO.
     */
    private function aplicarEstadoPorCategoria(
        ConsolidadoCotizacionAduanaTramite $tramite,
        int $idTipoPermiso,
        string $nombreCategoria,
        string $seccion
    ): void {
        $pivot = $tramite->tiposPermiso->firstWhere('id', $idTipoPermiso);
        if (!$pivot) {
            return;
        }
        $estadoActual = $pivot->pivot->estado ?? 'PENDIENTE';
        if ($estadoActual === 'RECHAZADO') {
            return;
        }

        $nivelActual = self::ESTADO_NIVEL[$estadoActual] ?? 0;
        $n = strtolower(trim($nombreCategoria));

        $nuevoEstado = null;
        if (strpos($n, 'decreto') !== false || strpos($n, 'hoja resumen') !== false) {
            $nuevoEstado = 'COMPLETADO';
        } elseif (((strpos($n, 'expediente') !== false) && (strpos($n, 'cpb') !== false)) || $n === 'expediente cpb' || $n === 'expediente o cpb') {
            $nuevoEstado = 'EN_TRAMITE';
        } elseif ($seccion === 'documentos_tramite') {
            $nuevoEstado = 'SD';
        }

        if ($nuevoEstado !== null) {
            $nivelNuevo = self::ESTADO_NIVEL[$nuevoEstado] ?? 0;
            if ($nivelNuevo > $nivelActual) {
                app(TramiteAduanaService::class)->actualizarEstadoTipoPermiso($tramite->id, $idTipoPermiso, $nuevoEstado);
            }
        }
    }

    /**
     * Recalcula el estado del tipo_permiso según los documentos que quedan (tracking al borrar).
     * Si se borra el archivo que daba COMPLETADO/EN_TRAMITE/SD, vuelve al estado que corresponda.
     */
    private function recalcularEstadoTipoPermiso(int $idTramite, int $idTipoPermiso): void
    {
        $tramite = ConsolidadoCotizacionAduanaTramite::with('tiposPermiso')->find($idTramite);
        if (!$tramite) {
            return;
        }
        $pivot = $tramite->tiposPermiso->firstWhere('id', $idTipoPermiso);
        if (!$pivot || ($pivot->pivot->estado ?? '') === 'RECHAZADO') {
            return;
        }

        $documentos = TramiteAduanaDocumento::where('id_tramite', $idTramite)
            ->where('id_tipo_permiso', $idTipoPermiso)
            ->whereIn('seccion', ['documentos_tramite', 'seguimiento'])
            ->with('categoria')
            ->get();

        $tieneDecretoHoja = false;
        $tieneExpedienteCpb = false;
        $tieneDocumentosTramite = false;

        foreach ($documentos as $doc) {
            $nombreCat = ($doc->categoria !== null ? $doc->categoria->nombre : null) ?? '';
            $n = strtolower(trim($nombreCat));
            if (strpos($n, 'decreto') !== false || strpos($n, 'hoja resumen') !== false) {
                $tieneDecretoHoja = true;
            }
            if (((strpos($n, 'expediente') !== false) && (strpos($n, 'cpb') !== false)) || $n === 'expediente cpb' || $n === 'expediente o cpb') {
                $tieneExpedienteCpb = true;
            }
            if (($doc->seccion ?? '') === 'documentos_tramite') {
                $tieneDocumentosTramite = true;
            }
        }

        $nuevoEstado = 'PENDIENTE';
        if ($tieneDecretoHoja) {
            $nuevoEstado = 'COMPLETADO';
        } elseif ($tieneExpedienteCpb) {
            $nuevoEstado = 'EN_TRAMITE';
        } elseif ($tieneDocumentosTramite) {
            $nuevoEstado = 'SD';
        }

        $tramite->tiposPermiso()->updateExistingPivot($idTipoPermiso, ['estado' => $nuevoEstado]);
    }

    public function eliminar(int $id): array
    {
        try {
            $documento = TramiteAduanaDocumento::find($id);
            if (!$documento) {
                return ['success' => false, 'error' => 'Documento no encontrado'];
            }

            $idTramite = $documento->id_tramite;
            $idTipoPermiso = $documento->id_tipo_permiso;

            if (Storage::disk('public')->exists($documento->ruta)) {
                Storage::disk('public')->delete($documento->ruta);
            }

            $documento->delete();

            if ($idTipoPermiso !== null) {
                $this->recalcularEstadoTipoPermiso($idTramite, (int) $idTipoPermiso);
            }

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
                'success'         => true,
                'filePath'        => $filePath,
                'nombre_original' => $documento->nombre_original,
            ];
        } catch (\Exception $e) {
            Log::error('Error al descargar documento del trámite: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

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
                    'id'              => $c->id,
                    'id_tramite'      => $c->id_tramite,
                    'nombre'          => $c->nombre,
                    'seccion'         => $c->seccion ?? 'documentos_tramite',
                    'id_tipo_permiso' => $c->id_tipo_permiso,
                ];
            })->all();

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            Log::error('Error al listar categorías del trámite: ' . $e->getMessage());
            return ['success' => false, 'data' => [], 'error' => $e->getMessage()];
        }
    }

    public function crearCategoria(Request $request, int $idTramite): array
    {
        $request->validate([
            'nombre'          => 'required|string|max:255',
            'seccion'         => 'nullable|string|in:documentos_tramite,seguimiento',
            'id_tipo_permiso' => 'nullable|integer',
        ]);

        try {
            $tramite = ConsolidadoCotizacionAduanaTramite::find($idTramite);
            if (!$tramite) {
                return ['success' => false, 'error' => 'Trámite no encontrado'];
            }

            $seccion = $request->input('seccion', 'documentos_tramite');
            $idTipoPermiso = $request->input('id_tipo_permiso');

            $categoria = TramiteAduanaCategoria::firstOrCreate([
                'id_tramite'      => $idTramite,
                'nombre'          => $request->nombre,
                'seccion'         => $seccion,
                'id_tipo_permiso' => $idTipoPermiso,
            ]);

            return [
                'success' => true,
                'data'    => [
                    'id'              => $categoria->id,
                    'id_tramite'      => $categoria->id_tramite,
                    'nombre'          => $categoria->nombre,
                    'seccion'         => $categoria->seccion ?? 'documentos_tramite',
                    'id_tipo_permiso' => $categoria->id_tipo_permiso,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error al crear categoría del trámite: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * POST guardar-verificacion: actualiza estado_administracion de pagos de servicio y sube comprobantes (derecho por tipo, tramitador).
     * FormData: estados_pago_servicio (JSON), comprobante_derecho_{id_tipo_permiso} (file), pago_derecho_{id}_monto/banco/fecha_cierre, comprobante_tramitador (file), pago_tramitador_monto/banco/fecha_cierre.
     */
    public function guardarVerificacion(Request $request, int $idTramite): array
    {
        $tramite = ConsolidadoCotizacionAduanaTramite::with('tiposPermiso')->find($idTramite);
        if (!$tramite) {
            return ['success' => false, 'error' => 'Trámite no encontrado'];
        }

        $estadosJson = $request->input('estados_pago_servicio');
        if ($estadosJson !== null && $estadosJson !== '') {
            $estados = json_decode($estadosJson, true);
            if (is_array($estados)) {
                $primerTipo = $tramite->tiposPermiso->first();
                $idTipoPermisoDefault = $primerTipo ? (int) $primerTipo->id : null;
                foreach ($estados as $item) {
                    $idDoc = isset($item['id_documento']) ? (int) $item['id_documento'] : 0;
                    $estado = isset($item['estado']) && in_array($item['estado'], ['PENDIENTE', 'CONFIRMADO', 'OBSERVADO'], true)
                        ? $item['estado']
                        : 'PENDIENTE';
                    if ($idDoc <= 0) {
                        continue;
                    }
                    $pago = TramiteAduanaPago::where('id_tramite', $idTramite)->where('id_documento', $idDoc)->first();
                    if ($pago) {
                        $pago->estado_administracion = $estado;
                        $pago->save();
                    } elseif ($idTipoPermisoDefault !== null) {
                        TramiteAduanaPago::create([
                            'id_tramite'             => $idTramite,
                            'id_tipo_permiso'        => $idTipoPermisoDefault,
                            'id_documento'           => $idDoc,
                            'estado_administracion'  => $estado,
                        ]);
                    }
                }
            }
        }

        // Múltiples comprobantes por tipo: comprobante_derecho_{id} o comprobante_derecho_{id}_{idx}, pago_derecho_{id}_{idx}_monto/banco/fecha_cierre
        $allKeys = array_keys($request->all());
        foreach ($allKeys as $key) {
            if (strpos($key, 'comprobante_derecho_') !== 0) {
                continue;
            }
            $file = $request->file($key);
            if (!$file || !$file->isValid()) {
                continue;
            }
            $suffix = substr($key, strlen('comprobante_derecho_'));
            $parts = explode('_', $suffix, 2);
            $idTipoPermiso = (int) $parts[0];
            $idx = isset($parts[1]) ? (int) $parts[1] : 0;
            if ($idTipoPermiso <= 0) {
                continue;
            }
            $prefix = 'pago_derecho_' . $idTipoPermiso . '_' . $idx . '_';
            $monto = $request->input($prefix . 'monto') ?? $request->input('pago_derecho_' . $idTipoPermiso . '_monto');
            $banco = $request->input($prefix . 'banco') ?? $request->input('pago_derecho_' . $idTipoPermiso . '_banco');
            $fechaCierre = $request->input($prefix . 'fecha_cierre') ?? $request->input('pago_derecho_' . $idTipoPermiso . '_fecha_cierre');
            $filename = time() . '_' . uniqid() . '_der_' . $idTipoPermiso . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('tramites/documentos', $filename, 'public');
            PagoPermisoDerechoTramite::create([
                'id_tramite'      => $idTramite,
                'id_tipo_permiso' => $idTipoPermiso,
                'ruta'            => $path,
                'nombre_original' => $file->getClientOriginalName(),
                'extension'       => $file->getClientOriginalExtension(),
                'peso'            => $file->getSize(),
                'monto'           => $monto !== null && $monto !== '' ? $monto : null,
                'banco'           => $banco ?: null,
                'fecha_cierre'    => $fechaCierre ?: null,
            ]);
        }

        // Múltiples comprobantes tramitador: comprobante_tramitador_{idx}, pago_tramitador_{idx}_monto/banco/fecha_cierre (y comprobante_tramitador sin índice = índice 0)
        $tramitadorIndex = 0;
        foreach (['comprobante_tramitador', 'comprobante_tramitador_0'] as $tryKey) {
            $fileTramitador = $request->file($tryKey);
            if ($fileTramitador && $fileTramitador->isValid()) {
                $prefix = $tramitadorIndex === 0 ? 'pago_tramitador_' : 'pago_tramitador_' . $tramitadorIndex . '_';
                $monto = $request->input($prefix . 'monto') ?? $request->input('pago_tramitador_monto');
                $banco = $request->input($prefix . 'banco') ?? $request->input('pago_tramitador_banco');
                $fechaCierre = $request->input($prefix . 'fecha_cierre') ?? $request->input('pago_tramitador_fecha_cierre');
                $filename = time() . '_' . uniqid() . '_tramitador.' . $fileTramitador->getClientOriginalExtension();
                $path = $fileTramitador->storeAs('tramites/documentos', $filename, 'public');
                PagoPermisoTramite::create([
                    'id_tramite'      => $idTramite,
                    'ruta'            => $path,
                    'nombre_original' => $fileTramitador->getClientOriginalName(),
                    'extension'       => $fileTramitador->getClientOriginalExtension(),
                    'peso'            => $fileTramitador->getSize(),
                    'monto'           => $monto !== null && $monto !== '' ? $monto : null,
                    'banco'           => $banco ?: null,
                    'fecha_cierre'    => $fechaCierre ?: null,
                ]);
                break;
            }
        }
        foreach ($allKeys as $key) {
            if (!preg_match('/^comprobante_tramitador_(\d+)$/', $key, $m) || (int) $m[1] === 0) {
                continue;
            }
            $idx = (int) $m[1];
            $fileTramitador = $request->file($key);
            if (!$fileTramitador || !$fileTramitador->isValid()) {
                continue;
            }
            $prefix = 'pago_tramitador_' . $idx . '_';
            $monto = $request->input($prefix . 'monto');
            $banco = $request->input($prefix . 'banco');
            $fechaCierre = $request->input($prefix . 'fecha_cierre');
            $filename = time() . '_' . uniqid() . '_tramitador.' . $fileTramitador->getClientOriginalExtension();
            $path = $fileTramitador->storeAs('tramites/documentos', $filename, 'public');
            PagoPermisoTramite::create([
                'id_tramite'      => $idTramite,
                'ruta'            => $path,
                'nombre_original' => $fileTramitador->getClientOriginalName(),
                'extension'       => $fileTramitador->getClientOriginalExtension(),
                'peso'            => $fileTramitador->getSize(),
                'monto'           => $monto !== null && $monto !== '' ? $monto : null,
                'banco'           => $banco ?: null,
                'fecha_cierre'    => $fechaCierre ?: null,
            ]);
        }

        // Actualizar comprobantes existentes (mismo método, sin otros endpoints)
        $actualizarDerechoJson = $request->input('comprobante_derecho_actualizar');
        if ($actualizarDerechoJson !== null && $actualizarDerechoJson !== '') {
            $items = json_decode($actualizarDerechoJson, true);
            if (is_array($items)) {
                foreach ($items as $item) {
                    $idComp = isset($item['id']) ? (int) $item['id'] : 0;
                    if ($idComp <= 0) {
                        continue;
                    }
                    $row = PagoPermisoDerechoTramite::where('id_tramite', $idTramite)->where('id', $idComp)->first();
                    if ($row) {
                        if (array_key_exists('monto', $item)) {
                            $row->monto = $item['monto'] !== null && $item['monto'] !== '' ? $item['monto'] : null;
                        }
                        if (array_key_exists('banco', $item)) {
                            $row->banco = $item['banco'] ?: null;
                        }
                        if (array_key_exists('fecha_cierre', $item)) {
                            $row->fecha_cierre = $item['fecha_cierre'] ?: null;
                        }
                        $row->save();
                    }
                }
            }
        }

        $actualizarTramitadorJson = $request->input('comprobante_tramitador_actualizar');
        if ($actualizarTramitadorJson !== null && $actualizarTramitadorJson !== '') {
            $items = json_decode($actualizarTramitadorJson, true);
            if (is_array($items)) {
                foreach ($items as $item) {
                    $idComp = isset($item['id']) ? (int) $item['id'] : 0;
                    if ($idComp <= 0) {
                        continue;
                    }
                    $row = PagoPermisoTramite::where('id_tramite', $idTramite)->where('id', $idComp)->first();
                    if ($row) {
                        if (array_key_exists('monto', $item)) {
                            $row->monto = $item['monto'] !== null && $item['monto'] !== '' ? $item['monto'] : null;
                        }
                        if (array_key_exists('banco', $item)) {
                            $row->banco = $item['banco'] ?: null;
                        }
                        if (array_key_exists('fecha_cierre', $item)) {
                            $row->fecha_cierre = $item['fecha_cierre'] ?: null;
                        }
                        $row->save();
                    }
                }
            }
        }

        // Reemplazar archivo de comprobantes existentes: comprobante_derecho_reemplazar_{id}, comprobante_tramitador_reemplazar_{id}
        foreach ($allKeys as $key) {
            if (strpos($key, 'comprobante_derecho_reemplazar_') !== 0) {
                continue;
            }
            $idComp = (int) str_replace('comprobante_derecho_reemplazar_', '', $key);
            if ($idComp <= 0) {
                continue;
            }
            $file = $request->file($key);
            if (!$file || !$file->isValid()) {
                continue;
            }
            $row = PagoPermisoDerechoTramite::where('id_tramite', $idTramite)->where('id', $idComp)->first();
            if ($row) {
                if (Storage::disk('public')->exists($row->ruta)) {
                    Storage::disk('public')->delete($row->ruta);
                }
                $filename = time() . '_' . uniqid() . '_der_repl_' . $idComp . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('tramites/documentos', $filename, 'public');
                $row->ruta = $path;
                $row->nombre_original = $file->getClientOriginalName();
                $row->extension = $file->getClientOriginalExtension();
                $row->peso = $file->getSize();
                $row->save();
            }
        }
        foreach ($allKeys as $key) {
            if (strpos($key, 'comprobante_tramitador_reemplazar_') !== 0) {
                continue;
            }
            $idComp = (int) str_replace('comprobante_tramitador_reemplazar_', '', $key);
            if ($idComp <= 0) {
                continue;
            }
            $file = $request->file($key);
            if (!$file || !$file->isValid()) {
                continue;
            }
            $row = PagoPermisoTramite::where('id_tramite', $idTramite)->where('id', $idComp)->first();
            if ($row) {
                if (Storage::disk('public')->exists($row->ruta)) {
                    Storage::disk('public')->delete($row->ruta);
                }
                $filename = time() . '_' . uniqid() . '_tram_repl_' . $idComp . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('tramites/documentos', $filename, 'public');
                $row->ruta = $path;
                $row->nombre_original = $file->getClientOriginalName();
                $row->extension = $file->getClientOriginalExtension();
                $row->peso = $file->getSize();
                $row->save();
            }
        }

        return ['success' => true];
    }

    /**
     * Eliminar comprobante de derecho de trámite (solo administración). Borra el registro y el archivo en storage.
     */
    public function eliminarComprobanteDerecho(int $idTramite, int $idComprobante): array
    {
        $row = PagoPermisoDerechoTramite::where('id_tramite', $idTramite)->where('id', $idComprobante)->first();
        if (!$row) {
            return ['success' => false, 'error' => 'Comprobante no encontrado'];
        }
        if (Storage::disk('public')->exists($row->ruta)) {
            Storage::disk('public')->delete($row->ruta);
        }
        $row->delete();
        return ['success' => true];
    }

    /**
     * Eliminar comprobante del tramitador (solo administración). Borra el registro y el archivo en storage.
     */
    public function eliminarComprobanteTramitador(int $idTramite, int $idComprobante): array
    {
        $row = PagoPermisoTramite::where('id_tramite', $idTramite)->where('id', $idComprobante)->first();
        if (!$row) {
            return ['success' => false, 'error' => 'Comprobante no encontrado'];
        }
        if (Storage::disk('public')->exists($row->ruta)) {
            Storage::disk('public')->delete($row->ruta);
        }
        $row->delete();
        return ['success' => true];
    }

    /**
     * Guarda la asignación de documentos por sección para un tipo de permiso (tab).
     * Payload: documentos_tramite_ids, fotos_ids, seguimiento_ids (arrays de IDs).
     */
    public function guardarTipoPermiso(int $idTramite, int $idTipoPermiso, array $documentosTramiteIds, array $fotosIds, array $seguimientoIds): array
    {
        try {
            $tramite = ConsolidadoCotizacionAduanaTramite::with('tiposPermiso')->find($idTramite);
            if (!$tramite) {
                return ['success' => false, 'error' => 'Trámite no encontrado'];
            }

            $tieneTipo = $tramite->tiposPermiso->contains('id', $idTipoPermiso);
            if (!$tieneTipo) {
                return ['success' => false, 'error' => 'El tipo de permiso no pertenece a este trámite'];
            }

            $docIds = array_map('intval', array_filter($documentosTramiteIds, function ($id) { return is_numeric($id); }));
            $fotoIds = array_map('intval', array_filter($fotosIds, function ($id) { return is_numeric($id); }));
            $segIds = array_map('intval', array_filter($seguimientoIds, function ($id) { return is_numeric($id); }));
            $todosIds = array_unique(array_merge($docIds, $fotoIds, $segIds));

            if (!empty($todosIds)) {
                $documentos = TramiteAduanaDocumento::where('id_tramite', $idTramite)->whereIn('id', $todosIds)->get();
                foreach ($documentos as $doc) {
                    $id = (int) $doc->id;
                    if (in_array($id, $docIds, true)) {
                        $doc->seccion = 'documentos_tramite';
                        $doc->id_tipo_permiso = $idTipoPermiso;
                        $doc->save();
                    } elseif (in_array($id, $fotoIds, true)) {
                        $doc->seccion = 'fotos';
                        $doc->id_tipo_permiso = $idTipoPermiso;
                        $doc->save();
                    } elseif (in_array($id, $segIds, true)) {
                        $doc->seccion = 'seguimiento';
                        $doc->id_tipo_permiso = $idTipoPermiso;
                        $doc->save();
                    }
                }
            }

            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('Error al guardar tipo permiso documentos: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
