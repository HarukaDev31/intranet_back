<?php

namespace App\Services\ManualUsuario;

/**
 * Paso de flujo con título, texto de usuario y pie de captura.
 */
trait ManualUsuarioFlowItems
{
    /**
     * @param  array|string  $capture  Metadatos o capture_key explícita.
     * Configuración runner admitida: type, target, actions, expectedText,
     * padding, masks, piiAllow, expectedHash, enabled y url.
     */
    protected function itemFlujo($titulo, $cuerpo, $captura = '', $capture = [])
    {
        $item = ['title' => $titulo, 'body' => $cuerpo];
        if ($captura !== '') {
            $item['captura'] = $captura;
        }

        if (is_string($capture) && $capture !== '') {
            $capture = ['capture_key' => $capture];
        }
        if (is_array($capture)) {
            foreach ([
                'capture_key',
                'capture_alias_of',
                'capture_output',
                'type',
                'target',
                'actions',
                'expectedText',
                'padding',
                'masks',
                'piiAllow',
                'expectedHash',
                'enabled',
                'url',
            ] as $field) {
                if (array_key_exists($field, $capture) && $capture[$field] !== '') {
                    $item[$field] = $capture[$field];
                }
            }
        }

        return $item;
    }
}
