<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateSoporteTiAreasTables extends Migration
{
    public function up()
    {
        Schema::create('soporte_ti_areas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre', 80);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique('nombre');
        });

        Schema::create('soporte_ti_area_grupo', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('area_id');
            $table->unsignedInteger('grupo_id');
            $table->timestamps();
            $table->unique(array('area_id', 'grupo_id'));
            $table->unique('grupo_id');
            $table->foreign('area_id')->references('id')->on('soporte_ti_areas')->onDelete('cascade');
        });

        $now = now();
        $nombres = array(
            'Ventas',
            'Importaciones',
            'Marketing',
            'Administración y Finanzas',
            'RR.HH',
            'CEO',
        );

        $areaIds = array();
        foreach ($nombres as $i => $nombre) {
            $areaIds[$nombre] = (int) DB::table('soporte_ti_areas')->insertGetId(array(
                'nombre' => $nombre,
                'orden' => $i + 1,
                'activo' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ));
        }

        $rolPorArea = array(
            'Ventas' => array('Cotizador', 'Comercial', 'Asistente Comercial'),
            'Importaciones' => array('Coordinación', 'Jefe Importacion', 'Documentacion', 'ContenedorAlmacen'),
            'Marketing' => array('Marketing', 'Jefe Marketing'),
            'Administración y Finanzas' => array('Administración', 'Finanzas', 'Contabilidad', 'SUB_ADMINISTRACION'),
            'RR.HH' => array('RR.HH', 'RRHH', 'Recursos Humanos'),
            'CEO' => array('GERENCIA', 'CEO'),
        );

        foreach ($rolPorArea as $areaNombre => $roles) {
            $areaId = isset($areaIds[$areaNombre]) ? $areaIds[$areaNombre] : 0;
            if ($areaId <= 0) {
                continue;
            }
            $grupos = DB::table('grupo')
                ->whereIn('No_Grupo', $roles)
                ->where('Nu_Estado', 1)
                ->pluck('ID_Grupo');
            foreach ($grupos as $grupoId) {
                $ya = DB::table('soporte_ti_area_grupo')->where('grupo_id', (int) $grupoId)->exists();
                if ($ya) {
                    continue;
                }
                DB::table('soporte_ti_area_grupo')->insert(array(
                    'area_id' => $areaId,
                    'grupo_id' => (int) $grupoId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ));
            }
        }

        $this->insertarMenuSidebar();
    }

    /**
     * Ítem "Áreas" bajo Soporte TI, visible para Soporte / PM / Administración / GERENCIA.
     */
    protected function insertarMenuSidebar()
    {
        $exists = DB::table('menu')->where('url_intranet_v2', 'soporte-ti/areas')->exists();
        if ($exists) {
            return;
        }

        $padre = DB::table('menu')
            ->where('url_intranet_v2', 'soporte-ti')
            ->orWhere('No_Menu', 'Soporte TI')
            ->orWhere('No_Menu', 'Soporte')
            ->orderBy('ID_Padre')
            ->first();

        $padreId = 0;
        if ($padre) {
            $padreId = (int) $padre->ID_Menu;
            DB::table('menu')->where('ID_Menu', $padreId)->update(array('show_father' => 1));
        }

        $maxOrden = (int) DB::table('menu')->where('ID_Padre', $padreId)->max('Nu_Orden');

        $menuId = DB::table('menu')->insertGetId(array(
            'ID_Padre' => $padreId,
            'Nu_Orden' => $maxOrden + 1,
            'No_Menu' => 'Áreas',
            'No_Menu_Url' => 'soporte-ti/areas',
            'No_Class_Controller' => 'SoporteTiAreaController',
            'Txt_Css_Icons' => 'fa fa-sitemap',
            'Nu_Separador' => 0,
            'Nu_Seguridad' => 0,
            'Nu_Activo' => 0,
            'Nu_Tipo_Sistema' => 0,
            'Txt_Url_Video' => null,
            'No_Menu_China' => 'Areas',
            'show_father' => 1,
            'url_intranet_v2' => 'soporte-ti/areas',
        ));

        $rolesMenu = array('Soporte', 'PM', 'Administración', 'GERENCIA', 'Admin');

        $accesos = DB::table('menu_acceso as ma')
            ->join('grupo_usuario as gu', 'gu.ID_Grupo_Usuario', '=', 'ma.ID_Grupo_Usuario')
            ->join('grupo as g', 'g.ID_Grupo', '=', 'gu.ID_Grupo')
            ->whereIn('g.No_Grupo', $rolesMenu)
            ->when($padreId > 0, function ($q) use ($padreId) {
                return $q->where('ma.ID_Menu', $padreId);
            })
            ->select('ma.ID_Empresa', 'ma.ID_Grupo_Usuario', 'ma.Nu_Consultar', 'ma.Nu_Agregar', 'ma.Nu_Editar', 'ma.Nu_Eliminar')
            ->distinct()
            ->get();

        if ($accesos->isEmpty()) {
            $accesos = DB::table('grupo_usuario as gu')
                ->join('grupo as g', 'g.ID_Grupo', '=', 'gu.ID_Grupo')
                ->whereIn('g.No_Grupo', $rolesMenu)
                ->select('gu.ID_Empresa', 'gu.ID_Grupo_Usuario')
                ->get()
                ->map(function ($row) {
                    $row->Nu_Consultar = 1;
                    $row->Nu_Agregar = 1;
                    $row->Nu_Editar = 1;
                    $row->Nu_Eliminar = 1;

                    return $row;
                });
        }

        foreach ($accesos as $row) {
            $already = DB::table('menu_acceso')
                ->where('ID_Menu', $menuId)
                ->where('ID_Grupo_Usuario', $row->ID_Grupo_Usuario)
                ->exists();
            if ($already) {
                continue;
            }
            DB::table('menu_acceso')->insert(array(
                'ID_Empresa' => $row->ID_Empresa ?: 1,
                'ID_Menu' => $menuId,
                'ID_Grupo_Usuario' => $row->ID_Grupo_Usuario,
                'Nu_Consultar' => 1,
                'Nu_Agregar' => isset($row->Nu_Agregar) ? $row->Nu_Agregar : 1,
                'Nu_Editar' => isset($row->Nu_Editar) ? $row->Nu_Editar : 1,
                'Nu_Eliminar' => isset($row->Nu_Eliminar) ? $row->Nu_Eliminar : 1,
            ));
        }
    }

    public function down()
    {
        $menuId = DB::table('menu')->where('url_intranet_v2', 'soporte-ti/areas')->value('ID_Menu');
        if ($menuId) {
            DB::table('menu_acceso')->where('ID_Menu', $menuId)->delete();
            DB::table('menu')->where('ID_Menu', $menuId)->delete();
        }

        Schema::dropIfExists('soporte_ti_area_grupo');
        Schema::dropIfExists('soporte_ti_areas');
    }
}
