<?php

namespace App\Http\Controllers\PanelAcceso;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MenuAccesoController extends Controller
{
    /**
     * Obtener menú de acceso para un grupo específico.
     * GET /api/panel-acceso/menu-acceso/{empresaId}/{orgId}/{grupoId}
     */
    public function getMenuPorGrupo($empresaId, $orgId, $grupoId)
    {
        try {
            // Verificar que el grupo tenga usuarios
            $tieneUsuarios = DB::selectOne(
                'SELECT COUNT(*) AS existe FROM usuario WHERE ID_Empresa = ? AND ID_Organizacion = ? AND ID_Grupo = ? LIMIT 1',
                [$empresaId, $orgId, $grupoId]
            );

            if ($tieneUsuarios->existe == 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'El cargo no tiene usuario(s) asignado(s)',
                ], 422);
            }

            $query = "SELECT DISTINCT
                MNU.ID_Padre,
                MNU.ID_Menu,
                MNU.No_Menu,
                MNU.Txt_Url_Video,
                MNUACCESS.ID_Grupo,
                MNUACCESS.Nu_Consultar,
                MNUACCESS.Nu_Agregar,
                MNUACCESS.Nu_Editar,
                MNUACCESS.Nu_Eliminar
            FROM menu AS MNU
            LEFT JOIN (
                SELECT DISTINCT
                    MNUACCESS.ID_Menu,
                    GRPUSR.ID_Grupo,
                    MNUACCESS.Nu_Consultar,
                    MNUACCESS.Nu_Agregar,
                    MNUACCESS.Nu_Editar,
                    MNUACCESS.Nu_Eliminar
                FROM menu_acceso AS MNUACCESS
                JOIN grupo_usuario AS GRPUSR ON (GRPUSR.ID_Grupo_Usuario = MNUACCESS.ID_Grupo_Usuario)
                WHERE MNUACCESS.ID_Empresa = ?
                AND GRPUSR.ID_Grupo = ?
            ) AS MNUACCESS ON (MNUACCESS.ID_Menu = MNU.ID_Menu)
            LEFT JOIN (
                SELECT
                    MNU.ID_Menu AS ID_Menu_Sub_Padre,
                    (SELECT COUNT(*) FROM menu WHERE Nu_Seguridad = 0 AND ID_Padre = MNU.ID_Menu) AS Nu_Cantidad_Menu_Hijos
                FROM menu AS MNU
                INNER JOIN menu_acceso AS MNUACCESS ON (MNU.ID_Menu = MNUACCESS.ID_Menu)
                WHERE MNU.Nu_Seguridad = 0
                AND MNU.Nu_Activo = 0
                AND MNU.Nu_Tipo_Sistema = 0
            ) AS MNUSUBPADRE ON (MNUSUBPADRE.ID_Menu_Sub_Padre = MNU.ID_Menu)
            WHERE MNU.ID_Padre > 0
            AND (MNUSUBPADRE.Nu_Cantidad_Menu_Hijos = 0 OR MNUSUBPADRE.Nu_Cantidad_Menu_Hijos IS NULL)
            AND MNU.Nu_Seguridad = 0
            AND MNU.Nu_Activo = 0
            AND MNU.Nu_Tipo_Sistema = 0";

            $arrData = DB::select($query, [$empresaId, $grupoId]);

            foreach ($arrData as &$row) {
                $row->No_Menu_Padre = $this->getMenuPadreNombre($row->ID_Padre);
            }

