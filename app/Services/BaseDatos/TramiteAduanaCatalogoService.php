<?php

namespace App\Services\BaseDatos;

use App\Models\CargaConsolidada\TramiteAduanaEntidad;
use App\Models\CargaConsolidada\TramiteAduanaTipoPermiso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de catálogos para trámites aduana: entidades y tipos de permiso (solo id, nombre para listas).
 */
class TramiteAduanaCatalogoService
{
    /**
     * Listar entidades (no eliminadas) para dropdowns.
     *
     * @return array
     */
    public function listarEntidadesActivas()
    {
        try {
            $entidades = TramiteAduanaEntidad::query()
                ->orderBy('nombre')
                ->get();

            $data = $entidades->map(function ($e) {
                return [
                    'id' => (int) $e->id,
                    'nombre' => $e->nombre ?? '',
                ];
            })->values()->all();

            return [
                'success' => true,
                'data' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Error al listar entidades trámite aduana: ' . $e->getMessage());
            return [
                'success' => false,
                'data' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Listar tipos de permiso (no eliminados) para dropdowns.
     *
     * @return array
     */
    public function listarTiposPermisoActivos()
    {
        try {
            $tipos = TramiteAduanaTipoPermiso::query()
                ->orderBy('nombre')
                ->get();

            $data = $tipos->map(function ($p) {
                return [
                    'id' => (int) $p->id,
                    'nombre_permiso' => $p->nombre ?? '',
                ];
            })->values()->all();

            return [
                'success' => true,
                'data' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Error al listar tipos permiso trámite aduana: ' . $e->getMessage());
            return [
                'success' => false,
                'data' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Crear entidad (solo nombre).
     *
     * @return array
     */
    public function crearEntidad(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
        ]);

        try {
            $entidad = TramiteAduanaEntidad::create($validated);
            return [
                'success' => true,
                'data' => [
                    'id' => (int) $entidad->id,
                    'nombre' => $entidad->nombre,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error al crear entidad trámite aduana: ' . $e->getMessage());
            return [
                'success' => false,
                'data' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Crear tipo de permiso (solo nombre; en API se devuelve como nombre_permiso).
     *
     * @return array
     */
    public function crearTipoPermiso(Request $request)
    {
        $validated = $request->validate([
            'nombre_permiso' => 'required|string|max:255',
        ]);
        $validated['nombre'] = $validated['nombre_permiso'];
        unset($validated['nombre_permiso']);

        try {
            $tipo = TramiteAduanaTipoPermiso::create($validated);
            return [
                'success' => true,
                'data' => [
                    'id' => (int) $tipo->id,
                    'nombre_permiso' => $tipo->nombre,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error al crear tipo permiso trámite aduana: ' . $e->getMessage());
            return [
                'success' => false,
                'data' => null,
                'error' => $e->getMessage(),
            ];
        }
    }
}
