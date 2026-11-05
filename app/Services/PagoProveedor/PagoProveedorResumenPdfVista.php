<?php

namespace App\Services\PagoProveedor;

use Dompdf\FontMetrics;

/**
 * Prepara los datos de la plantilla del PDF de resumen. Los importes vienen de PagoProveedorComisionService.
 */
class PagoProveedorResumenPdfVista
{
    private const ANCHO_CONTENIDO = 180.0;

    private const PT_A_MM = 0.3528;

    private const FIN_HOJA = 273.0;

    private const ALTO_HOJA_TERMINOS = 219.0;

    private const TERMINOS_RMB = [
        'titulo' => 'Pago en Yuanes (RMB)',
        'items' => [
            'Yuanes (RMB) es la moneda de China. El pago al proveedor se realiza en yuanes y su valor se convierte a dólares para calcular el monto a abonar a Probusiness.',
            'El tipo de cambio es referencial y se actualiza el día en que se confirma la operación (abono a Probusiness). Al finalizar el proceso de pago al proveedor, se realiza un balance y se revisa si se generó alguna diferencia por tipo de cambio (T/C).',
            'Para gestionar el pago se requieren el nombre del vendedor y su WeChat.',
            'Si el cliente envía más de un voucher para completar un mismo pago (por ejemplo, desde 2 bancos), los montos se suman y la comisión se cobra una sola vez.',
        ],
    ];

    private const TERMINOS_USD = [
        'titulo' => 'Pago en Dólares (USD)',
        'items' => [
            'El pago al proveedor se realiza en dólares (USD): no hay conversión de moneda ni tipo de cambio.',
            'En transferencia al exterior (T-T), el cliente debe llenar el formulario con los datos bancarios de su proveedor (por ejemplo: N.° de cuenta, beneficiario y datos del banco).',
        ],
    ];

    private const TERMINOS_CUOTAS = [
        'titulo' => 'Si el cliente paga en 2 cuotas (fraccionado)',
        'items' => [
            'El cliente abona a Probusiness en 2 cuotas. La 2.ª cuota se paga en la fecha acordada con el cliente (fecha aproximada).',
            'Las comisiones bancaria y administrativa se aplican en cada cuota, según el monto de esa cuota.',
            'Cada cuota sigue el proceso completo: depósito, validación del pago y pago al proveedor.',
        ],
    ];

    private const TERMINO_CUOTAS_RMB = 'En pagos en yuanes (RMB), el tipo de cambio de la 2.ª cuota se actualiza el día en que se cobra; por eso su monto en dólares puede variar respecto al referencial.';

    private const TERMINOS_ABONOS = [
        'titulo' => 'Si el cliente abona en 2 partes (pago único al proveedor)',
        'items' => [
            'El cliente abona a Probusiness en 2 partes. La 2.ª parte se abona en la fecha acordada con el cliente (fecha aproximada).',
            'Las comisiones se calculan una sola vez, sobre el pago al proveedor: no hay comisión adicional por abono.',
            'El pago al proveedor se ejecuta solo cuando el cliente completa el abono total a Probusiness; si no lo completa, no se realiza el pago.',
        ],
    ];

    private const TERMINO_COMISION_CON_CUOTAS = 'La comisión bancaria depende del monto de cada pago. Si el cliente paga en 2 cuotas, las comisiones bancaria y administrativa se aplican en cada cuota.';

    private const TERMINOS_GENERALES = [
        'El abono a Probusiness se realiza en dólares (USD).',
        'La comisión bancaria depende del monto de cada pago.',
        'Los socios de provincia deben consultar la comisión de su banco.',
        'Si el depósito se realiza después de las 4:00 p. m., la gestión del pago al proveedor pasa al siguiente día hábil.',
        'El pago al proveedor se ejecuta una vez validado el depósito.',
        'El pago se ejecuta siempre que los datos del proveedor y los datos de su cuenta bancaria estén completos y correctos.',
        'Cualquier demora en el proceso se comunicará oportunamente al cliente.',
    ];

    public function __construct(private readonly FontMetrics $metricas)
    {
    }