            return response()->json([
                'success' => true,
                'data'    => $arrData,
            ]);
        } catch (\Exception $e) {
            Log::error('MenuAccesoController@getMenuPorGrupo: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al obtener permisos'], 500);
        }
    }

    /**
     * Guardar permisos de menú para un grupo.
     * POST /api/panel-acceso/menu-acceso
     */
    public function guardarPermisos(Request $request)
    {
        try {
            $request->validate([
                'id_empresa'    => 'required|integer',
                'id_org'        => 'required|integer',
                'id_grupo'      => 'required|integer',
                'menus'         => 'required|array',
            ]);

            $menuIds = array_map('intval', array_keys($request->menus ?? []));
            $requestedPerms = $request->menus ?? [];

            // Obtener TODOS los ID_Grupo_Usuario que correspondan (evitar LIMIT 1, que puede afectar a otro rol/org)
            $grupoUsuarios = DB::select(
                'SELECT ID_Grupo_Usuario FROM grupo_usuario WHERE ID_Empresa = ? AND ID_Organizacion = ? AND ID_Grupo = ?',
                [$request->id_empresa, $request->id_org, $request->id_grupo]
            );

            if (!$grupoUsuarios || count($grupoUsuarios) === 0) {
                return response()->json(['success' => false, 'message' => 'No se encontró el grupo de usuario'], 422);
            }

            $grupoUsuariosIds = array_values(array_filter(array_map(function ($g) {
                return (int) ($g->ID_Grupo_Usuario ?? 0);
            }, $grupoUsuarios)));

            // Usuarios afectados (informativo)
            $usuariosAfectados = DB::table('grupo_usuario')
                ->whereIn('ID_Grupo_Usuario', $grupoUsuariosIds)
                ->pluck('ID_Usuario')
                ->values()
                ->toArray();

            Log::info('menu_acceso.guardar_permisos.inicio', [
                'id_empresa' => (int) $request->id_empresa,
                'id_org' => (int) $request->id_org,
                'id_grupo' => (int) $request->id_grupo,
                'roles_grupo_usuario' => $grupoUsuariosIds,
                'usuarios_afectados' => $usuariosAfectados,
                'menu_ids' => $menuIds,
                'nuevos_permisos' => $requestedPerms,
            ]);

            DB::beginTransaction();

            foreach ($grupoUsuarios as $g) {
                $idGrupoUsuario = (int) ($g->ID_Grupo_Usuario ?? 0);
                if (!$idGrupoUsuario) continue;

                $oldRows = [];
                if (!empty($menuIds)) {
                    $oldRows = DB::table('menu_acceso')
                        ->where('ID_Grupo_Usuario', $idGrupoUsuario)
                        ->whereIn('ID_Menu', $menuIds)
                        ->select('ID_Menu', 'Nu_Consultar', 'Nu_Agregar', 'Nu_Editar', 'Nu_Eliminar')
                        ->orderBy('ID_Menu')
                        ->get()
                        ->toArray();
                }

                $oldCount = DB::table('menu_acceso')->where('ID_Grupo_Usuario', $idGrupoUsuario)->count();

                // Limpiar permisos anteriores (solo para ese grupo_usuario)
                DB::table('menu_acceso')->where('ID_Grupo_Usuario', $idGrupoUsuario)->delete();

                // Insertar menús seleccionados (con padres y abuelos automáticos)
                $this->insertarMenusConJerarquia($request->id_empresa, $idGrupoUsuario, $request->menus);

                // Insertar menús de seguridad automáticos según el nombre del grupo
                $this->insertarMenusSeguridad($request->id_empresa, $idGrupoUsuario, $request->id_grupo);

                $newRows = [];
                if (!empty($menuIds)) {
                    $newRows = DB::table('menu_acceso')
                        ->where('ID_Grupo_Usuario', $idGrupoUsuario)
                        ->whereIn('ID_Menu', $menuIds)
                        ->select('ID_Menu', 'Nu_Consultar', 'Nu_Agregar', 'Nu_Editar', 'Nu_Eliminar')
                        ->orderBy('ID_Menu')
                        ->get()
                        ->toArray();
                }
                $newCount = DB::table('menu_acceso')->where('ID_Grupo_Usuario', $idGrupoUsuario)->count();

                Log::info('menu_acceso.guardar_permisos.resultado', [
                    'id_grupo_usuario' => $idGrupoUsuario,
                    'id_empresa' => (int) $request->id_empresa,
                    'id_org' => (int) $request->id_org,
                    'id_grupo' => (int) $request->id_grupo,
                    'antiguos' => [
                        'count_total' => $oldCount,
                        'rows_menus_enviados' => $oldRows,
                    ],
                    'nuevos' => [
                        'count_total' => $newCount,
                        'rows_menus_enviados' => $newRows,
                    ],
                ]);
            }

            DB::commit();

            Log::info('menu_acceso.guardar_permisos.fin', [
                'id_empresa' => (int) $request->id_empresa,
                'id_org' => (int) $request->id_org,
                'id_grupo' => (int) $request->id_grupo,
                'updated_groups' => count($grupoUsuarios),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Permisos guardados exitosamente',
                'updated_groups' => count($grupoUsuarios),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('MenuAccesoController@guardarPermisos: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al guardar permisos'], 500);
        }
    }

    /**
     * Inserta menús con jerarquía (padre + abuelo automáticos).
     */
    private function insertarMenusConJerarquia(int $idEmpresa, int $idGrupoUsuario, array $menus): void
    {
        $inserted = [];

        foreach ($menus as $idMenu => $crud) {
            $idMenu = (int) $idMenu;
            if ($idMenu <= 0) continue;

            // Si el menú no existe en catálogo, no podemos asignarlo (evita FK error).
            $menuExiste = DB::table('menu')->where('ID_Menu', $idMenu)->exists();
            if (!$menuExiste) {
                Log::warning('menu_acceso.guardar_permisos.menu_inexistente', [
                    'id_grupo_usuario' => $idGrupoUsuario,
                    'id_empresa' => $idEmpresa,
                    'id_menu' => $idMenu,
                ]);
                continue;
            }

            // Insertar toda la cadena de ancestros hasta la raíz (ID_Padre = 0),
            // porque el login construye el árbol desde los padres raíz.
            $currentId = $idMenu;
            $guard = 0;
            while ($currentId > 0 && $guard < 25) {
                $guard++;

                if (!isset($inserted[$currentId])) {
                    // Si el ancestro no existe en `menu`, cortar la cadena (evita FK error).
                    $existeEnCatalogo = DB::table('menu')->where('ID_Menu', $currentId)->exists();
                    if (!$existeEnCatalogo) {
                        Log::warning('menu_acceso.guardar_permisos.ancestro_inexistente', [
                            'id_grupo_usuario' => $idGrupoUsuario,
                            'id_empresa' => $idEmpresa,
                            'id_menu_origen' => $idMenu,
                            'id_menu_ancestro' => $currentId,
                        ]);
                        break;
                    }

                    $yaExiste = DB::table('menu_acceso')
                        ->where('ID_Grupo_Usuario', $idGrupoUsuario)
                        ->where('ID_Menu', $currentId)
                        ->exists();

                    if (!$yaExiste) {
                        // Para ancestros, dar permisos completos para que el nodo sea visible/navegable.
                        DB::table('menu_acceso')->insert([
                            'ID_Empresa'       => $idEmpresa,
                            'ID_Menu'          => $currentId,
                            'ID_Grupo_Usuario' => $idGrupoUsuario,
                            'Nu_Consultar'     => 1,
                            'Nu_Agregar'       => 1,
                            'Nu_Editar'        => 1,
                            'Nu_Eliminar'      => 1,
                        ]);
                    }

                    $inserted[$currentId] = true;
                }

                $padreId = (int) (DB::table('menu')->where('ID_Menu', $currentId)->value('ID_Padre') ?? 0);
                if ($padreId <= 0) {
                    break;
                }

                // Si hubiera ciclos corruptos en datos, evitamos loop infinito.
                if ($padreId === $currentId) {
                    break;
                }
                $currentId = $padreId;
            }

            // Finalmente, actualizar el menú seleccionado con sus permisos reales (no 1/1/1/1).
            DB::table('menu_acceso')
                ->where('ID_Grupo_Usuario', $idGrupoUsuario)
                ->where('ID_Menu', $idMenu)
                ->update([
                    // Nota: el frontend a veces envía flags explícitos en false.
                    // `isset()` sería true aunque el valor sea false, así que usamos truthiness real.
                    'Nu_Consultar' => !empty($crud['Nu_Consultar']) ? 1 : 0,
                    'Nu_Agregar'   => !empty($crud['Nu_Agregar'])   ? 1 : 0,
                    'Nu_Editar'    => !empty($crud['Nu_Editar'])    ? 1 : 0,
                    'Nu_Eliminar'  => !empty($crud['Nu_Eliminar'])  ? 1 : 0,
                ]);
        }
    }

    /**
     * Insertar menús de seguridad automáticos según el nombre del grupo.
     * Replica la lógica del legacy PermisoUsuarioModel::addMenuAccesoSeguridad().
     */
    private function insertarMenusSeguridad(int $idEmpresa, int $idGrupoUsuario, int $idGrupo): void
    {
        $grupoRow = DB::selectOne(
            'SELECT No_Grupo FROM grupo_usuario AS GU JOIN grupo AS G ON G.ID_Grupo = GU.ID_Grupo WHERE GU.ID_Grupo_Usuario = ? LIMIT 1',
            [$idGrupoUsuario]
        );

        if (!$grupoRow) return;

        $nombreGrupo = strtoupper($grupoRow->No_Grupo);

        $esGrupoPrivilegiado = in_array($nombreGrupo, [
            'GERENCIA', 'GERENTE GENERAL', 'GERENTE', 'SISTEMAS',
            'DUEÑO', 'SOCIOS', 'FUNDADOR', 'FUNDADORES', 'ASESORA COMERCIAL',
        ]);

        if ($esGrupoPrivilegiado) {
            // Menús del módulo Configuración: 8=Usuarios, 57=Cargo/Grupo, 58=Usuario, 59=Opciones menú, 2=Padre Config
            $menusSeguridadConf = [
                ['ID_Menu' => 8,  'Nu_Consultar' => 1, 'Nu_Agregar' => 1, 'Nu_Editar' => 1, 'Nu_Eliminar' => 1],
                ['ID_Menu' => 57, 'Nu_Consultar' => 1, 'Nu_Agregar' => 1, 'Nu_Editar' => 1, 'Nu_Eliminar' => 0],
                ['ID_Menu' => 58, 'Nu_Consultar' => 1, 'Nu_Agregar' => 1, 'Nu_Editar' => 1, 'Nu_Eliminar' => 1],
                ['ID_Menu' => 59, 'Nu_Consultar' => 1, 'Nu_Agregar' => 1, 'Nu_Editar' => 1, 'Nu_Eliminar' => 1],
                ['ID_Menu' => 2,  'Nu_Consultar' => 1, 'Nu_Agregar' => 1, 'Nu_Editar' => 1, 'Nu_Eliminar' => 1],
            ];

            foreach ($menusSeguridadConf as $m) {
                DB::table('menu_acceso')->insert(array_merge($m, [
                    'ID_Empresa'       => $idEmpresa,
                    'ID_Grupo_Usuario' => $idGrupoUsuario,
                ]));
            }

            // Menús: Escritorio(1), Empresa(9), Org(10), Sistema(11), Formato(25), Almacén
            $menusLectura1 = DB::select('SELECT ID_Menu FROM menu WHERE ID_Menu IN (1,9,10,11,25)');
            foreach ($menusLectura1 as $m) {
                DB::table('menu_acceso')->insert([
                    'ID_Empresa'       => $idEmpresa,
                    'ID_Menu'          => $m->ID_Menu,
                    'ID_Grupo_Usuario' => $idGrupoUsuario,
                    'Nu_Consultar'     => 1,
                    'Nu_Agregar'       => 0,
                    'Nu_Editar'        => 1,
                    'Nu_Eliminar'      => 0,
                ]);
            }

            // Menús de catálogo: Moneda(12), Pais(13), Depto(14), Provincia(15), Distrito(16), etc.
            $menusLectura2 = DB::select('SELECT ID_Menu FROM menu WHERE ID_Menu IN (12,13,14,15,16,17,18,85,86,87)');
            foreach ($menusLectura2 as $m) {
                DB::table('menu_acceso')->insert([
                    'ID_Empresa'       => $idEmpresa,
                    'ID_Menu'          => $m->ID_Menu,
                    'ID_Grupo_Usuario' => $idGrupoUsuario,
                    'Nu_Consultar'     => 1,
                    'Nu_Agregar'       => 0,
                    'Nu_Editar'        => 0,
                    'Nu_Eliminar'      => 0,
                ]);
            }
        } else {
            // Solo Escritorio para el resto
            $menusBase = DB::select('SELECT ID_Menu FROM menu WHERE ID_Menu IN (1)');
            foreach ($menusBase as $m) {
                DB::table('menu_acceso')->insert([
                    'ID_Empresa'       => $idEmpresa,
                    'ID_Menu'          => $m->ID_Menu,
                    'ID_Grupo_Usuario' => $idGrupoUsuario,
                    'Nu_Consultar'     => 1,
                    'Nu_Agregar'       => 0,
                    'Nu_Editar'        => 1,
                    'Nu_Eliminar'      => 0,
                ]);
            }
        }
    }

    /**
     * Obtener nombre del menú padre (para mostrar agrupación en la tabla).
     */
    private function getMenuPadreNombre(int $id): string
    {
        $rowPadre = DB::selectOne('SELECT ID_Padre, No_Menu FROM menu WHERE ID_Menu = ? LIMIT 1', [$id]);
        if (!$rowPadre) return '';

        $nombre = $rowPadre->No_Menu;

        if ($id > 0) {
            $rowAbuelo = DB::selectOne('SELECT No_Menu FROM menu WHERE ID_Menu = ? LIMIT 1', [$rowPadre->ID_Padre]);
            if ($rowAbuelo) {
                $nombre = $rowAbuelo->No_Menu . ' > ' . $nombre;
            }
        }

        return $nombre;
    }
}
