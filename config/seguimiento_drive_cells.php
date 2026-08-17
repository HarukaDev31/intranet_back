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
    |
    | Layout hoja Seguimiento:
    |   YIWU B–L (start 2, width 11)
    |   RECIBIR N–T (start 14, width 7)
    |   CONTACTAR V–AB (start 22, width 7)
    |   URGENCIA AD–AK (start 30, width 8)
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
            'yiwu' => [
                'start_col' => 2,
                'width' => 11,
                'columns' => [
                    'cons' => 0,
                    'vendedor' => 1,
                    'cliente' => 2,
                    'code_supplier' => 3,
                    'cbm_yiwu' => 4,
                    'cbm_cotizado' => 5,
                    'diferencia' => 6,
                    'tipo_carga' => 7,
                    'estado_pago' => 8,
                    'ultima_actualizacion' => 9,
                    'yiwu_notas' => ['index' => 10, 'is_manual' => true],
                ],
            ],
            'recibir' => [
                'start_col' => 14,
                'width' => 7,
                'columns' => [
                    'cons' => 0,
                    'vendedor' => 1,
                    'cliente' => 2,
                    'cbm' => 3,
                    'fecha' => 4,
                    'code_supplier' => 5,
                    'ultima_actualizacion' => 6,
                ],
            ],
            'contactar' => [
                'start_col' => 22,
                'width' => 7,
                'columns' => [
                    'cons' => 0,
                    'vendedor' => 1,
                    'cliente' => 2,
                    'cbm_contactar' => 3,
                    'code_supplier' => 4,
                    'fecha_registro' => 5,
                    'note' => ['index' => 6, 'is_manual' => true],
                ],
            ],
            'urgencia' => [
                'start_col' => 30,
                'width' => 8,
                'columns' => [
                    'cons' => 0,
                    'vendedor' => 1,
                    'cliente' => 2,
                    'cbm' => 3,
                    'celular' => 4,
                    'motivo' => 5,
                    'estado' => 6,
                    'urgencia_notas' => ['index' => 7, 'is_manual' => true],
                ],
            ],
        ],
    ],
];
