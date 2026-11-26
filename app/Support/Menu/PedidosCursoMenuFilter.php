<?php

namespace App\Support\Menu;

use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * En rol Cotizador, Curso / Pedidos de Curso solo lo ven el jefe de ventas y los cotizadores autorizados.
 */
class PedidosCursoMenuFilter
{
    /** @var array<int, int> */
    private const MENU_IDS = [182, 183];

    /**
     * Cotizadores que gestionan la venta de curso (además del jefe de ventas).
     * 29409 = Kimberly (cotizaciones6), ticket CFG-26-0057.
     * 29170 = cuenta curso, mismo perfil, ticket CFG-26-0058.
     *
     * @var array<int, int>
     */
    private const COTIZADORES_CON_CURSO = [29409, 29170];

    public static function usuarioPuedeVerCurso(int $idUsuario): bool
    {
        return $idUsuario === (int) Usuario::ID_JEFE_VENTAS
            || in_array($idUsuario, self::COTIZADORES_CON_CURSO, true);
    }

    /**
     * @param  array<int, object>  $menus
     * @return array<int, object>
     */
    public static function apply(array $menus, int $idUsuario, int $idGrupo): array
    {
        if (self::usuarioPuedeVerCurso($idUsuario)) {
            return $menus;
        }

        $noGrupo = '';
        if ($idGrupo > 0) {
            $noGrupo = (string) (DB::table('grupo')->where('ID_Grupo', $idGrupo)->value('No_Grupo') ?? '');
        }

        if ($noGrupo === Usuario::ROL_RRHH) {
            return $menus;
        }

        if ($noGrupo !== Usuario::ROL_COTIZADOR) {
            return $menus;
        }

        $blocked = array_fill_keys(self::MENU_IDS, true);
        foreach (DB::table('menu')->whereIn('ID_Padre', self::MENU_IDS)->pluck('ID_Menu') as $cid) {
            $blocked[(int) $cid] = true;
        }

        $isBlocked = static function ($menu) use ($blocked): bool {
            $id = (int) ($menu->ID_Menu ?? 0);
            if ($id && isset($blocked[$id])) {
                return true;
            }
            $nombre = mb_strtolower(trim((string) ($menu->No_Menu ?? '')));
            $url = mb_strtolower(trim((string) ($menu->url_intranet_v2 ?? '')));
            if ($nombre === 'curso' || $nombre === 'pedidos de curso') {
                return true;
            }
            if ($url === 'curso' || str_starts_with($url, 'curso/') || str_starts_with($url, 'curso?')) {
                return true;
            }

            return false;
        };

        $menus = array_values(array_filter($menus, static function ($m) use ($isBlocked) {
            return !$isBlocked($m);
        }));

        foreach ($menus as $idx => $padre) {
            if (empty($padre->Hijos) || !is_array($padre->Hijos)) {
                continue;
            }
            $padre->Hijos = array_values(array_filter($padre->Hijos, static function ($h) use ($isBlocked) {
                return !$isBlocked($h);
            }));
            foreach ($padre->Hijos as $h) {
                if (empty($h->SubHijos) || !is_array($h->SubHijos)) {
                    continue;
                }
                $h->SubHijos = array_values(array_filter($h->SubHijos, static function ($sh) use ($isBlocked) {
                    return !$isBlocked($sh);
                }));
            }
            $menus[$idx] = $padre;
        }

        return $menus;
    }
}
