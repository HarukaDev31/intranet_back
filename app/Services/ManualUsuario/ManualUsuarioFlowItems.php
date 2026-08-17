<?php

namespace App\Services\ManualUsuario;

/**
 * Paso de flujo con título, texto de usuario y pie de captura.
 */
trait ManualUsuarioFlowItems
{
    /**
     * @return array{title:string,body:string,captura?:string}
     */
    protected function itemFlujo($titulo, $cuerpo, $captura = '')
    {
        $item = ['title' => $titulo, 'body' => $cuerpo];
        if ($captura !== '') {
            $item['captura'] = $captura;
        }

        return $item;
    }
}
