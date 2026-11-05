<?php

namespace App\Services\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Models\PagoProveedor\PagoProveedorCotizacion;
use App\Models\PagoProveedor\PagoProveedorEjecucion;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PagoProveedorPdfService
{
    public function __construct(
        private readonly PagoProveedorCotizacionService $cotizaciones,
        private readonly PagoProveedorCotizacionPresenter $presenter,
        private readonly PagoProveedorConfiguracionService $configuracion,
        private readonly PagoProveedorHistorialService $historial,
    ) {
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array{filename: string, base64: string}
     */
    public function resumen(int $id, array $actor): array
    {
        $built = $this->buildResumen($id);
        $this->historial->registrar('cotizacion', $built['quote_id'], 'PDF descargado', $actor);

        return [
            'filename' => $built['filename'],
            'base64' => base64_encode($built['binary']),
        ];
    }

    /**
     * PDF temporal para adjuntar en WhatsApp (no registra descarga).
     *
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array{path: string, filename: string}
     */
    public function resumenTemporal(int $id, array $actor): array
    {
        unset($actor);
        $built = $this->buildResumen($id);
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pp-resumen-' . $id . '-' . uniqid('', true) . '.pdf';
        if (file_put_contents($path, $built['binary']) === false) {
            throw new PagoProveedorException('No se pudo preparar el PDF para WhatsApp.');
        }

        return [
            'path' => $path,
            'filename' => $built['filename'],
        ];
    }

    /**
     * PDF con los datos del formulario, aunque la cotización no esté guardada.
     *
     * @param  array<string, mixed>  $datos
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array{filename: string, base64: string}
     */
    public function resumenBorrador(array $datos, array $actor): array
    {
        $cotizacion = $this->cotizaciones->borrador($datos);
        $resumen = $this->resumenCacheado($cotizacion);
        if ($cotizacion->id) {
            $this->historial->registrar('cotizacion', $cotizacion->id, 'PDF descargado', $actor);
        }

        return $resumen;
    }

    /**
     * @return array{path: string, filename: string}
     */
    public function temporalDeBorrador(PagoProveedorCotizacion $cotizacion): array
    {
        $resumen = $this->resumenCacheado($cotizacion);
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pp-resumen-' . uniqid('', true) . '.pdf';
        if (file_put_contents($path, base64_decode($resumen['base64'])) === false) {
            throw new PagoProveedorException('No se pudo preparar el PDF para WhatsApp.');
        }

        return ['path' => $path, 'filename' => $resumen['filename']];
    }

    /**
     * Solo se regenera si cambia lo que muestra el PDF, la fecha o la plantilla.
     *
     * @return array{filename: string, base64: string}
     */
    private function resumenCacheado(PagoProveedorCotizacion $cotizacion): array
    {
        $presentado = $this->presenter->cotizacion($cotizacion, $this->configuracion->tariff());
        $this->exigirPuedeGenerarPdf($presentado);

        $fecha = $this->fechaEmision();
        $huella = sha1((string) json_encode([
            Arr::only($presentado, ['id', 'codigo', 'estado', 'cod_cotizacion_cc', 'consolidado', 'cliente_nombre', 'tipo_cambio', 'proveedores_cantidad', 'total', 'total_ahora', 'comisiones', 'proveedores', 'resumen']),
            explode(' ', (string) ($presentado['vigencia_hasta'] ?? ''))[0],
            $fecha,
            filemtime(resource_path('views/pago-proveedor/resumen.blade.php')),
            filemtime(app_path('Services/PagoProveedor/PagoProveedorResumenPdfVista.php')),
        ]));

        return [
            'filename' => $this->nombreArchivo($presentado),
            'base64' => Cache::remember(
                'pago-proveedor:resumen-pdf:' . $huella,
                now()->addDay(),
                fn () => base64_encode($this->renderResumen($presentado, $fecha))
            ),
        ];
    }

    /**
     * @return array{quote_id: int, filename: string, binary: string}
     */
    private function buildResumen(int $id): array
    {
        $cotizacion = $this->cotizaciones->encontrar($id);
        $presentado = $this->presenter->cotizacion($cotizacion, $this->configuracion->tariff());
        $this->exigirPuedeGenerarPdf($presentado);

        return [
            'quote_id' => (int) $cotizacion->id,
            'filename' => $this->nombreArchivo($presentado),
            'binary' => $this->renderResumen($presentado, $this->fechaEmision()),
        ];
    }

    /**
     * @param  array<string, mixed>  $presentado
     */
    private function renderResumen(array $presentado, string $fecha): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');
        $dompdf = new Dompdf($options);
        $metricas = $dompdf->getFontMetrics();
        $dompdf->loadHtml(view(
            'pago-proveedor.resumen',
            (new PagoProveedorResumenPdfVista($metricas))->datos($presentado, $fecha)
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

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array{filename: string, base64: string}
     */
    public function balance(PagoProveedorSolicitudProveedor $provider, array $actor): array
    {
        $provider->loadMissing(['solicitud', 'ejecuciones']);
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
        $html = view('pago-proveedor.balance', [
            'fecha' => now()->timezone(PagoProveedorVigenciaService::ZONA)->format('d/m/Y'),
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
        ])->render();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        $code = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $provider->code_supplier) ?: (string) $provider->id;
        $this->historial->registrar(
            \App\Models\PagoProveedor\PagoProveedorHistorial::ENTITY_REQUEST,
            (int) $provider->request_id,
            'PDF de balance descargado',
            $actor,
            $provider->code_supplier
        );

        return [
            'filename' => 'Balance_'.$code.'.pdf',
            'base64' => base64_encode($dompdf->output()),
        ];
    }
}
