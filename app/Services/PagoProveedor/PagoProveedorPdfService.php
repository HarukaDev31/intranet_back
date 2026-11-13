<?php

namespace App\Services\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Models\PagoProveedor\PagoProveedorCotizacion;
use App\Models\PagoProveedor\PagoProveedorDocumento;
use App\Models\PagoProveedor\PagoProveedorEjecucion;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;
use Dompdf\Dompdf;
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
            $pagos[] = [
                'n' => (int) $execution->supplier_payment,
                'tc' => $rate,
                'usd' => $usd,
                'obtenido' => $got,
                'pagado' => $paid,
                'diff_usd' => $rowDiff,
            ];
        }
        if ($pagos === []) {
            throw new PagoProveedorException('Todavía no hay un pago registrado para armar el balance.');
        }
        $diffUsd = round($diffUsd, 2);
        $label = 'Diferencia';
        if ($provider->exchange_result === PagoProveedorEjecucion::RESULT_COLLECT) {
            $label = 'Diferencia a pagar';
        } elseif ($provider->exchange_result === PagoProveedorEjecucion::RESULT_REFUND) {
            $label = 'Diferencia a devolver';
        } elseif (abs($diffUsd) <= 0.009) {
            $label = 'Sin diferencia';
        }

        return [
            'cliente' => $request?->client_name,
            'consolidado' => $request?->consolidation,
            'codigo_proveedor' => $provider->code_supplier,
            'nombre_proveedor' => $provider->supplier_name,
            'producto' => $provider->product,
            'diferencia_label' => $label,
            'diferencia_usd' => abs($diffUsd),
            'cobrado_usd' => round($cobrado, 2),
            'obtenido_rmb' => round($obtenido, 2),
            'pagado_rmb' => round($pagado, 2),
            'pagos' => $pagos,
            'mostrar_cuentas' => $provider->exchange_result === PagoProveedorEjecucion::RESULT_COLLECT,
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function renderBalance(array $datos, string $fecha): string
    {
        $html = view('pago-proveedor.balance', ['fecha' => $fecha] + $datos)->render();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
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
