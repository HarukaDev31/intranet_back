<?php

namespace App\Services\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Models\PagoProveedor\PagoProveedorCotizacion;
use App\Models\PagoProveedor\PagoProveedorEjecucion;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;
use Dompdf\Dompdf;
use Dompdf\Options;

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
     * @return array{quote_id: int, filename: string, binary: string}
     */
    private function buildResumen(int $id): array
    {
        $cotizacion = $this->cotizaciones->encontrar($id);
        $presentado = $this->presenter->cotizacion($cotizacion, $this->configuracion->tariff());
        $this->exigirPuedeGenerarPdf($presentado);

        $cuentasPath = public_path('pago-proveedor/cuentas-bancarias.jpg');
        $cuentasImagen = is_file($cuentasPath)
            ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($cuentasPath))
            : null;
        $hayCuotas = collect($presentado['proveedores'] ?? [])->contains(function ($proveedor) {
            $mode = $proveedor['tipo_fraccionamiento']
                ?? $proveedor['client_payment_mode']
                ?? $proveedor['modalidad']
                ?? '';

            return in_array($mode, ['clienteFracciona', 'client_installments', 'Fraccionado', 'fraccionado'], true)
                || (($proveedor['cuotas'] ?? null) === 2);
        });

        $html = view('pago-proveedor.resumen', [
            'cotizacion' => $presentado,
            'fecha' => now()->timezone(PagoProveedorVigenciaService::ZONA)->format('d/m/Y'),
            'cuentas_imagen' => $cuentasImagen,
            'hay_cuotas' => $hayCuotas,
        ])->render();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        return [
            'quote_id' => (int) $cotizacion->id,
            'filename' => 'Resumen_pago_proveedor_' . $cotizacion->code . '.pdf',
            'binary' => $dompdf->output(),
        ];
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
