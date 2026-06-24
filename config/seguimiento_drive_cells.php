<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Columnas rastreadas del Excel de seguimiento en Drive
    |--------------------------------------------------------------------------
    |
    | is_manual: se preserva al regenerar desde BD.
    | Las columnas configuradas como automáticas se reescriben desde BD.
    | En Cotizaciones, cualquier columna adicional fuera de B-K se considera
    | manual y se preserva por row_key + letra de columna.
    */
    'sheets' => [
        'Cotizaciones' => [
            'data_start_row' => 2,
            'preserve_extra_columns' => true,
            'columns' => [
                'carga' => ['letter' => 'B', 'is_manual' => false],
                'asesor' => ['letter' => 'C', 'is_manual' => false],
                'nombre_cliente' => ['letter' => 'D', 'is_manual' => false],
                'whatsapp' => ['letter' => 'E', 'is_manual' => false],
                'code_supplier' => ['letter' => 'F', 'is_manual' => false],
                'volumen' => ['letter' => 'G', 'is_manual' => false],
                'volumen_china' => ['letter' => 'H', 'is_manual' => false],
                'estado' => ['letter' => 'I', 'is_manual' => false],
                'estado_china' => ['letter' => 'J', 'is_manual' => false],
                'notas' => ['letter' => 'K', 'is_manual' => true],
            ],
        ],
        'Seguimiento' => [
            'contactar' => [
                'start_col' => 18,
                'width' => 6,
                'columns' => [
                    'cons' => 0,
                    'vendedor' => 1,
                    'cliente' => 2,
                    'cbm_contactar' => 3,
                    'code_supplier' => 4,
                    'note' => ['index' => 5, 'is_manual' => true],
                ],
            ],
        ],
    ],
];
