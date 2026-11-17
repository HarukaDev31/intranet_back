<?php

namespace App\Services\PanelAcceso;

use Illuminate\Support\Facades\DB;

/**
 * Orden del sidebar: usuario pisa cargo; si no hay override, menu.Nu_Orden.
 */
class MenuOrdenService
{
    /**
     * Mapa ID_Menu => Nu_Orden efectivo para un usuario.
     *
     * @return array<int, int>
     */
    public function mapaEfectivo($idUsuario, $idGrupo)
    {
        $mapa = [];

        if ($idGrupo) {
            $rows = DB::table('menu_orden_grupo')
                ->where('ID_Grupo', (int) $idGrupo)
                ->get(['ID_Menu', 'Nu_Orden']);
            foreach ($rows as $row) {
                $mapa[(int) $row->ID_Menu] = (int) $row->Nu_Orden;
            }
        }

        if ($idUsuario) {
            $rows = DB::table('menu_orden_usuario')
                ->where('ID_Usuario', (int) $idUsuario)
                ->get(['ID_Menu', 'Nu_Orden']);
            foreach ($rows as $row) {
                $mapa[(int) $row->ID_Menu] = (int) $row->Nu_Orden;
            }
        }

        return $mapa;
    }

    /**
     * Reordena el árbol de menús del login (padres → hijos → subhijos).
     *
     * @param  object[]  $padres
     * @param  array<int, int>  $mapa
     * @return object[]
     */
    public function aplicarAlArbol(array $padres, array $mapa)
    {
        if ($padres === [] || $mapa === []) {
            return $padres;
        }

        $padres = $this->ordenarHermanos($padres, $mapa);
        foreach ($padres as $padre) {
            $hijos = isset($padre->Hijos) && is_array($padre->Hijos) ? $padre->Hijos : [];
            $hijos = $this->ordenarHermanos($hijos, $mapa);
            foreach ($hijos as $hijo) {
                $sub = isset($hijo->SubHijos) && is_array($hijo->SubHijos) ? $hijo->SubHijos : [];
                $hijo->SubHijos = $this->ordenarHermanos($sub, $mapa);
            }
            $padre->Hijos = $hijos;
        }

        return $padres;
    }

    /**
     * @param  object[]  $items
     * @param  array<int, int>  $mapa
     * @return object[]
     */
    private function ordenarHermanos(array $items, array $mapa)
    {
        usort($items, function ($a, $b) use ($mapa) {
            $idA = (int) ($a->ID_Menu ?? 0);
            $idB = (int) ($b->ID_Menu ?? 0);
            $ordA = array_key_exists($idA, $mapa)
                ? (int) $mapa[$idA]
                : (int) ($a->Nu_Orden ?? 0);
            $ordB = array_key_exists($idB, $mapa)
                ? (int) $mapa[$idB]
                : (int) ($b->Nu_Orden ?? 0);
            if ($ordA === $ordB) {
                return $idA - $idB;
            }

            return $ordA - $ordB;
        });

        // Reflejar orden efectivo en Nu_Orden para el front.
        $i = 1;
        foreach ($items as $item) {
            $item->Nu_Orden = $i++;
        }

        return array_values($items);
    }
}
