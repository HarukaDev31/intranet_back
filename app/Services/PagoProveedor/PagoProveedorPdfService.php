<?php

namespace App\Services\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Models\PagoProveedor\PagoProveedorCotizacion;
use App\Models\PagoProveedor\PagoProveedorDocumento;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PagoProveedorPdfService
{
    public function __construct(
        private readonly PagoProveedorCotizacionService $cotizaciones,
        private readonly PagoProveedorCotizacionPresenter $presenter,
        private readonly PagoProveedorConfiguracionService $configuracion,
        private readonly PagoProveedorHistorialService $historial,
        private readonly PagoProveedorDocumentoService $documentos,
    ) {
    }

    /**
     * Resumen de la cotización con el paso del flujo en el que está.
     * El PDF se reutiliza mientras los datos y el paso no cambien.
     *
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array{filename: string, base64: string, paso: int, paso_nombre: string, emitido_el: string|null, generado: bool, descargas: int}
     */
    public function resumen(int $id, array $actor): array
    {
        $cotizacion = $this->cotizaciones->encontrar($id);
        $documento = $this->resumenDeCotizacion($cotizacion, $actor, true);
        $this->historial->registrar(
            PagoProveedorDocumento::ENTITY_QUOTE,
            (int) $cotizacion->id,
            'PDF descargado',
            $actor,
            'Paso ' . $documento['paso'] . ' · ' . $documento['paso_nombre']
        );

        return $documento;
    }

    /**
     * PDF temporal para adjuntar en WhatsApp (no registra descarga).
     *
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array{path: string, filename: string}
     */
    public function resumenTemporal(int $id, array $actor): array
    {
        $documento = $this->resumenDeCotizacion($this->cotizaciones->encontrar($id), $actor, false);
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pp-resumen-' . $id . '-' . uniqid('', true) . '.pdf';
        if (file_put_contents($path, base64_decode($documento['base64'])) === false) {
            throw new PagoProveedorException('No se pudo preparar el PDF para WhatsApp.');
        }

        return [
            'path' => $path,
            'filename' => $documento['filename'],
        ];
    }

    /**
     * PDF con los datos del formulario, aunque la cotización no esté guardada.
     *
     * @param  array<string, mixed>  $datos
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array{filename: string, base64: string, paso: int, paso_nombre: string, emitido_el: string|null, generado: bool, descargas: int}
     */
    public function resumenBorrador(array $datos, array $actor): array
    {
        $cotizacion = $this->cotizaciones->borrador($datos);
        if (!$cotizacion->id) {
            return $this->resumenSinGuardar($cotizacion);
        }

        return $this->resumenDeCotizacion($cotizacion, $actor, true);
    }

    /**
     * @return array{path: string, filename: string}
     */
    public function temporalDeBorrador(PagoProveedorCotizacion $cotizacion): array
    {
        $documento = $cotizacion->id
            ? $this->resumenDeCotizacion($cotizacion, null, false)
            : $this->resumenSinGuardar($cotizacion);

        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pp-resumen-' . uniqid('', true) . '.pdf';
        if (file_put_contents($path, base64_decode($documento['base64'])) === false) {
            throw new PagoProveedorException('No se pudo preparar el PDF para WhatsApp.');
        }

        return ['path' => $path, 'filename' => $documento['filename']];
    }

    /**
     * Balance de cambio de un proveedor: también se emite una vez por estado.
     *
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array{filename: string, base64: string, paso: int, paso_nombre: string, emitido_el: string|null, generado: bool, descargas: int}
     */
    public function balance(PagoProveedorSolicitudProveedor $provider, array $actor, bool $contarDescarga = true): array
    {
        $provider->loadMissing(['solicitud', 'ejecuciones']);
        $datos = $this->datosBalance($provider);
        $paso = $this->documentos->pasoDeSolicitudProveedor($provider);
        $code = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $provider->code_supplier) ?: (string) $provider->id;

        $documento = $this->documentos->emitir(
            PagoProveedorDocumento::ENTITY_REQUEST,
            (int) $provider->request_id,
            PagoProveedorDocumento::TYPE_BALANCE,
            (int) $provider->id,
            $paso,
            sha1((string) json_encode($datos)),
            'Balance_' . $code . '.pdf',
            fn (string $fecha) => $this->renderBalance($datos, $fecha),
            $actor,
            $contarDescarga
        );

        if ($contarDescarga) {
            $this->historial->registrar(
                PagoProveedorDocumento::ENTITY_REQUEST,
                (int) $provider->request_id,
                'PDF de balance descargado',
                $actor,
                $provider->code_supplier
            );
        }

        return $documento;
    }

    /**
     * Estado del resumen sin generarlo: dice en qué paso se emitió y si sigue vigente.
     * Sirve para avisar en la ficha si el PDF se regenerará al descargarlo.
     *
     * @return array{paso: int, paso_nombre: string, paso_actual: int, paso_actual_nombre: string, desactualizado: bool, generado_en: string|null, emitido_el: string|null, descargas: int, generado: bool}|null
     */
    public function estadoResumen(PagoProveedorCotizacion $cotizacion): ?array
    {
        if (!$cotizacion->id) {
            return null;
        }

        $estado = $this->documentos->estado(
            PagoProveedorDocumento::ENTITY_QUOTE,
            (int) $cotizacion->id,
            PagoProveedorDocumento::TYPE_SUMMARY
        );
        if ($estado === null) {
            return null;
        }

        $paso = $this->documentos->pasoDeCotizacion($cotizacion);
        $vigente = false;
        try {
            $presentado = $this->presenter->cotizacion($cotizacion, $this->configuracion->tariff());
            $vigente = $this->documentos->vigente(
                PagoProveedorDocumento::ENTITY_QUOTE,
                (int) $cotizacion->id,
                PagoProveedorDocumento::TYPE_SUMMARY,
                0,
                $paso,
                $this->huellaResumen($presentado)
            );
        } catch (\Throwable $e) {
            // Si todavía faltan datos para el PDF, se conserva el paso emitido.
            $vigente = (int) $estado['paso'] === $paso;
        }

        $estado['paso_actual'] = $paso;
        $estado['paso_actual_nombre'] = PagoProveedorDocumento::stepLabel($paso);
        $estado['desactualizado'] = !$vigente;

        return $estado;
    }

    /**
     * Estado del balance sin generarlo: dice en qué paso se emitió y si sigue vigente.
     *
     * @return array{paso: int, paso_nombre: string, paso_actual: int, paso_actual_nombre: string, desactualizado: bool, generado_en: string|null, emitido_el: string|null, descargas: int, generado: bool}|null
     */
    public function estadoBalance(PagoProveedorSolicitudProveedor $provider): ?array
    {
        $provider->loadMissing(['solicitud', 'ejecuciones']);
        $estado = $this->documentos->estado(
            PagoProveedorDocumento::ENTITY_REQUEST,
            (int) $provider->request_id,
            PagoProveedorDocumento::TYPE_BALANCE,
            (int) $provider->id
        );
        if ($estado === null) {
            return null;
        }

        $paso = $this->documentos->pasoDeSolicitudProveedor($provider);
        $vigente = false;
        try {
            $datos = $this->datosBalance($provider);
            $vigente = $this->documentos->vigente(
                PagoProveedorDocumento::ENTITY_REQUEST,
                (int) $provider->request_id,
                PagoProveedorDocumento::TYPE_BALANCE,
                (int) $provider->id,
                $paso,
                sha1((string) json_encode($datos))
            );
        } catch (\Throwable $e) {
            // Sin pagos registrados todavía no hay balance: se compara solo el paso.
            $vigente = (int) $estado['paso'] === $paso;
        }

        $estado['paso_actual'] = $paso;
        $estado['paso_actual_nombre'] = PagoProveedorDocumento::stepLabel($paso);
        $estado['desactualizado'] = !$vigente;

        return $estado;
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}|null  $actor
     * @return array{filename: string, base64: string, paso: int, paso_nombre: string, emitido_el: string|null, generado: bool, descargas: int}
     */
    private function resumenDeCotizacion(PagoProveedorCotizacion $cotizacion, ?array $actor, bool $contarDescarga): array
    {
        $presentado = $this->presenter->cotizacion($cotizacion, $this->configuracion->tariff());
        $this->exigirPuedeGenerarPdf($presentado);
        $paso = $this->documentos->pasoDeCotizacion($cotizacion);
        $referencia = $this->referenciaSolicitud($cotizacion);

        return $this->documentos->emitir(
            PagoProveedorDocumento::ENTITY_QUOTE,
            (int) $cotizacion->id,
            PagoProveedorDocumento::TYPE_SUMMARY,
            0,
            $paso,
            $this->huellaResumen($presentado),
            $this->nombreArchivo($presentado),
            fn (string $fecha) => $this->renderResumen($presentado, $fecha, $paso, $referencia),
            $actor,
            $contarDescarga
        );
    }

    /**
     * Borrador de una cotización todavía no guardada: no hay nada que cachear.
     *
     * @return array{filename: string, base64: string, paso: int, paso_nombre: string, emitido_el: string|null, generado: bool, descargas: int}
     */
    private function resumenSinGuardar(PagoProveedorCotizacion $cotizacion): array
    {
        $presentado = $this->presenter->cotizacion($cotizacion, $this->configuracion->tariff());
        $this->exigirPuedeGenerarPdf($presentado);
        $fecha = $this->fechaEmision();
        $paso = PagoProveedorDocumento::STEP_QUOTE;

        return [
            'filename' => $this->nombreArchivo($presentado),
            'base64' => base64_encode($this->renderResumen($presentado, $fecha, $paso, null)),
            'paso' => $paso,
            'paso_nombre' => PagoProveedorDocumento::stepLabel($paso),
            'emitido_el' => $fecha,
            'generado' => true,
            'descargas' => 0,
        ];
    }

    /**
     * Solo lo que muestra el PDF: si nada de esto cambia, se reutiliza el archivo.
     *
     * @param  array<string, mixed>  $presentado
     */
    private function huellaResumen(array $presentado): string
    {
        return sha1((string) json_encode([
            Arr::only($presentado, [
                'id',
                'codigo',
                'estado',
                'cod_cotizacion_cc',
                'consolidado',
                'cliente_nombre',
                'tipo_cambio',
                'proveedores_cantidad',
                'total',
                'total_ahora',
                'comisiones',
                'proveedores',
                'resumen',
            ]),
            explode(' ', (string) ($presentado['vigencia_hasta'] ?? ''))[0],
        ]));
    }

    private function referenciaSolicitud(PagoProveedorCotizacion $cotizacion): ?string
    {
        $cotizacion->loadMissing('solicitud');
        $solicitud = $cotizacion->solicitud;
        if ($solicitud === null) {
            return null;
        }

        return 'Solicitud N.° ' . $solicitud->order_number;
    }

    /**
     * @param  array<string, mixed>  $presentado
     */
    private function renderResumen(array $presentado, string $fecha, int $paso, ?string $referencia): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');
        $dompdf = new Dompdf($options);
        $metricas = $dompdf->getFontMetrics();
        $dompdf->loadHtml(view(
            'pago-proveedor.resumen',
            (new PagoProveedorResumenPdfVista($metricas))->datos($presentado, $fecha, $paso, $referencia)
        )->render());
        $dompdf->setPaper('A4');
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $fuente = $metricas->getFont('Helvetica');
        $puntosPorMm = 72 / 25.4;
        $canvas->page_text(
            (210 - 15) * $puntosPorMm - $metricas->getTextWidth('Página 1 de 1', $fuente, 7.5),
            283 * $puntosPorMm,
            'Página {PAGE_NUM} de {PAGE_COUNT}',
            $fuente,
            7.5,
            [115 / 255, 115 / 255, 115 / 255]
        );

        return (string) $dompdf->output();
    }

    /**
     * Datos del balance de cambio (yuanes obtenidos − yuanes pagados). También se emite sin diferencia.
     *
     * @return array<string, mixed>
     */
    private function datosBalance(PagoProveedorSolicitudProveedor $provider): array
    {
        $request = $provider->solicitud;
        $pagos = [];
        $cobrado = 0.0;
        $obtenido = 0.0;
        $pagado = 0.0;
        $diffUsd = 0.0;
        $tasas = [];
        foreach ($provider->ejecuciones->sortBy('supplier_payment') as $execution) {
            $usd = (float) $execution->cargo_amount;
            $got = (float) $execution->yuan_obtained;
            $paid = (float) $execution->yuan_paid;
            $rate = (float) $execution->real_exchange_rate;
            $rowDiff = (float) $execution->exchange_difference_usd;
            $cobrado += $usd;
            $obtenido += $got;
            $pagado += $paid;
            $diffUsd += $rowDiff;
            $tasas[] = $this->tasa($rate);
            $pagos[] = [
                'n' => (int) $execution->supplier_payment,
                'fecha' => $execution->executed_on ? $execution->executed_on->format('d/m/Y') : '—',
                'tc' => $rate,
                'tc_texto' => $this->tasa($rate),
                'usd' => $usd,
                'obtenido' => $got,
                'pagado' => $paid,
                'diff_rmb' => round($got - $paid, 2),
                'diff_usd' => $rowDiff,
            ];
        }
        if ($pagos === []) {
            throw new PagoProveedorException('Todavía no hay un pago registrado para armar el balance.');
        }

        $diffUsd = round($diffUsd, 2);
        $diffRmb = round($obtenido - $pagado, 2);
        // Negativo: faltaron yuanes y el cliente paga. Positivo: sobraron y se le devuelve.
        $tipo = 'cero';
        // Dentro de la tolerancia la diferencia la asume Pro Business: al cliente se le informa «Todo correcto».
        $enTolerancia = $provider->exchange_result === \App\Models\PagoProveedor\PagoProveedorEjecucion::RESULT_WITHIN;
        if ($enTolerancia) {
            $tipo = 'cero';
        } elseif ($diffUsd < -0.005) {
            $tipo = 'pagar';
        } elseif ($diffUsd > 0.005) {
            $tipo = 'devolver';
        }
        $montoDiferencia = '$' . number_format(abs($diffUsd), 2, '.', ',');
        $tc = implode(' / ', array_values(array_unique($tasas)));

        return [
            'cliente' => $this->nombrePropio((string) ($request->client_name ?? '')),
            'consolidado' => $request?->consolidation,
            'codigo_proveedor' => $provider->code_supplier,
            'nombre_proveedor' => $provider->supplier_name,
            'producto' => $provider->product,
            'tipo' => $tipo,
            'diferencia_titulo' => $tipo === 'devolver' ? 'DIFERENCIA A DEVOLVER' : 'DIFERENCIA A PAGAR',
            'diferencia_nota' => ['pagar' => 'Por depositar', 'devolver' => 'A tu favor', 'cero' => 'Todo correcto'][$tipo],
            'diferencia_usd' => $enTolerancia ? 0.0 : abs($diffUsd),
            'diferencia_texto' => $enTolerancia ? '$0.00' : $montoDiferencia,
            'diferencia_real_texto' => $montoDiferencia,
            'diferencia_rmb' => $diffRmb,
            'conclusion' => [
                'pagar' => 'Diferencia a pagar: ' . $montoDiferencia . '.',
                'devolver' => 'Diferencia a devolver: ' . $montoDiferencia . '.',
                'cero' => $enTolerancia && abs($diffUsd) > 0.005
                    ? 'Todo correcto: la diferencia de ' . $montoDiferencia . ' está dentro de la tolerancia y la asume Pro Business.'
                    : 'Todo correcto: no hay diferencia por pagar ni por devolver.',
            ][$tipo],
            'pagos_texto' => count($pagos) > 1 ? 'Balance general · ' . count($pagos) . ' pagos' : 'Pago único',
            'tc_texto' => $tc,
            'cobrado_usd' => round($cobrado, 2),
            'obtenido_rmb' => round($obtenido, 2),
            'pagado_rmb' => round($pagado, 2),
            'pagos' => $pagos,
            'mostrar_cuentas' => $tipo === 'pagar',
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function renderBalance(array $datos, string $fecha): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');
        $dompdf = new Dompdf($options);
        $metricas = $dompdf->getFontMetrics();
        $dompdf->loadHtml(view('pago-proveedor.balance', $this->vistaBalance($datos, $fecha, $metricas))->render());
        $dompdf->setPaper('A4');
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $fuente = $metricas->getFont('Helvetica');
        $puntosPorMm = 72 / 25.4;
        $canvas->page_text(
            (210 - 15) * $puntosPorMm - $metricas->getTextWidth('Página 1 de 1', $fuente, 7.5),
            283 * $puntosPorMm,
            'Página {PAGE_NUM} de {PAGE_COUNT}',
            $fuente,
            7.5,
            [115 / 255, 115 / 255, 115 / 255]
        );

        return (string) $dompdf->output();
    }

    /**
     * Textos, imágenes y tamaños de letra de la plantilla del balance (no entran en la huella del PDF).
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function vistaBalance(array $datos, string $fecha, FontMetrics $metricas): array
    {
        $imagen = fn (string $nombre) => 'data:image/png;base64,' . base64_encode((string) file_get_contents(public_path('pago-proveedor/pdf/' . $nombre . '.png')));
        $negrita = $metricas->getFont('Helvetica', 'bold');
        $ajustar = function (string $texto, float $anchoMm, array $tamanos) use ($metricas, $negrita): float {
            foreach ($tamanos as $tamano) {
                if ($metricas->getTextWidth($texto, $negrita, $tamano) * 25.4 / 72 <= $anchoMm) {
                    return (float) $tamano;
                }
            }

            return (float) end($tamanos);
        };
        $partes = array_map('intval', explode('/', $fecha));
        $fechaTexto = count($partes) === 3 ? sprintf('%02d/%02d/%04d', $partes[0], $partes[1], $partes[2]) : $fecha;

        $usd = fn (float $valor) => '$' . number_format($valor, 2, '.', ',');
        $rmb = fn (float $valor) => '¥' . number_format($valor, 2, '.', ',');
        $rmbConSigno = fn (float $valor) => ($valor < -0.004 ? '-' : ($valor > 0.004 ? '+' : '')) . $rmb(abs($valor));

        $tc = (string) $datos['tc_texto'];
        $tarjetas = [
            ['tipo' => 'total', 'ancho' => 70.5, 'icono' => 'billetera', 'etiqueta' => $datos['diferencia_titulo'], 'valor' => $datos['diferencia_texto'], 'nota' => $datos['diferencia_nota']],
            ['tipo' => 'gris', 'ancho' => 53, 'icono' => 'banco', 'etiqueta' => 'MONTO COBRADO', 'valor' => $usd((float) $datos['cobrado_usd']), 'nota' => 'Valor de la carga'],
            ['tipo' => 'gris', 'ancho' => 50.5, 'icono' => 'ruta', 'etiqueta' => 'PAGADO AL PROVEEDOR', 'valor' => $rmb((float) $datos['pagado_rmb']), 'nota' => 'Tipo de cambio real ' . $tc],
        ];
        foreach ($tarjetas as &$tarjeta) {
            $tarjeta['valor_pt'] = $ajustar($tarjeta['valor'], $tarjeta['ancho'] - 8, [22, 20, 18, 16, 14]);
        }
        unset($tarjeta);

        $diferenciaTotal = $rmbConSigno((float) $datos['diferencia_rmb']) . ' (aprox. ' . ($datos['diferencia_real_texto'] ?? $datos['diferencia_texto']) . ')';
        $pagos = $datos['pagos'];
        $filas = count($pagos) === 1
            ? [
                ['Monto cobrado (valor de la carga)', $usd((float) $pagos[0]['usd'])],
                ['Tipo de cambio aplicado', $pagos[0]['tc_texto']],
                ['Yuanes obtenidos', $rmb((float) $pagos[0]['obtenido'])],
                ['Yuanes pagados al proveedor', $rmb((float) $pagos[0]['pagado'])],
                ['Diferencia', $diferenciaTotal],
            ]
            : [
                ['Fecha del pago', ...array_column($pagos, 'fecha'), '—'],
                ['Monto cobrado', ...array_map(fn ($p) => $usd((float) $p['usd']), $pagos), $usd((float) $datos['cobrado_usd'])],
                ['Tipo de cambio aplicado', ...array_column($pagos, 'tc_texto'), '—'],
                ['Yuanes obtenidos', ...array_map(fn ($p) => $rmb((float) $p['obtenido']), $pagos), $rmb((float) $datos['obtenido_rmb'])],
                ['Yuanes pagados al proveedor', ...array_map(fn ($p) => $rmb((float) $p['pagado']), $pagos), $rmb((float) $datos['pagado_rmb'])],
                ['Diferencia', ...array_map(fn ($p) => $rmbConSigno((float) $p['diff_rmb']), $pagos), $diferenciaTotal],
            ];

        $pagosTexto = (string) $datos['pagos_texto'];
        $tcCliente = '1 USD = ' . $tc . ' RMB';

        return [
            'logo' => $imagen('logo'),
            'iconos' => array_map($imagen, [
                'persona' => 'persona_n',
                'billetera' => 'billetera_o',
                'documento' => 'doc_n',
                'caja' => 'caja_n',
                'banco' => 'banco_n',
                'ruta' => 'ruta_n',
            ]),
            'fecha' => $fechaTexto,
            'datos_cliente' => [
                ['etiqueta' => 'Cliente', 'valor' => $datos['cliente'] ?: '—', 'ancho' => 54.7, 'pt' => $ajustar($datos['cliente'] ?: '—', 50, [10.5, 9.5, 8.5, 7.5])],
                ['etiqueta' => 'N.° de consolidado', 'valor' => (string) ($datos['consolidado'] ?: '—'), 'ancho' => 33.6, 'pt' => $ajustar((string) ($datos['consolidado'] ?: '—'), 28.5, [10.5, 9.5, 8.5, 7.5])],
                ['etiqueta' => 'Pagos al proveedor', 'valor' => $pagosTexto, 'ancho' => 43.7, 'pt' => $ajustar($pagosTexto, 39, [10.5, 9.5, 8.5, 7.5])],
                ['etiqueta' => 'Tipo de cambio aplicado', 'valor' => $tcCliente, 'ancho' => 48, 'pt' => $ajustar($tcCliente, 43.5, [10.5, 9.5, 8.5, 7.5])],
            ],
            'datos_proveedor' => [
                ['etiqueta' => 'Código de proveedor', 'valor' => (string) ($datos['codigo_proveedor'] ?: '—'), 'ancho' => 47.4],
                ['etiqueta' => 'Proveedor', 'valor' => (string) ($datos['nombre_proveedor'] ?: '—'), 'ancho' => 79],
                ['etiqueta' => 'Producto', 'valor' => (string) ($datos['producto'] ?: '—'), 'ancho' => 53.6],
            ],
            'tarjetas' => $tarjetas,
            'varios_pagos' => count($pagos) > 1,
            'columnas' => array_map(fn ($p) => 'Pago ' . $p['n'], $pagos),
            'filas' => $filas,
            'conclusion' => $datos['conclusion'],
            'mostrar_cuentas' => (bool) $datos['mostrar_cuentas'],
            'cuentas' => [
                ['banco' => 'BCP', 'color' => '#004a99', 'cuenta' => '191-4216222-1-10', 'cci' => '002-191-00421622211-0-57'],
                ['banco' => 'Interbank', 'color' => '#009650', 'cuenta' => '200-3006218820', 'cci' => '003-200-003006218820-33'],
            ],
        ];
    }

    private function tasa(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 4, '.', ''), '0'), '.');
    }

    /**
     * «CAMILA RÍOS SOTO» → «Camila Ríos Soto»; las partículas van en minúscula salvo al inicio.
     */
    private function nombrePropio(string $nombre): string
    {
        $palabras = preg_split('/\s+/u', trim($nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $resultado = [];
        foreach ($palabras as $indice => $palabra) {
            $minuscula = mb_strtolower($palabra, 'UTF-8');
            $resultado[] = $indice > 0 && in_array($minuscula, ['de', 'del', 'la', 'las', 'los', 'y', 'e'], true)
                ? $minuscula
                : mb_convert_case($minuscula, MB_CASE_TITLE, 'UTF-8');
        }

        return implode(' ', $resultado);
    }

    /**
     * @param  array<string, mixed>  $presentado
     */
    private function nombreArchivo(array $presentado): string
    {
        $cliente = preg_replace('/[^A-Za-z0-9]+/', '_', Str::ascii(trim((string) ($presentado['cliente_nombre'] ?? '')))) ?: 'cliente';

        return 'Resumen_pago_proveedor_' . $cliente . '.pdf';
    }

    private function fechaEmision(): string
    {
        return now()->timezone(PagoProveedorVigenciaService::ZONA)->format('j/n/Y');
    }

    /**
     * @param  array<string, mixed>  $presentado
     */
    private function exigirPuedeGenerarPdf(array $presentado): void
    {
        $faltantes = $presentado['faltantes_pdf'] ?? [];
        if (is_array($faltantes) && count($faltantes) > 0) {
            throw new PagoProveedorException(implode(' ', $faltantes));
        }
        if (($presentado['estado'] ?? '') === 'pendiente' && !empty($presentado['vencida'])) {
            throw new PagoProveedorException('La cotización está vencida. Renuévala para descargar el resumen.');
        }
        if (empty($presentado['puede_descargar_pdf'])) {
            throw new PagoProveedorException('Aún no se puede generar el PDF de resumen.');
        }
    }
}
