<?php

namespace App\Services\BaseDatos;

use App\Models\CargaConsolidada\ConsolidadoCotizacionAduanaTramite;
use App\Models\CargaConsolidada\TramiteAduanaCategoria;
use App\Models\CargaConsolidada\TramiteAduanaPago;
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
                    ->orWhereHas('cliente', function ($q) use ($term) { $q->where('nombre', 'like', "%{$term}%")->orWhere('documento', 'like', "%{$term}%"); })
                    ->orWhereHas('cotizacion', function ($q) use ($term) { $q->where('nombre', 'like', "%{$term}%")->orWhere('documento', 'like', "%{$term}%"); });
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
     * Crear trámite (acepta tipos_permiso como array; los datos por tipo van en el pivot)
     */
    public function crear(Request $request): array
    {
        $validated = $request->validate([
            'id_consolidado' => 'required|integer',
            'id_entidad' => 'required|integer',
            'precio' => 'required|numeric|min:0',
            'tipos_permiso' => 'required|array|min:1',
            'tipos_permiso.*.id_tipo_permiso' => 'required|integer',
            'tipos_permiso.*.derecho_entidad' => 'required|numeric|min:0',
            'tipos_permiso.*.f_inicio' => 'nullable|date',
            'tipos_permiso.*.f_termino' => 'nullable|date',
            'tipos_permiso.*.f_caducidad' => 'nullable|date',
            'tipos_permiso.*.dias' => 'nullable|integer|min:0',
            'tipos_permiso.*.estado' => 'nullable|in:PENDIENTE,SD,PAGADO,EN_TRAMITE,RECHAZADO,COMPLETADO',
            'id_cotizacion' => 'nullable|integer',
            'id_cliente' => 'nullable|integer',
            'tramitador' => 'nullable|numeric|min:0',
            'estado' => 'nullable|in:PENDIENTE,SD,PAGADO,EN_TRAMITE,RECHAZADO,COMPLETADO',
        ]);

        $tramiteData = [
            'id_consolidado' => $validated['id_consolidado'],
            'id_entidad' => $validated['id_entidad'],
            'precio' => $validated['precio'],
            'id_cotizacion' => $validated['id_cotizacion'] ?? null,
            'id_cliente' => null,
            'estado' => $validated['estado'] ?? 'PENDIENTE',
            'tramitador' => $validated['tramitador'] ?? null,
        ];
        if (!empty($validated['id_cotizacion'])) {
            $tramiteData['id_cliente'] = null;
        }

        try {
            $tramite = ConsolidadoCotizacionAduanaTramite::create($tramiteData);

            $pivotData = [];
            foreach ($validated['tipos_permiso'] as $tp) {
                $pivotData[$tp['id_tipo_permiso']] = [
                    'derecho_entidad' => $tp['derecho_entidad'],
                    'estado' => $tp['estado'] ?? 'PENDIENTE',
                    'f_inicio' => $tp['f_inicio'] ?? null,
                    'f_termino' => $tp['f_termino'] ?? null,
                    'f_caducidad' => $tp['f_caducidad'] ?? null,
                    'dias' => $tp['dias'] ?? null,
                ];
            }
            $tramite->tiposPermiso()->attach($pivotData);

            $tipoIds = array_column($validated['tipos_permiso'], 'id_tipo_permiso');
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
     * Actualizar trámite (acepta tipos_permiso como array; sincroniza el pivot)
     */
    public function actualizar(int $id, Request $request): array
    {
        $tramite = ConsolidadoCotizacionAduanaTramite::find($id);
        if (!$tramite) {
            return ['success' => false, 'data' => null, 'error' => 'Trámite no encontrado'];
        }

        $validated = $request->validate([
            'id_consolidado' => 'sometimes|integer',
            'id_entidad' => 'sometimes|integer',
            'precio' => 'sometimes|numeric|min:0',
            'tipos_permiso' => 'sometimes|array|min:1',
            'tipos_permiso.*.id_tipo_permiso' => 'required_with:tipos_permiso|integer',
            'tipos_permiso.*.derecho_entidad' => 'required_with:tipos_permiso|numeric|min:0',
            'tipos_permiso.*.f_inicio' => 'nullable|date',
            'tipos_permiso.*.f_termino' => 'nullable|date',
            'tipos_permiso.*.f_caducidad' => 'nullable|date',
            'tipos_permiso.*.dias' => 'nullable|integer|min:0',
            'tipos_permiso.*.estado' => 'nullable|in:PENDIENTE,SD,PAGADO,EN_TRAMITE,RECHAZADO,COMPLETADO',
            'id_cotizacion' => 'nullable|integer',
            'id_cliente' => 'nullable|integer',
            'tramitador' => 'nullable|numeric|min:0',
            'estado' => 'nullable|in:PENDIENTE,SD,PAGADO,EN_TRAMITE,RECHAZADO,COMPLETADO',
        ]);

        $tramiteData = collect($validated)->except('tipos_permiso')->filter(function ($v, $k) {
            return in_array($k, ['id_consolidado', 'id_entidad', 'precio', 'id_cotizacion', 'id_cliente', 'estado', 'tramitador'], true);
        })->all();
        if (array_key_exists('id_cotizacion', $validated) && !empty($validated['id_cotizacion'])) {
            $tramiteData['id_cliente'] = null;
        }

        try {
            $tramite->update($tramiteData);

            if (isset($validated['tipos_permiso'])) {
                $pivotData = [];
                foreach ($validated['tipos_permiso'] as $tp) {
                    $pivotData[$tp['id_tipo_permiso']] = [
                        'derecho_entidad' => $tp['derecho_entidad'],
                        'estado' => $tp['estado'] ?? 'PENDIENTE',
                        'f_inicio' => $tp['f_inicio'] ?? null,
                        'f_termino' => $tp['f_termino'] ?? null,
                        'f_caducidad' => $tp['f_caducidad'] ?? null,
                        'dias' => $tp['dias'] ?? null,
                    ];
                }
                $tramite->tiposPermiso()->sync($pivotData);

                $tipoIds = array_column($validated['tipos_permiso'], 'id_tipo_permiso');
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
            $tramite->delete();
            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('Error al eliminar trámite aduana: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
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

        $tiposPermiso = $t->tiposPermiso->map(function ($tp) {
            $pivot = $tp->pivot;
            $formatDate = function ($value) {
                if ($value === null || $value === '') return null;
                try {
                    return \Carbon\Carbon::parse($value)->format('Y-m-d');
                } catch (\Throwable $e) {
                    return null;
                }
            };
            return [
                'id' => $tp->id,
                'nombre_permiso' => $tp->nombre ?? null,
                'derecho_entidad' => (float) ($pivot->derecho_entidad ?? 0),
                'estado' => $pivot->estado ?? 'PENDIENTE',
                'f_inicio' => $formatDate($pivot->f_inicio ?? null),
                'f_termino' => $formatDate($pivot->f_termino ?? null),
                'f_caducidad' => $formatDate($pivot->f_caducidad ?? null),
                'dias' => $pivot->dias,
            ];
        })->all();

        return [
            'id' => $t->id,
            'id_cotizacion' => $t->id_cotizacion,
            'id_consolidado' => $t->id_consolidado,
            'id_cliente' => $t->id_cliente,
            'id_entidad' => $t->id_entidad,
            'precio' => (float) $t->precio,
            'tramitador' => $t->tramitador !== null ? (float) $t->tramitador : null,
            'f_inicio' => $t->f_inicio ? $t->f_inicio->format('Y-m-d') : null,
            'f_termino' => $t->f_termino ? $t->f_termino->format('Y-m-d') : null,
            'f_caducidad' => $t->f_caducidad ? $t->f_caducidad->format('Y-m-d') : null,
            'dias' => $t->dias,
            'estado' => $t->estado,
            'created_at' => $t->created_at ? $t->created_at->toIso8601String() : null,
            'updated_at' => $t->updated_at ? $t->updated_at->toIso8601String() : null,
            'cotizacion' => $cotizacion ? ['id' => $cotizacion->id, 'nombre' => $cotizacion->nombre ?? null] : null,
            'consolidado' => $consolidado ? [
                'id' => $consolidado->id,
                'codigo' => self::formatoConsolidadoCodigo($consolidado),
                'nombre' => $consolidado->carga ?? null,
            ] : null,
            'entidad' => $t->entidad ? ['id' => $t->entidad->id, 'nombre' => $t->entidad->nombre] : null,
            'tipos_permiso' => $tiposPermiso,
            'cliente' => $cliente,
            'total_pago_servicio' => (float) TramiteAduanaPago::where('id_tramite', $t->id)->sum('monto'),
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
