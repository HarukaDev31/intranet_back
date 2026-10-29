<?php

namespace App\Services\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Models\PagoProveedor\PagoProveedorCotizacion;
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
        $cotizacion = $this->cotizaciones->encontrar($id);
        $presentado = $this->presenter->cotizacion($cotizacion, $this->configuracion->tarifa());
        if (!$presentado['puede_enviar']) {
            throw new PagoProveedorException(
                $presentado['vencida']
                    ? 'La cotización está vencida. Renuévala para descargar el resumen.'
                    : implode(' ', $presentado['faltantes_pdf'])
            );
        }

        $html = view('pago-proveedor.resumen', [
            'cotizacion' => $presentado,
            'fecha' => now()->timezone(PagoProveedorVigenciaService::ZONA)->format('d/m/Y'),
        ])->render();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        $this->historial->registrar('cotizacion', $cotizacion->id, 'PDF descargado', $actor);

        return [
            'filename' => 'Resumen_pago_proveedor_' . $cotizacion->codigo . '.pdf',
            'base64' => base64_encode($dompdf->output()),
        ];
    }
}
