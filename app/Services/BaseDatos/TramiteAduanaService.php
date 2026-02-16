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
                'tipoPermiso',
                'cliente',
            ]);

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->whereHas('entidad', function ($q) use ($term) { $q->where('nombre', 'like', "%{$term}%"); })
                    ->orWhereHas('tipoPermiso', function ($q) use ($term) { $q->where('nombre', 'like', "%{$term}%"); })
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
            'tipoPermiso',
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
            'id_consolidado' => 'required|integer',
            'id_entidad' => 'required|integer',
            'id_tipo_permiso' => 'required|integer',
            'derecho_entidad' => 'required|numeric|min:0',
            'precio' => 'required|numeric|min:0',
            'id_cotizacion' => 'nullable|integer',
            'id_cliente' => 'nullable|integer',
            'f_inicio' => 'nullable|date',
            'f_termino' => 'nullable|date',
            'f_caducidad' => 'nullable|date',
            'dias' => 'nullable|integer|min:0',
            'estado' => 'nullable|in:PENDIENTE,SD,PAGADO,EN_TRAMITE,RECHAZADO,COMPLETADO',
        ]);

        $validated['estado'] = $validated['estado'] ?? 'PENDIENTE';

        try {
            $tramite = ConsolidadoCotizacionAduanaTramite::create($validated);
            TramiteAduanaCategoria::crearPorDefectoParaTramite($tramite->id);
            $tramite->load(['cotizacion', 'consolidado', 'entidad', 'tipoPermiso', 'cliente']);
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
            'id_consolidado' => 'sometimes|integer',
            'id_entidad' => 'sometimes|integer',
            'id_tipo_permiso' => 'sometimes|integer',
            'derecho_entidad' => 'sometimes|numeric|min:0',
            'precio' => 'sometimes|numeric|min:0',
            'id_cotizacion' => 'nullable|integer',
            'id_cliente' => 'nullable|integer',
            'f_inicio' => 'nullable|date',
            'f_termino' => 'nullable|date',
            'f_caducidad' => 'nullable|date',
            'dias' => 'nullable|integer|min:0',
            'estado' => 'nullable|in:PENDIENTE,SD,PAGADO,EN_TRAMITE,RECHAZADO,COMPLETADO',
        ]);

        try {
            $tramite->update($validated);
            $tramite->load(['cotizacion', 'consolidado', 'entidad', 'tipoPermiso', 'cliente']);
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

        return [
            'id' => $t->id,
            'id_cotizacion' => $t->id_cotizacion,
            'id_consolidado' => $t->id_consolidado,
            'id_cliente' => $t->id_cliente,
            'id_entidad' => $t->id_entidad,
            'id_tipo_permiso' => $t->id_tipo_permiso,
            'derecho_entidad' => (float) $t->derecho_entidad,
            'precio' => (float) $t->precio,
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
            'tipo_permiso' => $t->tipoPermiso ? ['id' => $t->tipoPermiso->id, 'nombre_permiso' => $t->tipoPermiso->nombre] : null,
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
