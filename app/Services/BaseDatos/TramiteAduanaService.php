<?php

namespace App\Services\BaseDatos;

use App\Models\CargaConsolidada\ConsolidadoCotizacionAduanaTramite;
use App\Models\CargaConsolidada\TramiteAduanaCategoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TramiteAduanaService
{
    /**
     * Listar trámites con filtros y paginación
     */
    public function listar(Request $request): array
    {
        $perPage = (int) $request->get('limit', 50);
        $page = (int) $request->get('page', 1);

        $query = ConsolidadoCotizacionAduanaTramite::query()
            ->with([
                'cotizacion',
                'consolidado',
                'entidad',
                'tiposPermiso',
                'cliente',
            ]);

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->whereHas('entidad', function ($q) use ($term) { $q->where('nombre', 'like', "%{$term}%"); })
                    ->orWhereHas('tiposPermiso', function ($q) use ($term) { $q->where('nombre', 'like', "%{$term}%"); })
                    ->orWhereHas('cliente', function ($q) use ($term) { $q->where('nombre', 'like', "%{$term}%")->orWhere('documento', 'like', "%{$term}%"); });
            });
        }
        if ($request->filled('id_consolidado')) {
            $query->where('id_consolidado', $request->id_consolidado);
        }
        if ($request->filled('id_entidad')) {
            $query->where('id_entidad', $request->id_entidad);
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        $query->orderByDesc('created_at');
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $data = collect($paginator->items())->map(function ($t) { return $this->mapearTramite($t); })->all();

        return [
            'success' => true,
            'data' => $data,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    /**
     * Obtener un trámite por ID
     */
    public function mostrar(int $id): array
    {
        $tramite = ConsolidadoCotizacionAduanaTramite::with([
            'cotizacion',
            'consolidado',
            'entidad',
            'tiposPermiso',
            'cliente',
        ])->find($id);

        if (!$tramite) {
            return ['success' => false, 'data' => null, 'error' => 'Trámite no encontrado'];
        }

        return [
            'success' => true,
            'data' => $this->mapearTramite($tramite),
        ];
    }

    /**
     * Crear trámite
     */
    public function crear(Request $request): array
    {
        $validated = $request->validate([
            'id_consolidado'                      => 'required|integer',
            'id_entidad'                          => 'required|integer',
            'tipos_permiso'                       => 'required|array|min:1',
            'tipos_permiso.*.id_tipo_permiso'     => 'required|integer',
            'tipos_permiso.*.derecho_entidad'     => 'required|numeric|min:0',
            'tipos_permiso.*.estado'              => 'nullable|in:PENDIENTE,SD,PAGADO,EN_TRAMITE,RECHAZADO,COMPLETADO',
            'precio'                              => 'required|numeric|min:0',
            'id_cotizacion'                       => 'nullable|integer',
            'id_cliente'                          => 'nullable|integer',
            'f_inicio'                            => 'nullable|date',
            'f_termino'                           => 'nullable|date',
            'f_caducidad'                         => 'nullable|date',
            'dias'                                => 'nullable|integer|min:0',
            'estado'                              => 'nullable|in:PENDIENTE,SD,PAGADO,EN_TRAMITE,RECHAZADO,COMPLETADO',
            'tramitador'                          => 'nullable|numeric|min:0',
        ]);

        $tramiteData = collect($validated)->except('tipos_permiso')->all();
        $tramiteData['estado'] = $tramiteData['estado'] ?? 'PENDIENTE';

        try {
            $tramite = ConsolidadoCotizacionAduanaTramite::create($tramiteData);

            // Sincronizar tipos de permiso con sus derechos y estados individuales
            $pivotData = collect($validated['tipos_permiso'])->mapWithKeys(function ($tp) {
                return [$tp['id_tipo_permiso'] => [
                    'derecho_entidad' => $tp['derecho_entidad'],
                    'estado' => $tp['estado'] ?? 'PENDIENTE',
                ]];
            })->all();
            $tramite->tiposPermiso()->sync($pivotData);

            $tipoIds = collect($validated['tipos_permiso'])->pluck('id_tipo_permiso')->all();
            TramiteAduanaCategoria::crearCategoriasParaTramiteConTipos($tramite->id, $tipoIds);
            $tramite->load(['cotizacion', 'consolidado', 'entidad', 'tiposPermiso', 'cliente']);

            return [
                'success' => true,
                'data' => $this->mapearTramite($tramite),
            ];
        } catch (\Exception $e) {
            Log::error('Error al crear trámite aduana: ' . $e->getMessage());
            return ['success' => false, 'data' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Actualizar trámite
     */
    public function actualizar(int $id, Request $request): array
    {
        $tramite = ConsolidadoCotizacionAduanaTramite::find($id);
        if (!$tramite) {
            return ['success' => false, 'data' => null, 'error' => 'Trámite no encontrado'];
        }

        $validated = $request->validate([
            'id_consolidado'                      => 'sometimes|integer',
            'id_entidad'                          => 'sometimes|integer',
            'tipos_permiso'                       => 'sometimes|array|min:1',
            'tipos_permiso.*.id_tipo_permiso'     => 'required_with:tipos_permiso|integer',
            'tipos_permiso.*.derecho_entidad'     => 'required_with:tipos_permiso|numeric|min:0',
            'tipos_permiso.*.estado'              => 'nullable|in:PENDIENTE,SD,PAGADO,EN_TRAMITE,RECHAZADO,COMPLETADO',
            'precio'                              => 'sometimes|numeric|min:0',
            'id_cotizacion'                       => 'nullable|integer',
            'id_cliente'                          => 'nullable|integer',
            'f_inicio'                            => 'nullable|date',
            'f_termino'                           => 'nullable|date',
            'f_caducidad'                         => 'nullable|date',
            'dias'                                => 'nullable|integer|min:0',
            'estado'                              => 'nullable|in:PENDIENTE,SD,PAGADO,EN_TRAMITE,RECHAZADO,COMPLETADO',
            'tramitador'                          => 'nullable|numeric|min:0',
        ]);

        try {
            $tramiteData = collect($validated)->except('tipos_permiso')->all();
            $tramite->update($tramiteData);

            // Sincronizar tipos de permiso solo si se envían en el request
            if ($request->has('tipos_permiso') && isset($validated['tipos_permiso'])) {
                $pivotData = collect($validated['tipos_permiso'])->mapWithKeys(function ($tp) {
                    $pivotRow = ['derecho_entidad' => $tp['derecho_entidad']];
                    if (isset($tp['estado'])) {
                        $pivotRow['estado'] = $tp['estado'];
                    }
                    return [$tp['id_tipo_permiso'] => $pivotRow];
                })->all();
                $tramite->tiposPermiso()->sync($pivotData);
                $tipoIds = collect($validated['tipos_permiso'])->pluck('id_tipo_permiso')->all();
                TramiteAduanaCategoria::asegurarCategoriasParaTramite($tramite->id, $tipoIds);
            }

            $tramite->load(['cotizacion', 'consolidado', 'entidad', 'tiposPermiso', 'cliente']);
            return [
                'success' => true,
                'data' => $this->mapearTramite($tramite),
            ];
        } catch (\Exception $e) {
            Log::error('Error al actualizar trámite aduana: ' . $e->getMessage());
            return ['success' => false, 'data' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Eliminar trámite
     */
    public function eliminar(int $id): array
    {
        $tramite = ConsolidadoCotizacionAduanaTramite::find($id);
        if (!$tramite) {
            return ['success' => false, 'error' => 'Trámite no encontrado'];
        }
        try {
            $tramite->tiposPermiso()->detach();
            $tramite->delete();
            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('Error al eliminar trámite aduana: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Actualizar el estado de un tipo de permiso en el pivot (sin tocar el resto del trámite)
     */
    public function actualizarEstadoTipoPermiso(int $tramiteId, int $tipoPermisoId, string $estado): array
    {
        $tramite = ConsolidadoCotizacionAduanaTramite::find($tramiteId);
        if (!$tramite) {
            return ['success' => false, 'error' => 'Trámite no encontrado'];
        }

        $estados = ConsolidadoCotizacionAduanaTramite::ESTADOS;
        if (!in_array($estado, $estados)) {
            return ['success' => false, 'error' => 'Estado no válido'];
        }

        $exists = $tramite->tiposPermiso()->wherePivot('id_tipo_permiso', $tipoPermisoId)->exists();
        if (!$exists) {
            return ['success' => false, 'error' => 'Tipo de permiso no asociado a este trámite'];
        }

        $tramite->tiposPermiso()->updateExistingPivot($tipoPermisoId, ['estado' => $estado]);

        return ['success' => true, 'data' => ['estado' => $estado]];
    }

    /**
     * Actualiza f_inicio y/o f_termino del tipo_permiso (pivot). Calcula dias = diferencia en días.
     * @param string|null $f_inicio Fecha Y-m-d
     * @param string|null $f_termino Fecha Y-m-d
     */
    public function actualizarFechasTipoPermiso(int $tramiteId, int $tipoPermisoId, ?string $f_inicio = null, ?string $f_termino = null): array
    {
        $tramite = ConsolidadoCotizacionAduanaTramite::with('tiposPermiso')->find($tramiteId);
        if (!$tramite) {
            return ['success' => false, 'error' => 'Trámite no encontrado'];
        }

        $pivot = $tramite->tiposPermiso->firstWhere('id', $tipoPermisoId);
        if (!$pivot) {
            return ['success' => false, 'error' => 'Tipo de permiso no asociado a este trámite'];
        }

        $updates = [];
        if ($f_inicio !== null) {
            $updates['f_inicio'] = $f_inicio;
        }
        if ($f_termino !== null) {
            $updates['f_termino'] = $f_termino;
        }

        if (!empty($updates)) {
            $tramite->tiposPermiso()->updateExistingPivot($tipoPermisoId, $updates);
        }

        // Recalcular dias si tenemos ambas fechas (leer pivot actualizado)
        $pivotRow = \Illuminate\Support\Facades\DB::table('tramite_aduana_tramite_tipo_permiso')
            ->where('id_tramite', $tramiteId)
            ->where('id_tipo_permiso', $tipoPermisoId)
            ->first();
        if ($pivotRow && $pivotRow->f_inicio && $pivotRow->f_termino) {
            $d1 = \Carbon\Carbon::parse($pivotRow->f_inicio);
            $d2 = \Carbon\Carbon::parse($pivotRow->f_termino);
            $dias = (int) abs($d1->diffInDays($d2, false));
            $tramite->tiposPermiso()->updateExistingPivot($tipoPermisoId, ['dias' => $dias]);
        }

        return ['success' => true];
    }

    private function mapearTramite(ConsolidadoCotizacionAduanaTramite $t): array
    {
        $consolidado = $t->consolidado;
        $cotizacion = $t->cotizacion;
        $cliente = $t->cliente;
        if (!$cliente && $cotizacion) {
            $cliente = (object)[
                'id' => $cotizacion->id,
                'nombre' => $cotizacion->nombre ?? null,
                'ruc' => $cotizacion->documento ?? null,
                'telefono' => $cotizacion->telefono ?? null,
                'email' => $cotizacion->correo ?? null,
            ];
        } elseif ($cliente) {
            $cliente = (object)[
                'id' => $cliente->id,
                'nombre' => $cliente->nombre ?? null,
                'ruc' => $cliente->ruc ?? $cliente->documento ?? null,
                'telefono' => $cliente->telefono ?? null,
                'email' => $cliente->correo ?? null,
            ];
        }

        return [
            'id' => $t->id,
            'id_cotizacion' => $t->id_cotizacion,
            'id_consolidado' => $t->id_consolidado,
            'id_cliente' => $t->id_cliente,
            'id_entidad' => $t->id_entidad,
            'precio' => (float) $t->precio,
            'f_inicio' => $t->f_inicio ? $t->f_inicio->format('Y-m-d') : null,
            'f_termino' => $t->f_termino ? $t->f_termino->format('Y-m-d') : null,
            'f_caducidad' => $t->f_caducidad ? $t->f_caducidad->format('Y-m-d') : null,
            'dias' => $t->dias,
            'estado' => $t->estado,
            'tramitador' => $t->tramitador !== null ? (float) $t->tramitador : null,
            'created_at' => $t->created_at ? $t->created_at->toIso8601String() : null,
            'updated_at' => $t->updated_at ? $t->updated_at->toIso8601String() : null,
            'cotizacion' => $cotizacion ? ['id' => $cotizacion->id, 'nombre' => $cotizacion->nombre ?? null] : null,
            'consolidado' => $consolidado ? [
                'id' => $consolidado->id,
                'codigo' => self::formatoConsolidadoCodigo($consolidado),
                'nombre' => $consolidado->carga ?? null,
            ] : null,
            'entidad' => $t->entidad ? ['id' => $t->entidad->id, 'nombre' => $t->entidad->nombre] : null,
            'tipos_permiso' => $t->tiposPermiso->map(function ($tp) {
                $p = $tp->pivot;
                return [
                    'id' => $tp->id,
                    'nombre_permiso' => $tp->nombre,
                    'derecho_entidad' => (float) $p->derecho_entidad,
                    'estado' => $p->estado ?? 'PENDIENTE',
                    'f_inicio' => $p->f_inicio ? (\Carbon\Carbon::parse($p->f_inicio)->format('Y-m-d')) : null,
                    'f_termino' => $p->f_termino ? (\Carbon\Carbon::parse($p->f_termino)->format('Y-m-d')) : null,
                    'f_caducidad' => $p->f_caducidad ? (\Carbon\Carbon::parse($p->f_caducidad)->format('Y-m-d')) : null,
                    'dias' => $p->dias,
                ];
            })->all(),
            'cliente' => $cliente,
        ];
    }

    /** Formato del consolidado en lista: #carga - año */
    private static function formatoConsolidadoCodigo($consolidado): ?string
    {
        $carga = $consolidado->carga ?? '';
        $anio = $consolidado->f_entrega ? $consolidado->f_entrega->format('Y') : '';
        if ($carga === '' && $anio === '') {
            return null;
        }
        return '#' . $carga . ($anio !== '' ? ' - ' . $anio : '');
    }
}