    /**
     * @param  array<string, mixed>  $cotizacion
     * @return array<string, mixed>
     */
    public function datos(array $cotizacion, string $fecha): array
    {
        $proveedores = $cotizacion['proveedores'] ?? [];
        $tc = (float) ($cotizacion['tipo_cambio'] ?? 0);
        $bloques = [];
        foreach ($proveedores as $indice => $proveedor) {
            $bloques[] = $this->bloque($proveedor, $indice + 1, $tc);
        }

        $hayRmb = collect($proveedores)->contains('moneda', 'RMB');
        $hayUsd = collect($proveedores)->contains('moneda', 'USD');
        $hayCobros = collect($bloques)->contains(fn (array $bloque) => $bloque['cobros'] !== null);
        $pagoEnCuotas = collect($proveedores)->contains(fn (array $proveedor) => $this->tipo($proveedor) === 'cuotas');

        return [
            'logo' => $this->imagen('logo'),
            'iconos' => array_map(fn (string $nombre) => $this->imagen($nombre), [
                'persona' => 'persona_n',
                'billetera' => 'billetera_o',
                'calendario' => 'calendario_n',
                'documento' => 'doc_n',
                'caja' => 'caja_n',
                'banco' => 'banco_n',
                'ruta' => 'ruta_n',
            ]),
            'fecha' => $fecha,
            'referencia' => !empty($cotizacion['id']) ? 'Cotización ' . $cotizacion['codigo'] : '',
            'datos_cliente' => $this->datosCliente($cotizacion, $hayRmb, $tc, $fecha, count($proveedores)),
            'tarjetas' => $this->tarjetas($cotizacion, $bloques, $hayCobros),
            'vigencia' => ($cotizacion['estado'] ?? '') === 'pendiente' && !empty($cotizacion['vigencia_hasta'])
                ? implode('/', array_map('intval', explode('/', explode(' ', (string) $cotizacion['vigencia_hasta'])[0])))
                : null,
            'reloj' => 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="66" height="66"><g transform="scale(10)"><circle cx="3.3" cy="3.3" r="2.7" fill="none" stroke="#f2662b" stroke-width="0.55"/><path d="M3.3 3.3 L3.3 1.6 M3.3 3.3 L4.6 4" fill="none" stroke="#f2662b" stroke-width="0.55"/></g></svg>'),
            'bloques' => $bloques,
            'compromiso' => $hayCobros ? $this->usd($cotizacion['total']) : null,
            'pasos' => $this->pasos($hayRmb, $hayUsd),
            'linea_tiempo' => $this->lineaDeTiempo(),
            'pago_en_cuotas' => $pagoEnCuotas,
            'terminos' => $this->terminos($proveedores, $hayRmb, $hayUsd, $pagoEnCuotas, $this->espacioParaTerminos($bloques, ($cotizacion['estado'] ?? '') === 'pendiente' && !empty($cotizacion['vigencia_hasta']), $pagoEnCuotas, $hayCobros)),
        ];
    }

    /**
     * @param  array<string, mixed>  $proveedor
     */
    private function tipo(array $proveedor): string
    {
        $abono = ($proveedor['modalidad_abono'] ?? '') === 'fraccionado';
        $pago = ($proveedor['modalidad_pago'] ?? '') === 'fraccionado';

        return match (true) {
            $abono && $pago => 'cuotas',
            $abono => 'abonos',
            $pago => 'pagos',
            default => 'total',
        };
    }

