<?php

namespace App\Services\SoporteTi;

use App\Models\Grupo;
use App\Models\SoporteTi\SoporteTiArea;
use App\Models\SoporteTi\SoporteTiSolicitud;
use App\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SoporteTiAreaService
{
    /** @var SoporteTiCacheService */
    protected $cache;

    public function __construct(SoporteTiCacheService $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Catálogo para el modal de creación (áreas activas + default del rol).
     *
     * @return array{areas: array<int, array{id:int,nombre:string,roles:array}>, area_default: string|null}
     */
    public function catalogoParaCrear(?Authenticatable $user = null)
    {
        $user = $user ?: Auth::user();
        $areas = $this->cache->rememberAreas(function () {
            return SoporteTiArea::query()
                ->with(array('grupos' => function ($q) {
                    $q->orderBy('No_Grupo');
                }))
                ->where('activo', 1)
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get()
                ->map(function (SoporteTiArea $a) {
                    $roles = array();
                    foreach ($a->grupos as $g) {
                        $roles[] = array(
                            'id' => (int) $g->ID_Grupo,
                            'nombre' => $g->No_Grupo,
                        );
                    }

                    return array(
                        'id' => (int) $a->id,
                        'nombre' => $a->nombre,
                        'roles' => $roles,
                    );
                })
                ->values()
                ->all();
        });

        return array(
            'areas' => $areas,
            'area_default' => $this->areaDefaultDelUsuario($user, $areas),
        );
    }

    /**
     * Listado de gestión (incluye inactivas y roles).
     *
     * @return array{areas: array, grupos: array}
     */
    public function listarGestion(?Authenticatable $user = null)
    {
        $this->asegurarPuedeGestionar($user);

        $areas = SoporteTiArea::query()
            ->with(array('grupos' => function ($q) {
                $q->orderBy('No_Grupo');
            }))
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return array(
            'areas' => $areas->map(function (SoporteTiArea $a) {
                return $this->mapArea($a);
            })->values()->all(),
            'grupos' => $this->listarGruposAsignables(),
        );
    }

    /**
     * @param array{nombre:string,orden?:int,activo?:bool,grupo_ids?:int[]} $data
     * @return array
     */
    public function crear(array $data, ?Authenticatable $user = null)
    {
        $this->asegurarPuedeGestionar($user);
        $nombre = $this->normalizarNombre(isset($data['nombre']) ? $data['nombre'] : '');
        $this->asegurarNombreUnico($nombre);

        $area = DB::transaction(function () use ($data, $nombre) {
            $maxOrden = (int) SoporteTiArea::max('orden');
            $area = SoporteTiArea::create(array(
                'nombre' => $nombre,
                'orden' => isset($data['orden']) ? (int) $data['orden'] : ($maxOrden + 1),
                'activo' => array_key_exists('activo', $data) ? (bool) $data['activo'] : true,
            ));
            $this->syncGrupos($area, isset($data['grupo_ids']) ? $data['grupo_ids'] : array());

            return $area;
        });

        $this->cache->bumpCatalogEpoch('areas');
        $area->load('grupos');

        return $this->mapArea($area);
    }

    /**
     * @param int|string $id
     * @param array{nombre?:string,orden?:int,activo?:bool,grupo_ids?:int[]} $data
     * @return array
     */
    public function actualizar($id, array $data, ?Authenticatable $user = null)
    {
        $this->asegurarPuedeGestionar($user);
        $area = SoporteTiArea::find($id);
        if (!$area) {
            throw new ModelNotFoundException();
        }

        DB::transaction(function () use ($area, $data) {
            $patch = array();
            if (array_key_exists('nombre', $data)) {
                $nombre = $this->normalizarNombre($data['nombre']);
                $this->asegurarNombreUnico($nombre, (int) $area->id);
                $patch['nombre'] = $nombre;
            }
            if (array_key_exists('orden', $data)) {
                $patch['orden'] = (int) $data['orden'];
            }
            if (array_key_exists('activo', $data)) {
                $patch['activo'] = (bool) $data['activo'];
            }
            if ($patch) {
                $area->fill($patch);
                $area->save();
            }
            if (array_key_exists('grupo_ids', $data)) {
                $this->syncGrupos($area, $data['grupo_ids']);
            }
        });

        $this->cache->bumpCatalogEpoch('areas');
        $area->load('grupos');

        return $this->mapArea($area);
    }

    /**
     * @param int|string $id
     * @return array{desactivada:bool}
     */
    public function eliminar($id, ?Authenticatable $user = null)
    {
        $this->asegurarPuedeGestionar($user);
        $area = SoporteTiArea::find($id);
        if (!$area) {
            throw new ModelNotFoundException();
        }

        $usada = SoporteTiSolicitud::where('area', $area->nombre)->exists();
        if ($usada) {
            $area->activo = false;
            $area->save();
            $this->cache->bumpCatalogEpoch('areas');

            return array('desactivada' => true);
        }

        $area->grupos()->detach();
        $area->delete();
        $this->cache->bumpCatalogEpoch('areas');

        return array('desactivada' => false);
    }

    /**
     * @param array<int, array{id:int,nombre:string,roles?:array}> $areas
     * @return string|null
     */
    protected function areaDefaultDelUsuario(?Authenticatable $user, array $areas)
    {
        $fallback = isset($areas[0]['nombre']) ? $areas[0]['nombre'] : null;
        if (!$user instanceof Usuario) {
            return $fallback;
        }

        $user->loadMissing(array('grupo', 'gruposUsuario'));

        $grupoIds = array();
        $nombres = array();
        if ((int) $user->ID_Grupo > 0) {
            $grupoIds[] = (int) $user->ID_Grupo;
        }
        if ($user->grupo) {
            $grupoIds[] = (int) $user->grupo->ID_Grupo;
            $nom = trim((string) $user->grupo->No_Grupo);
            if ($nom !== '') {
                $nombres[] = $nom;
            }
        }
        foreach ($user->gruposUsuario as $gu) {
            if ((int) $gu->ID_Grupo > 0) {
                $grupoIds[] = (int) $gu->ID_Grupo;
            }
        }
        $grupoIds = array_values(array_unique(array_filter($grupoIds)));

        if (count($grupoIds) > 0) {
            $nombre = DB::table('soporte_ti_areas as a')
                ->join('soporte_ti_area_grupo as ag', 'ag.area_id', '=', 'a.id')
                ->where('a.activo', 1)
                ->whereIn('ag.grupo_id', $grupoIds)
                ->orderBy('a.orden')
                ->value('a.nombre');
            if ($nombre) {
                return (string) $nombre;
            }
        }

        if (count($nombres) > 0) {
            $lower = array_map('mb_strtolower', $nombres);
            foreach ($areas as $area) {
                foreach (isset($area['roles']) ? $area['roles'] : array() as $rol) {
                    $rolNombre = mb_strtolower(trim((string) (isset($rol['nombre']) ? $rol['nombre'] : '')));
                    if ($rolNombre !== '' && in_array($rolNombre, $lower, true)) {
                        return (string) $area['nombre'];
                    }
                }
            }
        }

        return $fallback;
    }

    /**
     * @param int[]|mixed $grupoIds
     */
    protected function syncGrupos(SoporteTiArea $area, $grupoIds)
    {
        $ids = array();
        if (is_array($grupoIds)) {
            foreach ($grupoIds as $id) {
                $n = (int) $id;
                if ($n > 0) {
                    $ids[] = $n;
                }
            }
        }
        $ids = array_values(array_unique($ids));

        if (count($ids) > 0) {
            $ocupados = DB::table('soporte_ti_area_grupo')
                ->whereIn('grupo_id', $ids)
                ->where('area_id', '!=', (int) $area->id)
                ->pluck('grupo_id')
                ->all();
            if (count($ocupados) > 0) {
                $nombres = Grupo::whereIn('ID_Grupo', $ocupados)->pluck('No_Grupo')->implode(', ');
                throw new \InvalidArgumentException(
                    'Estos roles ya están asociados a otra área: ' . $nombres
                );
            }
        }

        $area->grupos()->sync($ids);
    }

    /**
     * @return array<int, array{id:int,nombre:string}>
     */
    protected function listarGruposAsignables()
    {
        return Grupo::query()
            ->where('Nu_Estado', 1)
            ->where('Nu_Tipo_Privilegio_Acceso', 1)
            ->orderBy('No_Grupo')
            ->get(array('ID_Grupo', 'No_Grupo'))
            ->map(function (Grupo $g) {
                return array(
                    'id' => (int) $g->ID_Grupo,
                    'nombre' => $g->No_Grupo,
                );
            })
            ->values()
            ->all();
    }

    /**
     * @return array
     */
    protected function mapArea(SoporteTiArea $a)
    {
        $roles = array();
        if ($a->relationLoaded('grupos')) {
            foreach ($a->grupos as $g) {
                $roles[] = array(
                    'id' => (int) $g->ID_Grupo,
                    'nombre' => $g->No_Grupo,
                );
            }
        }

        return array(
            'id' => (int) $a->id,
            'nombre' => $a->nombre,
            'orden' => (int) $a->orden,
            'activo' => (bool) $a->activo,
            'roles' => $roles,
            'grupo_ids' => array_map(function ($r) {
                return $r['id'];
            }, $roles),
        );
    }

    /**
     * @param string $nombre
     * @param int|null $exceptId
     */
    protected function asegurarNombreUnico($nombre, $exceptId = null)
    {
        $q = SoporteTiArea::where('nombre', $nombre);
        if ($exceptId) {
            $q->where('id', '!=', (int) $exceptId);
        }
        if ($q->exists()) {
            throw new \InvalidArgumentException('Ya existe un área con ese nombre.');
        }
    }

    /**
     * @param string $nombre
     * @return string
     */
    protected function normalizarNombre($nombre)
    {
        $n = trim((string) $nombre);
        if ($n === '') {
            throw new \InvalidArgumentException('El nombre del área es obligatorio.');
        }
        if (mb_strlen($n) > 80) {
            throw new \InvalidArgumentException('El nombre no puede superar 80 caracteres.');
        }

        return $n;
    }

    protected function asegurarPuedeGestionar(?Authenticatable $user = null)
    {
        $user = $user ?: Auth::user();
        if (!$user instanceof Usuario) {
            throw new AuthorizationException('No autenticado');
        }
        $user->loadMissing('grupo');
        $nombre = $user->grupo ? strtolower(trim((string) $user->grupo->No_Grupo)) : '';
        $permitidos = array(
            strtolower(Usuario::ROL_SOPORTE),
            strtolower(Usuario::ROL_PM),
            strtolower(Usuario::ROL_ADMINISTRACION),
            'gerencia',
            'admin',
        );
        if (!in_array($nombre, $permitidos, true)) {
            throw new AuthorizationException('No autorizado para gestionar áreas de Soporte TI.');
        }
    }
}
