<?php

return [
    /*
     * PRY-26-0003: los consolidados cerrados en China desde esta fecha (f_cierre) aparecen en Operaciones
     * como «Por aprobar». Los cerrados antes no, para no llenar la lista con consolidados antiguos.
     * Vacío = todos.
     */
    'borradores_desde' => env('CHINA_BORRADORES_DESDE', '2026-09-01'),
];