    /**
     * @param  array<string, mixed>  $proveedor
     * @return array<string, mixed>
     */
    private function bloque(array $proveedor, int $numero, float $tc): array
    {
        $calculo = $proveedor['calculo'] ?? [];
        $cuotas = $calculo['cuotas'] ?? [];
        $tipo = $this->tipo($proveedor);
        $esRmb = $proveedor['moneda'] === 'RMB';
        $valorUsd = (float) ($calculo['valor_carga_usd'] ?? $proveedor['valor_carga_usd']);
        $valorNativo = $esRmb ? round($valorUsd * $tc, 2) : $valorUsd;
        $valorTexto = $esRmb
            ? '¥' . $this->numero($valorNativo) . ' ÷ ' . $this->tasa($tc) . ' = ' . $this->usd($valorUsd)
            : $this->usd($valorUsd);
        $porcentaje1 = (float) ($proveedor['porcentaje_1'] ?? 100);
        $porcentaje2 = (float) ($proveedor['porcentaje_2'] ?? (100 - $porcentaje1));
        $reparto = $this->porcentaje($porcentaje1) . '%–' . $this->porcentaje($porcentaje2) . '%';
        $modalidad = match ($tipo) {
            'cuotas' => '2 cuotas ' . $reparto,
            'abonos' => 'Total · 2 abonos ' . $reparto,
            'pagos' => '2 pagos al proveedor ' . $reparto,
            default => 'Total',
        };
        $partesSub = array_filter([trim((string) $proveedor['nombre_proveedor']), $proveedor['producto'] ?: '—']);
        $subtitulo = implode(' · ', [...$partesSub, $esRmb ? 'RMB / Transferencia' : 'USD / T-T', $modalidad]);
        $titulo = 'Proveedor ' . $numero . ': ' . ($proveedor['code_supplier'] ?: 'Sin código');
        $conCobros = count($cuotas) > 1;
        $anchoTexto = self::ANCHO_CONTENIDO - 16.5 - ($conCobros ? 54 : 6);
        $incluida = $calculo['etiqueta_bancaria'] ?? null;
        $fechaCobro2 = !empty($proveedor['fecha_cuota_2'])
            ? 'Fecha aprox. ' . date('j/n/Y', (int) strtotime((string) $proveedor['fecha_cuota_2']))
            : 'Fecha por definir';

        return [
            'numero' => $numero,
            'titulo' => $titulo,
            'subtitulo' => $subtitulo,
            'subtitulo_pt' => $this->ajustar($subtitulo, $anchoTexto, [8, 7.4, 6.8, 6.3], false),
            'valor_texto' => $valorTexto,
            'total' => $this->usd($calculo['total'] ?? 0),
            'bancaria' => (float) ($calculo['bancaria'] ?? 0),
            'administrativa' => (float) ($calculo['administrativa'] ?? 0),
            'filas' => $conCobros ? [] : $this->filasUnicas($proveedor, $calculo, $valorTexto, $incluida),
            'cobros' => $conCobros ? $this->cobros($proveedor, $cuotas, $incluida, $fechaCobro2) : null,
            'cobro2_total' => $conCobros ? (float) $cuotas[1]['total'] : 0.0,
            'cobro2_fecha' => $fechaCobro2,
            'tipo' => $tipo,
            'fraccion' => $tipo === 'pagos' ? [
                'titulo' => 'Modalidad de pago al proveedor (fraccionamiento)',
                'partes' => [
                    ['etiqueta' => '1.ª parte', 'porcentaje' => $this->porcentaje($porcentaje1) . '%', 'monto' => ($esRmb ? '¥' : '$') . $this->numero($valorNativo * $porcentaje1 / 100), 'nota' => '1.er pago al proveedor'],
                    ['etiqueta' => '2.ª parte', 'porcentaje' => $this->porcentaje($porcentaje2) . '%', 'monto' => ($esRmb ? '¥' : '$') . $this->numero($valorNativo * $porcentaje2 / 100), 'nota' => '2.º pago al proveedor'],
                ],
                'texto' => 'El cliente realiza un solo abono de ' . $this->usd($calculo['total'] ?? 0) . '. Pro Business paga al proveedor en 2 partes, según esta distribución. La comisión bancaria se cobra una sola vez y la administrativa dos veces (una por pago).',
            ] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $proveedor
     * @param  array<string, mixed>  $calculo
     * @return array<int, array{etiqueta: string, valor: string}>
     */
    private function filasUnicas(array $proveedor, array $calculo, string $valorTexto, ?string $incluida): array
    {
        $filas = [['etiqueta' => 'Valor de carga', 'valor' => $valorTexto]];
        if (!empty($proveedor['comision_bancaria'])) {
            $filas[] = ['etiqueta' => 'Comisión bancaria / plataforma', 'valor' => $incluida ?: $this->usd($calculo['bancaria'] ?? 0)];
        }
        if (!empty($proveedor['comision_administrativa'])) {
            $filas[] = ['etiqueta' => 'Comisión administrativa', 'valor' => $this->usd($calculo['administrativa'] ?? 0)];
        }

        return $filas;
    }

    /**
     * @param  array<string, mixed>  $proveedor
     * @param  array<int, array<string, mixed>>  $cuotas
     * @return array<int, array<string, mixed>>
     */
    private function cobros(array $proveedor, array $cuotas, ?string $incluida, string $fechaCobro2): array
    {
        $cobros = [];
        foreach ($cuotas as $indice => $cuota) {
            $filas = [['etiqueta' => 'Valor de carga', 'valor' => $this->usd($cuota['carga'])]];
            if (!empty($proveedor['comision_bancaria'])) {
                $filas[] = [
                    'etiqueta' => 'Comisión bancaria / plataforma',
                    'valor' => $indice === 0 && $incluida ? $incluida : $this->usd($cuota['bancaria']),
                ];
            }
            if (!empty($proveedor['comision_administrativa'])) {
                $filas[] = ['etiqueta' => 'Comisión administrativa', 'valor' => $this->usd($cuota['administrativa'])];
            }
            $cobros[] = [
                'titulo' => 'Cobro ' . ($indice + 1) . ' (' . $this->porcentaje($cuota['porcentaje']) . '%)',
                'etiqueta' => $indice === 0 ? 'Ahora' : $fechaCobro2,
                'filas' => $filas,
                'total_titulo' => 'Total cobro ' . ($indice + 1),
                'total' => $this->usd($cuota['total']),
            ];
        }

        return $cobros;
    }

    /**
     * @param  array<string, mixed>  $cotizacion
     * @return array<int, array{etiqueta: string, valor: string, ancho: float, pt: float}>
     */
    private function datosCliente(array $cotizacion, bool $hayRmb, float $tc, string $fecha, int $cantidad): array
    {
        $filas = [
            ['Cliente', $cotizacion['cliente_nombre'] ?: '—'],
            ['N.° de consolidado', $cotizacion['consolidado'] ?: '—'],
            ['Cotización C.C', 'N.° ' . $cotizacion['cod_cotizacion_cc']],
            ['N.° de proveedores', (string) $cantidad],
        ];
        $conTc = $hayRmb && $tc > 0;
        if ($conTc) {
            $filas[] = ['Tipo de cambio referencial', '1 USD = ' . $this->tasa($tc) . ' RMB (' . $fecha . ')'];
        }
        $pesos = [
            'Cliente' => $conTc ? 1.6 : 2.1,
            'N.° de consolidado' => 1.4,
            'Cotización C.C' => $conTc ? 1.45 : 1.5,
            'N.° de proveedores' => $conTc ? 1.35 : 1.3,
            'Tipo de cambio referencial' => 2.25,
        ];
        $pesoTotal = array_sum(array_map(fn (array $fila) => $pesos[$fila[0]], $filas));

        return array_map(function (array $fila, int $indice) use ($pesos, $pesoTotal) {
            $ancho = self::ANCHO_CONTENIDO * $pesos[$fila[0]] / $pesoTotal;

            return [
                'etiqueta' => $fila[0],
                'valor' => $fila[1],
                'ancho' => $ancho,
                'pt' => $this->ajustar($fila[1], $ancho - ($indice > 0 ? 5.5 : 1.5), [10, 9.6, 9.2, 8.8, 8.4]),
            ];
        }, $filas, array_keys($filas));
    }

    /**
     * @param  array<string, mixed>  $cotizacion
     * @param  array<int, array<string, mixed>>  $bloques
     * @return array<int, array<string, mixed>>
     */
    private function tarjetas(array $cotizacion, array $bloques, bool $hayCobros): array
    {
        $conCobros = array_values(array_filter($bloques, fn (array $bloque) => $bloque['cobros'] !== null));
        $referencias = implode(' y ', array_map(fn (array $bloque) => explode(': ', $bloque['titulo'], 2)[1], $conCobros));
        $nota = '';
        if ($conCobros !== []) {
            $nota = count($conCobros) === count($bloques) ? 'Cuota 1 de 2' : 'Incluye la cuota 1 de ' . $referencias;
        }
        $total = $this->usd($cotizacion['total_ahora']);
        $tarjetas = [[
            'tipo' => 'total',
            'icono' => 'billetera',
            'etiqueta' => $hayCobros ? 'TOTAL A PAGAR AHORA' : 'TOTAL A PAGAR',
            'valor' => $total,
            'nota' => $nota,
        ]];

        if ($hayCobros) {
            $fechas = array_values(array_unique(array_map(fn (array $bloque) => $bloque['cobro2_fecha'], $conCobros)));
            $tarjetas[] = [
                'tipo' => 'otro',
                'icono' => 'calendario',
                'etiqueta' => 'PRÓXIMO ABONO',
                'valor' => $this->usd(array_sum(array_map(fn (array $bloque) => $bloque['cobro2_total'], $conCobros))),
                'nota' => count($fechas) === 1 ? $fechas[0] : 'Según proveedor',
            ];
        }

        $bancaria = array_sum(array_map(fn (array $bloque) => $bloque['bancaria'], $bloques));
        $administrativa = array_sum(array_map(fn (array $bloque) => $bloque['administrativa'], $bloques));
        if ($bancaria + $administrativa > 0.004) {
            $origen = $bancaria > 0.004 && $administrativa > 0.004
                ? 'Banco + Administrativa'
                : ($bancaria > 0.004 ? 'Banco' : 'Administrativa');
            $tarjetas[] = [
                'tipo' => 'otro',
                'icono' => 'documento',
                'etiqueta' => 'COMISIONES',
                'valor' => $this->usd($bancaria + $administrativa),
                'nota' => $origen . ($hayCobros ? ' (2 cuotas)' : ''),
            ];
        }

        $anchos = match (count($tarjetas)) {
            3 => [70, 53, 51],
            2 => [90, 87],
            default => [self::ANCHO_CONTENIDO],
        };
        foreach ($tarjetas as $indice => $tarjeta) {
            $ancho = (float) $anchos[$indice];
            $tarjetas[$indice]['ancho'] = $ancho;
            $tarjetas[$indice]['valor_pt'] = $this->ajustar($tarjeta['valor'], $ancho - 9, [21, 19, 17, 15]);
            $tarjetas[$indice]['nota_pt'] = $this->ajustar($tarjeta['nota'], $ancho - 8, [8, 7.6, 7.2, 6.8], false);
        }

        return $tarjetas;
    }

    /**
     * @return array<int, array{titulo: string, descripcion: string, etiqueta: string}>
     */
    private function pasos(bool $hayRmb, bool $hayUsd): array
    {
        $pago = match (true) {
            $hayRmb && $hayUsd => 'Pagamos a tu proveedor en RMB o USD.',
            $hayRmb => 'Pagamos a tu proveedor en RMB.',
            $hayUsd => 'Pagamos a tu proveedor en USD.',
            default => 'Pagamos a tu proveedor.',
        };

        return [
            ['etiqueta' => 'INICIO', 'titulo' => 'Cotización', 'descripcion' => 'Te enviamos la propuesta para tu confirmación.'],
            ['etiqueta' => 'PASO 1', 'titulo' => 'Solicitud y datos de pago', 'descripcion' => 'Envías los datos del proveedor y tu voucher.'],
            ['etiqueta' => 'PASO 2', 'titulo' => 'Validación de pago', 'descripcion' => 'Verificamos el ingreso de tu depósito.'],
            ['etiqueta' => 'PASO 3', 'titulo' => 'Ejecución de pago', 'descripcion' => $pago],
            ['etiqueta' => 'PASO 4', 'titulo' => 'Confirmación', 'descripcion' => 'Te enviamos la constancia para tu validación.'],
        ];
    }

    /**
     * Línea de tiempo del esquema, con la cotización como etapa actual. Coordenadas en mm.
     */
    private function lineaDeTiempo(): string
    {
        $columna = self::ANCHO_CONTENIDO / 5;
        $radio = 6;
        $cy = 15.8;
        $gris = '#aaaaaa';
        $trazos = [
            '<path d="M-2.6 -3.6 L1.2 -3.6 L2.8 -2 L2.8 3.6 L-2.6 3.6 Z M1.2 -3.6 L1.2 -2 L2.8 -2 M-1.2 -0.4 L1.5 -0.4 M-1.2 1.1 L1.5 1.1 M-1.2 2.5 L0.3 2.5"/>',
            '<rect x="-4" y="-2.6" width="8" height="5.2" rx="0.9" ry="0.9"/><path d="M-4 -0.5 L4 -0.5 M-2.8 1.5 L-0.8 1.5"/>',
            '<path d="M0 -4.4 L3.6 -3 L3.6 0.6 L0 4.4 L-3.6 0.6 L-3.6 -3 Z M-1.5 0.2 L-0.4 1.4 L1.8 -1.3"/>',
            '<path d="M-4.2 -3.4 L4.4 0 L-4.2 3.4 L-2.4 0 Z M-2.4 0 L4.4 0"/>',
            '<circle cx="0" cy="0" r="3.9"/><path d="M-1.8 0.1 L-0.5 1.5 L2 -1.4"/>',
        ];

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="180" height="26">';
        for ($i = 0; $i < 4; $i++) {
            $x1 = $columna * ($i + 0.5) + $radio;
            $x2 = $columna * ($i + 1.5) - $radio;
            $svg .= sprintf('<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="#c8c8c8" stroke-width="0.8" stroke-dasharray="1.4 1.2"/>', $x1, $cy, $x2, $cy);
        }
        foreach ($trazos as $i => $trazo) {
            $x = $columna * ($i + 0.5);
            if ($i === 0) {
                $svg .= sprintf('<circle cx="%s" cy="%s" r="7.5" fill="#fde2d6"/><circle cx="%s" cy="%s" r="6" fill="#f2662b"/>', $x, $cy, $x, $cy);
                $svg .= sprintf('<rect x="%s" y="%s" width="18" height="4" rx="2" ry="2" fill="#f2662b"/>', $x - 9, $cy - 12);
            } else {
                $svg .= sprintf('<circle cx="%s" cy="%s" r="6" fill="#ffffff" stroke="#c8c8c8" stroke-width="0.6"/>', $x, $cy);
            }
            $color = $i === 0 ? '#ffffff' : $gris;
            $svg .= sprintf('<g transform="translate(%s %s)" fill="none" stroke="%s" stroke-width="0.55" stroke-linecap="round" stroke-linejoin="round">%s</g>', $x, $cy, $color, $trazo);
        }
        $svg .= '</svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Reparte los términos en dos columnas parejas. Lo que no cabe en la hoja continúa en la siguiente.
     *
     * @param  array<int, array<string, mixed>>  $proveedores
     * @return array<int, array{titulo: string, columnas: array<int, array<int, array{titulo: string|null, items: array<int, string>}>>}>
     */
    private function terminos(array $proveedores, bool $hayRmb, bool $hayUsd, bool $pagoEnCuotas, float $disponible): array
    {
        $secciones = [];
        if ($hayRmb) {
            $secciones[] = self::TERMINOS_RMB;
        }
        if ($hayUsd) {
            $secciones[] = self::TERMINOS_USD;
        }
        if ($pagoEnCuotas) {
            $cuotas = self::TERMINOS_CUOTAS;
            $cuotasEnRmb = collect($proveedores)->contains(fn (array $p) => $this->tipo($p) === 'cuotas' && $p['moneda'] === 'RMB');
            if ($cuotasEnRmb) {
                array_splice($cuotas['items'], 2, 0, self::TERMINO_CUOTAS_RMB);
            }
            $secciones[] = $cuotas;
        }
        if (collect($proveedores)->contains(fn (array $p) => $this->tipo($p) === 'abonos')) {
            $secciones[] = self::TERMINOS_ABONOS;
        }
        $generales = self::TERMINOS_GENERALES;
        if (!$pagoEnCuotas) {
            $generales[1] = self::TERMINO_COMISION_CON_CUOTAS;
        }
        $secciones[] = ['titulo' => $secciones !== [] ? 'Términos generales' : null, 'items' => $generales];

        $grupos = [];
        $resto = $secciones;
        while ($resto !== []) {
            $cantidad = count($resto);
            while ($cantidad > 1 && $this->repartir(array_slice($resto, 0, $cantidad))['alto'] > $disponible) {
                $cantidad--;
            }
            $seleccion = array_slice($resto, 0, $cantidad);
            $pendiente = array_slice($resto, $cantidad);
            $alto = $this->repartir($seleccion)['alto'];
            if ($alto > $disponible && $disponible < self::ALTO_HOJA_TERMINOS) {
                $disponible = self::ALTO_HOJA_TERMINOS;

                continue;
            }

            if ($pendiente !== [] && $disponible - $alto >= 12 && count($pendiente[0]['items']) > 1) {
                $siguiente = $pendiente[0];
                $caben = 0;
                for ($n = 1; $n < count($siguiente['items']); $n++) {
                    $prueba = [...$seleccion, ['titulo' => $siguiente['titulo'], 'items' => array_slice($siguiente['items'], 0, $n)]];
                    if ($this->repartir($prueba)['alto'] > $disponible) {
                        break;
                    }
                    $caben = $n;
                }
                if ($caben > 0) {
                    $seleccion[] = ['titulo' => $siguiente['titulo'], 'items' => array_slice($siguiente['items'], 0, $caben)];
                    $tituloSiguiente = $siguiente['titulo'] ? preg_replace('/ \(cont\.\)$/', '', $siguiente['titulo']) . ' (cont.)' : null;
                    $pendiente[0] = ['titulo' => $tituloSiguiente, 'items' => array_slice($siguiente['items'], $caben)];
                }
            }

            $grupos[] = [
                'titulo' => $grupos === [] ? 'TÉRMINOS Y CONDICIONES' : 'TÉRMINOS Y CONDICIONES (CONTINUACIÓN)',
                'columnas' => $this->repartir($seleccion)['columnas'],
            ];
            $resto = $pendiente;
            $disponible = self::ALTO_HOJA_TERMINOS;
        }

        return $grupos;
    }

    /**
     * @param  array<int, array<string, mixed>>  $secciones
     * @return array{columnas: array<int, array<int, array{titulo: string|null, items: array<int, string>}>>, alto: float}
     */
    private function repartir(array $secciones): array
    {
        $anchoColumna = (self::ANCHO_CONTENIDO - 10) / 2;
        $unidades = [];
        foreach ($secciones as $seccion => $datos) {
            if ($datos['titulo']) {
                $lineasTitulo = (int) ceil($this->anchoMm($datos['titulo'], 9.4, true) / ($anchoColumna - 1));
                $unidades[] = ['seccion' => $seccion, 'item' => null, 'alto' => 6 + ($lineasTitulo - 1) * 4.2];
            }
            $ultimo = count($datos['items']) - 1;
            foreach ($datos['items'] as $indice => $texto) {
                $lineas = (int) ceil($this->anchoMm($texto, 8.6, false) / ($anchoColumna - 4.5));
                $unidades[] = ['seccion' => $seccion, 'item' => $indice, 'alto' => $lineas * 3.9 + 2.4 + ($indice === $ultimo ? 3 : 0)];
            }
        }

        $corte = 0;
        $mejor = INF;
        $altoMejor = 0.0;
        $total = count($unidades);
        for ($c = 0; $c <= $total; $c++) {
            if ($c > 0 && $c < $total && $unidades[$c - 1]['item'] === null) {
                continue;
            }
            $alto1 = (float) array_sum(array_column(array_slice($unidades, 0, $c), 'alto'));
            $alto2 = (float) array_sum(array_column(array_slice($unidades, $c), 'alto'));
            $parte = $c > 0 && $c < $total && $unidades[$c - 1]['seccion'] === $unidades[$c]['seccion'] ? 8 : 0;
            if (abs($alto1 - $alto2) + $parte < $mejor) {
                $mejor = abs($alto1 - $alto2) + $parte;
                $corte = $c;
                $altoMejor = max($alto1, $alto2);
            }
        }

        return [
            'columnas' => [
                $this->columnaTerminos(array_slice($unidades, 0, $corte), $secciones),
                $this->columnaTerminos(array_slice($unidades, $corte), $secciones),
            ],
            'alto' => $altoMejor,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $unidades
     * @param  array<int, array<string, mixed>>  $secciones
     * @return array<int, array{titulo: string|null, items: array<int, string>}>
     */
    private function columnaTerminos(array $unidades, array $secciones): array
    {
        $columna = [];
        $actual = null;
        foreach ($unidades as $unidad) {
            if ($actual !== $unidad['seccion']) {
                $actual = $unidad['seccion'];
                $columna[] = [
                    'titulo' => $unidad['item'] === null || $unidad['item'] === 0 ? $secciones[$actual]['titulo'] : null,
                    'items' => [],
                ];
            }
            if ($unidad['item'] !== null) {
                $columna[count($columna) - 1]['items'][] = $secciones[$actual]['items'][$unidad['item']];
            }
        }

        return $columna;
    }

    /**
     * Estima cuánto espacio queda para los términos en la hoja donde termina el esquema del proceso.
     *
     * @param  array<int, array<string, mixed>>  $bloques
     */
    private function espacioParaTerminos(array $bloques, bool $conVigencia, bool $pagoEnCuotas, bool $conCompromiso): float
    {
        $y = 37.0;
        $colocar = function (float $alto) use (&$y): void {
            if ($y + $alto > self::FIN_HOJA) {
                $y = 37.0;
            }
            $y += $alto;
        };

        $y += 87.6 - ($conVigencia ? 0 : 12);
        foreach ($bloques as $indice => $bloque) {
            $filas = $bloque['cobros'] ? count($bloque['cobros'][0]['filas']) : count($bloque['filas']);
            $alto = $bloque['cobros'] ? 15 + 9 + 6.5 + $filas * 5.9 + 11.6 + 8 : 15 + $filas * 6 + 11.6 + 8;
            $colocar($alto + ($indice === 0 ? 11 : 0));
        }
        if ($conCompromiso) {
            $colocar(14);
        }
        foreach ($bloques as $bloque) {
            if ($bloque['fraccion']) {
                $colocar(35);
            }
        }
        $colocar(50);
        $colocar(11 + 46.7 + 6 + ($pagoEnCuotas ? 7 : 0));

        return self::FIN_HOJA - $y - 11 - 6;
    }

    private function imagen(string $nombre): string
    {
        return 'data:image/png;base64,' . base64_encode((string) file_get_contents(public_path('pago-proveedor/pdf/' . $nombre . '.png')));
    }

    private function usd(mixed $valor): string
    {
        return '$' . $this->numero((float) $valor);
    }

    private function numero(float $valor): string
    {
        return number_format($valor, 2, '.', ',');
    }

    private function porcentaje(mixed $valor): string
    {
        return rtrim(rtrim(number_format((float) $valor, 2, '.', ''), '0'), '.');
    }

    private function tasa(float $tc): string
    {
        return rtrim(rtrim(number_format($tc, 4, '.', ''), '0'), '.');
    }

    private function anchoMm(string $texto, float $pt, bool $negrita): float
    {
        $fuente = $this->metricas->getFont('Helvetica', $negrita ? 'bold' : 'normal');

        return $this->metricas->getTextWidth($texto, $fuente, $pt) * self::PT_A_MM;
    }

    /**
     * @param  array<int, float|int>  $tamanos
     */
    private function ajustar(string $texto, float $ancho, array $tamanos, bool $negrita = true): float
    {
        foreach ($tamanos as $tamano) {
            if ($this->anchoMm($texto, (float) $tamano, $negrita) <= $ancho) {
                return (float) $tamano;
            }
        }

        return (float) end($tamanos);
    }
}
