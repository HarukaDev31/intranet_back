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

            // Obtener ID_Grupo_Usuario
            $grupoUsuario = DB::selectOne(
                'SELECT ID_Grupo_Usuario FROM grupo_usuario WHERE ID_Empresa = ? AND ID_Organizacion = ? AND ID_Grupo = ? LIMIT 1',
                [$request->id_empresa, $request->id_org, $request->id_grupo]
            );

            if (!$grupoUsuario) {
                return response()->json(['success' => false, 'message' => 'No se encontró el grupo de usuario'], 422);
            }

            $idGrupoUsuario = $grupoUsuario->ID_Grupo_Usuario;

            DB::beginTransaction();

            // Limpiar permisos anteriores
            DB::table('menu_acceso')->where('ID_Grupo_Usuario', $idGrupoUsuario)->delete();

            // Insertar menús seleccionados (con padres y abuelos automáticos)
            $this->insertarMenusConJerarquia($request->id_empresa, $idGrupoUsuario, $request->menus);

            // Insertar menús de seguridad automáticos según el nombre del grupo
            $this->insertarMenusSeguridad($request->id_empresa, $idGrupoUsuario, $request->id_grupo);

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Permisos guardados exitosamente']);
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
        $EID_Menu_Padre     = '';
        $EID_Menu_Sub_Padre = '';

        foreach ($menus as $idMenu => $crud) {
            // Insertar menú abuelo si existe
            $abuelo = DB::selectOne(
                'SELECT COUNT(*) AS existe FROM menu WHERE ID_Menu = (SELECT ID_Padre FROM menu WHERE ID_Menu = (SELECT ID_Padre FROM menu WHERE ID_Menu = ? LIMIT 1) LIMIT 1) LIMIT 1',
                [$idMenu]
            );

            if ($abuelo && $abuelo->existe > 0) {
                $rowAbuelo = DB::selectOne(
                    'SELECT ID_Menu FROM menu WHERE ID_Menu = (SELECT ID_Padre FROM menu WHERE ID_Menu = (SELECT ID_Padre FROM menu WHERE ID_Menu = ? LIMIT 1) LIMIT 1) LIMIT 1',
                    [$idMenu]
                );
                $idAbuelo = $rowAbuelo->ID_Menu;

                if ($EID_Menu_Padre != $idAbuelo) {
                    $yaExiste = DB::selectOne(
                        'SELECT COUNT(*) AS existe FROM menu_acceso WHERE ID_Grupo_Usuario = ? AND ID_Menu = ?',
                        [$idGrupoUsuario, $idAbuelo]
                    );
                    if ($yaExiste->existe == 0) {
                        DB::table('menu_acceso')->insert([
                            'ID_Empresa'      => $idEmpresa,
                            'ID_Menu'         => $idAbuelo,
                            'ID_Grupo_Usuario'=> $idGrupoUsuario,
                            'Nu_Consultar'    => 1,
                            'Nu_Agregar'      => 1,
                            'Nu_Editar'       => 1,
                            'Nu_Eliminar'     => 1,
                        ]);
                    }
                    $EID_Menu_Padre = $idAbuelo;
                }
            }

            // Insertar menú padre si existe
            $padre = DB::selectOne(
                'SELECT COUNT(*) AS existe FROM menu WHERE ID_Menu = (SELECT ID_Padre FROM menu WHERE ID_Menu = ? LIMIT 1) LIMIT 1',
                [$idMenu]
            );

            if ($padre && $padre->existe > 0) {
                $rowPadre = DB::selectOne(
                    'SELECT ID_Menu FROM menu WHERE ID_Menu = (SELECT ID_Padre FROM menu WHERE ID_Menu = ? LIMIT 1) LIMIT 1',
                    [$idMenu]
                );
                $idPadre = $rowPadre->ID_Menu;

                if ($EID_Menu_Sub_Padre != $idPadre) {
                    $yaExiste = DB::selectOne(
                        'SELECT COUNT(*) AS existe FROM menu_acceso WHERE ID_Grupo_Usuario = ? AND ID_Menu = ?',
                        [$idGrupoUsuario, $idPadre]
                    );
                    if ($yaExiste->existe == 0) {
                        DB::table('menu_acceso')->insert([
                            'ID_Empresa'      => $idEmpresa,
                            'ID_Menu'         => $idPadre,
                            'ID_Grupo_Usuario'=> $idGrupoUsuario,
                            'Nu_Consultar'    => 1,
                            'Nu_Agregar'      => 1,
                            'Nu_Editar'       => 1,
                            'Nu_Eliminar'     => 1,
                        ]);
                    }
                    $EID_Menu_Sub_Padre = $idPadre;
                }
            }

            // Insertar el menú hijo con sus permisos
            DB::table('menu_acceso')->insert([
                'ID_Empresa'      => $idEmpresa,
                'ID_Menu'         => $idMenu,
                'ID_Grupo_Usuario'=> $idGrupoUsuario,
                'Nu_Consultar'    => isset($crud['Nu_Consultar']) ? 1 : 0,
                'Nu_Agregar'      => isset($crud['Nu_Agregar'])   ? 1 : 0,
                'Nu_Editar'       => isset($crud['Nu_Editar'])    ? 1 : 0,
                'Nu_Eliminar'     => isset($crud['Nu_Eliminar'])  ? 1 : 0,
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
