<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('menu')->where('url_intranet_v2', 'pago-proveedor/cotizaciones')->exists()) {
            return;
        }

        $carga = DB::table('menu')
            ->where('ID_Padre', 0)
            ->where('No_Menu', 'like', 'Carga Consolidada%')
            ->orderBy('Nu_Orden')
            ->first();

        if ($carga) {
            $orden = (int) $carga->Nu_Orden;
            DB::table('menu')
                ->where('ID_Padre', 0)
                ->where('Nu_Orden', '>=', $orden)
                ->increment('Nu_Orden');
        } else {
            $orden = ((int) DB::table('menu')->where('ID_Padre', 0)->max('Nu_Orden')) + 1;
        }

        $padreId = DB::table('menu')->insertGetId([
            'ID_Padre' => 0,
            'Nu_Orden' => $orden,
            'No_Menu' => 'Pago a Proveedor',
            'No_Menu_Url' => '#',
            'No_Class_Controller' => '',
            'Txt_Css_Icons' => 'fa fa-money',
            'Nu_Separador' => 0,
            'Nu_Seguridad' => 0,
            'Nu_Activo' => 0,
            'Nu_Tipo_Sistema' => 0,
            'Txt_Url_Video' => null,
            'No_Menu_China' => 'Supplier Payment',
            'show_father' => 1,
            'url_intranet_v2' => null,
        ]);

        $cotizacionesId = DB::table('menu')->insertGetId($this->hijo(
            $padreId,
            1,
            'Cotizaciones',
            'pago-proveedor/cotizaciones'
        ));
        $solicitudesId = DB::table('menu')->insertGetId($this->hijo(
            $padreId,
            2,
            'Solicitudes',
            'pago-proveedor/solicitudes'
        ));

        $this->conceder([$padreId, $cotizacionesId, $solicitudesId], ['Cotizador', 'Admin']);
    }

    public function down(): void
    {
        $ids = DB::table('menu')
            ->where('No_Menu', 'Pago a Proveedor')
            ->where('ID_Padre', 0)
            ->pluck('ID_Menu');

        $hijos = DB::table('menu')
            ->whereIn('url_intranet_v2', ['pago-proveedor/cotizaciones', 'pago-proveedor/solicitudes'])
            ->pluck('ID_Menu');

        $todos = $ids->merge($hijos)->unique()->values();
        if ($todos->isEmpty()) {
            return;
        }

        DB::table('menu_acceso')->whereIn('ID_Menu', $todos)->delete();
        DB::table('menu')->whereIn('ID_Padre', $ids)->delete();
        DB::table('menu')->whereIn('ID_Menu', $todos)->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function hijo(int $padreId, int $orden, string $nombre, string $url): array
    {
        return [
            'ID_Padre' => $padreId,
            'Nu_Orden' => $orden,
            'No_Menu' => $nombre,
            'No_Menu_Url' => $url,
            'No_Class_Controller' => 'PagoProveedorCotizacionController',
            'Txt_Css_Icons' => 'fa fa-file-text-o',
            'Nu_Separador' => 0,
            'Nu_Seguridad' => 0,
            'Nu_Activo' => 0,
            'Nu_Tipo_Sistema' => 0,
            'Txt_Url_Video' => null,
            'No_Menu_China' => null,
            'show_father' => 1,
            'url_intranet_v2' => $url,
        ];
    }

    /**
     * @param  array<int, int>  $menuIds
     * @param  array<int, string>  $grupos
     */
    private function conceder(array $menuIds, array $grupos): void
    {
        $filas = DB::table('grupo')
            ->whereIn('No_Grupo', $grupos)
            ->get(['ID_Grupo', 'ID_Empresa']);

        foreach ($filas as $grupo) {
            $representante = DB::table('grupo_usuario')
                ->where('ID_Grupo', $grupo->ID_Grupo)
                ->orderBy('ID_Grupo_Usuario')
                ->value('ID_Grupo_Usuario');

            if (!$representante) {
                continue;
            }

            foreach ($menuIds as $menuId) {
                $existe = DB::table('menu_acceso')
                    ->where('ID_Menu', $menuId)
                    ->where('ID_Grupo_Usuario', $representante)
                    ->exists();
                if ($existe) {
                    continue;
                }

                DB::table('menu_acceso')->insert([
                    'ID_Empresa' => $grupo->ID_Empresa ?: 1,
                    'ID_Menu' => $menuId,
                    'ID_Grupo_Usuario' => $representante,
                    'Nu_Consultar' => 1,
                    'Nu_Agregar' => 1,
                    'Nu_Editar' => 1,
                    'Nu_Eliminar' => 1,
                ]);
            }
        }
    }
};
