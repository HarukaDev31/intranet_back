<?php

namespace App\Http\Controllers\PanelAcceso;

use App\Http\Controllers\Controller;
use App\Models\Organizacion;
use App\Services\Auth\AuthMenuCacheService;
use App\Services\PanelAcceso\MenuOrdenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MenuOrdenController extends Controller
{
    private const ID_ORGANIZACION_ADMIN = 1;

    /** @var MenuOrdenService */
    private $ordenService;

    public function __construct(MenuOrdenService $ordenService)
    {
        $this->ordenService = $ordenService;
    }

    /**
     * GET /api/panel-acceso/menu-orden/grupo/{grupoId}?org_id=
     * Árbol de menús del cargo con orden efectivo (grupo → Nu_Orden).
     */
    public function porGrupo(Request $request, $grupoId)
    {
        try {
            $authUser = auth()->user();
            $orgId = $this->resolverOrg($request, $authUser);
            if ($orgId === null) {
                return response()->json(['success' => false, 'message' => 'Organización inválida'], 422);
            }

            $grupoId = (int) $grupoId;
            $grupo = DB::table('grupo')->where('ID_Grupo', $grupoId)->first();
            if (!$grupo) {
                return response()->json(['success' => false, 'message' => 'Cargo no encontrado'], 404);
            }
            if ((int) $grupo->ID_Organizacion !== $orgId && (int) $authUser->getAttribute('ID_Organizacion') !== self::ID_ORGANIZACION_ADMIN) {
                return response()->json(['success' => false, 'message' => 'El cargo no pertenece a tu organización'], 403);
            }

            $permitidos = $this->menuIdsConAccesoGrupo($grupoId);
            $items = $this->menusArbolOrg($orgId, $permitidos);
            $mapa = $this->ordenService->mapaEfectivo(0, $grupoId);
            $items = $this->aplicarMapaAItems($items, $mapa);
            $tieneOverride = DB::table('menu_orden_grupo')->where('ID_Grupo', $grupoId)->exists();

            return response()->json([
                'success' => true,
                'data' => [
                    'ambito' => 'grupo',
                    'id_grupo' => $grupoId,
                    'tiene_override' => $tieneOverride,
                    'items' => $items,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('MenuOrdenController@porGrupo: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error al cargar orden'], 500);
        }
    }

    /**
     * GET /api/panel-acceso/menu-orden/usuario/{usuarioId}
     */
    public function porUsuario($usuarioId)
    {
        try {
            $authUser = auth()->user();
            $usuarioId = (int) $usuarioId;
            $usuario = DB::table('usuario')->where('ID_Usuario', $usuarioId)->first();
            if (!$usuario) {
                return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
            }

            $authOrg = (int) $authUser->getAttribute('ID_Organizacion');
            $userOrg = (int) $usuario->ID_Organizacion;
            if ($authOrg !== self::ID_ORGANIZACION_ADMIN && $authOrg !== $userOrg) {
                return response()->json(['success' => false, 'message' => 'Usuario fuera de tu organización'], 403);
            }

            $idGrupo = (int) ($usuario->ID_Grupo ?? 0);
            $permitidos = $this->menuIdsConAccesoUsuario($usuarioId);
            $items = $this->menusArbolOrg($userOrg, $permitidos);
            $mapa = $this->ordenService->mapaEfectivo($usuarioId, $idGrupo);
            $items = $this->aplicarMapaAItems($items, $mapa);
            $tieneOverride = DB::table('menu_orden_usuario')->where('ID_Usuario', $usuarioId)->exists();

            return response()->json([
                'success' => true,
                'data' => [
                    'ambito' => 'usuario',
                    'id_usuario' => $usuarioId,
                    'id_grupo' => $idGrupo,
                    'tiene_override' => $tieneOverride,
                    'items' => $items,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('MenuOrdenController@porUsuario: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error al cargar orden'], 500);
        }
    }

    /**
     * GET /api/panel-acceso/menu-orden/opciones?org_id=
     * Opciones listas para USelect ({ label, value }).
     */
    public function opciones(Request $request)
    {
        try {
            $authUser = auth()->user();
            $orgId = $this->resolverOrg($request, $authUser);
            if ($orgId === null) {
                return response()->json(['success' => false, 'message' => 'Organización inválida'], 422);
            }

            $authOrg = (int) $authUser->getAttribute('ID_Organizacion');
            $empresaId = (int) $authUser->getAttribute('ID_Empresa');

            $organizaciones = [];
            if ($authOrg === self::ID_ORGANIZACION_ADMIN) {
                $orgRows = DB::table('organizacion')
                    ->where('ID_Empresa', $empresaId)
                    ->where('Nu_Estado', 1)
                    ->orderBy('No_Organizacion')
                    ->get(['ID_Organizacion', 'No_Organizacion']);
                foreach ($orgRows as $o) {
                    $organizaciones[] = [
                        'label' => (string) $o->No_Organizacion,
                        'value' => (int) $o->ID_Organizacion,
                    ];
                }
            }

            $grupos = [];
            $grupoRows = DB::table('grupo')
                ->where('ID_Organizacion', $orgId)
                ->orderBy('No_Grupo')
                ->get(['ID_Grupo', 'No_Grupo']);
            foreach ($grupoRows as $g) {
                $grupos[] = [
                    'label' => (string) $g->No_Grupo,
                    'value' => (int) $g->ID_Grupo,
                ];
            }

            $usuarios = [];
            $userRows = DB::table('usuario AS U')
                ->leftJoin('grupo AS G', 'G.ID_Grupo', '=', 'U.ID_Grupo')
                ->where('U.ID_Organizacion', $orgId)
                ->where('U.Nu_Estado', 1)
                ->whereNull('U.ID_Entidad')
                ->orderBy('U.No_Nombres_Apellidos')
                ->limit(300)
                ->get([
                    'U.ID_Usuario',
                    'U.No_Usuario',
                    'U.No_Nombres_Apellidos',
                    'U.ID_Grupo',
                    'U.ID_Organizacion',
                    'G.No_Grupo',
                ]);
            foreach ($userRows as $u) {
                $nombre = trim((string) ($u->No_Nombres_Apellidos ?: $u->No_Usuario));
                $cargo = trim((string) ($u->No_Grupo ?: '—'));
                $usuarios[] = [
                    'label' => $nombre . ' (' . $cargo . ')',
                    'value' => (int) $u->ID_Usuario,
                    'id_grupo' => (int) ($u->ID_Grupo ?? 0),
                    'id_org' => (int) $u->ID_Organizacion,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'organizaciones' => $organizaciones,
                    'grupos' => $grupos,
                    'usuarios' => $usuarios,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('MenuOrdenController@opciones: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error al cargar opciones'], 500);
        }
    }

    /**
     * PUT /api/panel-acceso/menu-orden/grupo/{grupoId}
     * body: { items: [{ id, children: [...] }, ...] } — el back asigna orden.
     */
    public function guardarGrupo(Request $request, $grupoId)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer',
        ]);

        try {
            $authUser = auth()->user();
            $orgId = $this->resolverOrg($request, $authUser);
            if ($orgId === null) {
                return response()->json(['success' => false, 'message' => 'Organización inválida'], 422);
            }

            $grupoId = (int) $grupoId;
            $grupo = DB::table('grupo')->where('ID_Grupo', $grupoId)->first();
            if (!$grupo) {
                return response()->json(['success' => false, 'message' => 'Cargo no encontrado'], 404);
            }
            if ((int) $grupo->ID_Organizacion !== $orgId && (int) $authUser->getAttribute('ID_Organizacion') !== self::ID_ORGANIZACION_ADMIN) {
                return response()->json(['success' => false, 'message' => 'El cargo no pertenece a tu organización'], 403);
            }

            $this->reemplazarOrden('menu_orden_grupo', 'ID_Grupo', $grupoId, $this->aplanarArbol($request->input('items', [])));
            app(AuthMenuCacheService::class)->invalidateAll();

            return response()->json(['success' => true, 'message' => 'Orden del cargo guardado']);
        } catch (\Exception $e) {
            Log::error('MenuOrdenController@guardarGrupo: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error al guardar orden'], 500);
        }
    }

    /**
     * PUT /api/panel-acceso/menu-orden/usuario/{usuarioId}
     * body: { items: [{ id, children: [...] }, ...] }
     */
    public function guardarUsuario(Request $request, $usuarioId)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer',
        ]);

        try {
            $authUser = auth()->user();
            $usuarioId = (int) $usuarioId;
            $usuario = DB::table('usuario')->where('ID_Usuario', $usuarioId)->first();
            if (!$usuario) {
                return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
            }

            $authOrg = (int) $authUser->getAttribute('ID_Organizacion');
            if ($authOrg !== self::ID_ORGANIZACION_ADMIN && $authOrg !== (int) $usuario->ID_Organizacion) {
                return response()->json(['success' => false, 'message' => 'Usuario fuera de tu organización'], 403);
            }

            $this->reemplazarOrden('menu_orden_usuario', 'ID_Usuario', $usuarioId, $this->aplanarArbol($request->input('items', [])));
            app(AuthMenuCacheService::class)->invalidateAll();

            return response()->json(['success' => true, 'message' => 'Orden del usuario guardado (pisa el del cargo)']);
        } catch (\Exception $e) {
            Log::error('MenuOrdenController@guardarUsuario: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error al guardar orden'], 500);
        }
    }

    /**
     * DELETE /api/panel-acceso/menu-orden/usuario/{usuarioId}
     * Quita override: vuelve al orden del cargo.
     */
    public function limpiarUsuario($usuarioId)
    {
        try {
            $authUser = auth()->user();
            $usuarioId = (int) $usuarioId;
            $usuario = DB::table('usuario')->where('ID_Usuario', $usuarioId)->first();
            if (!$usuario) {
                return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
            }

            $authOrg = (int) $authUser->getAttribute('ID_Organizacion');
            if ($authOrg !== self::ID_ORGANIZACION_ADMIN && $authOrg !== (int) $usuario->ID_Organizacion) {
                return response()->json(['success' => false, 'message' => 'Usuario fuera de tu organización'], 403);
            }

            DB::table('menu_orden_usuario')->where('ID_Usuario', $usuarioId)->delete();
            app(AuthMenuCacheService::class)->invalidateAll();

            return response()->json(['success' => true, 'message' => 'Override de usuario eliminado']);
        } catch (\Exception $e) {
            Log::error('MenuOrdenController@limpiarUsuario: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error al limpiar orden'], 500);
        }
    }

    private function resolverOrg(Request $request, $authUser)
    {
        $authOrg = (int) $authUser->getAttribute('ID_Organizacion');
        $orgId = $authOrg;
        if ($authOrg === self::ID_ORGANIZACION_ADMIN) {
            $fromRequest = $request->input('org_id', $request->input('id_org'));
            if ($fromRequest !== null && $fromRequest !== '') {
                $orgId = (int) $fromRequest;
            }
        }

        return Organizacion::find($orgId) ? $orgId : null;
    }

    /**
     * IDs de menú con Nu_Consultar para algún usuario del cargo.
     *
     * @return array<int, int> mapa id => id
     */
    private function menuIdsConAccesoGrupo($grupoId)
    {
        $ids = DB::table('menu_acceso AS MA')
            ->join('grupo_usuario AS GU', 'GU.ID_Grupo_Usuario', '=', 'MA.ID_Grupo_Usuario')
            ->where('GU.ID_Grupo', (int) $grupoId)
            ->where('MA.Nu_Consultar', 1)
            ->distinct()
            ->pluck('MA.ID_Menu')
            ->map(function ($id) {
                return (int) $id;
            })
            ->all();

        return array_fill_keys($ids, true);
    }

    /**
     * IDs de menú con Nu_Consultar para el grupo_usuario del usuario.
     *
     * @return array<int, int> mapa id => id
     */
    private function menuIdsConAccesoUsuario($usuarioId)
    {
        $ids = DB::table('menu_acceso AS MA')
            ->join('grupo_usuario AS GU', 'GU.ID_Grupo_Usuario', '=', 'MA.ID_Grupo_Usuario')
            ->where('GU.ID_Usuario', (int) $usuarioId)
            ->where('MA.Nu_Consultar', 1)
            ->distinct()
            ->pluck('MA.ID_Menu')
            ->map(function ($id) {
                return (int) $id;
            })
            ->all();

        return array_fill_keys($ids, true);
    }

    /**
     * Menús activos de la org en árbol (solo los permitidos + ancestros necesarios).
     *
     * @param  array<int, bool>|null  $permitidos  null = todos
     * @return array<int, array<string, mixed>>
     */
    private function menusArbolOrg($orgId, $permitidos = null)
    {
        $rows = DB::table('menu')
            ->where('ID_Organizacion', (int) $orgId)
            ->where('Nu_Activo', 0)
            ->where('Nu_Seguridad', 0)
            ->orderBy('ID_Padre')
            ->orderBy('Nu_Orden')
            ->orderBy('ID_Menu')
            ->get([
                'ID_Menu',
                'ID_Padre',
                'No_Menu',
                'Nu_Orden',
                'url_intranet_v2',
                'show_father',
            ]);

        if (is_array($permitidos)) {
            if ($permitidos === []) {
                return [];
            }
            $byId = [];
            foreach ($rows as $row) {
                $byId[(int) $row->ID_Menu] = $row;
            }
            // Incluir ancestros para no romper el árbol del sidebar.
            $incluir = $permitidos;
            foreach (array_keys($permitidos) as $menuId) {
                $actual = $byId[$menuId] ?? null;
                while ($actual) {
                    $padreId = (int) $actual->ID_Padre;
                    if ($padreId <= 0 || isset($incluir[$padreId])) {
                        break;
                    }
                    $incluir[$padreId] = true;
                    $actual = $byId[$padreId] ?? null;
                }
            }
            $rows = $rows->filter(function ($row) use ($incluir) {
                return isset($incluir[(int) $row->ID_Menu]);
            })->values();
        }

        $byPadre = [];
        foreach ($rows as $row) {
            $byPadre[(int) $row->ID_Padre][] = $row;
        }

        $build = function ($padreId) use (&$build, &$byPadre) {
            $out = [];
            foreach ($byPadre[$padreId] ?? [] as $row) {
                $out[] = [
                    'id' => (int) $row->ID_Menu,
                    'id_padre' => (int) $row->ID_Padre,
                    'nombre' => $row->No_Menu,
                    'orden' => (int) $row->Nu_Orden,
                    'ruta' => $row->url_intranet_v2,
                    'show_father' => (int) $row->show_father,
                    'children' => $build((int) $row->ID_Menu),
                ];
            }

            return $out;
        };

        return $build(0);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, int>  $mapa
     * @return array<int, array<string, mixed>>
     */
    private function aplicarMapaAItems(array $items, array $mapa)
    {
        usort($items, function ($a, $b) use ($mapa) {
            $ordA = array_key_exists($a['id'], $mapa) ? $mapa[$a['id']] : (int) $a['orden'];
            $ordB = array_key_exists($b['id'], $mapa) ? $mapa[$b['id']] : (int) $b['orden'];
            if ($ordA === $ordB) {
                return $a['id'] - $b['id'];
            }

            return $ordA - $ordB;
        });

        $i = 1;
        foreach ($items as &$item) {
            $item['orden'] = $i++;
            $item['children'] = $this->aplicarMapaAItems($item['children'] ?? [], $mapa);
        }
        unset($item);

        return array_values($items);
    }

    /**
     * Árbol del front → filas planas { id_menu, orden } (hermanos por aparición).
     *
     * @param  array<int, mixed>  $items
     * @return array<int, array{id_menu: int, orden: int}>
     */
    private function aplanarArbol(array $items)
    {
        $out = [];
        $walk = function (array $list) use (&$walk, &$out) {
            $orden = 1;
            foreach ($list as $item) {
                if (!is_array($item) || !isset($item['id'])) {
                    continue;
                }
                $out[] = [
                    'id_menu' => (int) $item['id'],
                    'orden' => $orden++,
                ];
                $children = isset($item['children']) && is_array($item['children']) ? $item['children'] : [];
                if ($children !== []) {
                    $walk($children);
                }
            }
        };
        $walk($items);

        return $out;
    }

    /**
     * @param  array<int, array{id_menu: int, orden: int}>  $items
     */
    private function reemplazarOrden($tabla, $fk, $fkValue, array $items)
    {
        DB::transaction(function () use ($tabla, $fk, $fkValue, $items) {
            DB::table($tabla)->where($fk, (int) $fkValue)->delete();
            $now = now();
            $rows = [];
            foreach ($items as $item) {
                $rows[] = [
                    $fk => (int) $fkValue,
                    'ID_Menu' => (int) $item['id_menu'],
                    'Nu_Orden' => (int) $item['orden'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($rows !== []) {
                DB::table($tabla)->insert($rows);
            }
        });
    }
}
