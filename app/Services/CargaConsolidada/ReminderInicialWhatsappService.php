<?php

namespace App\Services\CargaConsolidada;

use App\Jobs\ForceSendCobrandoJob;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\Cotizacion;
use App\Traits\FileTrait;
use Carbon\Carbon;

class ReminderInicialWhatsappService
{
    use FileTrait;

    /**
     * @return array{success:bool,message?:string,data?:array<string,mixed>}
     */
    public function preview(int $idCotizacion): array
    {
        $payload = $this->buildPayload($idCotizacion);
        if ($payload === null) {
            return ['success' => false, 'message' => 'Cotización no encontrada'];
        }
        if ($payload['phone'] === '') {
            return ['success' => false, 'message' => 'El cliente no tiene un teléfono válido'];
        }

        return [
            'success' => true,
            'data' => [
                'id_cotizacion' => $idCotizacion,
                'cliente' => $payload['nombre'],
                'phone' => $payload['phone'],
                'carga' => $payload['carga'],
                'message' => $payload['message'],
                'has_excel' => $payload['has_excel'],
                'excel_url' => $payload['excel_url'],
            ],
        ];
    }

    /**
     * @return array{nombre:string,phone:string,phone_id:string,carga:string,id_container:int,message:string,has_excel:bool,excel_url:?string}|null
     */
    public function buildPayload(int $idCotizacion, $idContainer = null): ?array
    {
        $cotizacion = Cotizacion::find($idCotizacion);
        if (!$cotizacion) {
            return null;
        }

        $containerId = $idContainer !== null && (int) $idContainer > 0
            ? (int) $idContainer
            : (int) ($cotizacion->id_contenedor ?? 0);

        $contenedor = $containerId > 0 ? Contenedor::find($containerId) : null;
        if (!$contenedor) {
            return null;
        }

        $carga = (string) ($contenedor->carga ?? 'N/A');
        $fechaCierre = $contenedor->f_cierre;
        $anioContenedor = $fechaCierre ? Carbon::parse($fechaCierre)->year : Carbon::now()->year;
        $fCierre = '';
        if ($fechaCierre) {
            $fCierre = Carbon::parse($fechaCierre)->locale('es')->format('d F');
            $meses = [
                'January' => 'Enero',
                'February' => 'Febrero',
                'March' => 'Marzo',
                'April' => 'Abril',
                'May' => 'Mayo',
                'June' => 'Junio',
                'July' => 'Julio',
                'August' => 'Agosto',
                'September' => 'Septiembre',
                'October' => 'Octubre',
                'November' => 'Noviembre',
                'December' => 'Diciembre',
            ];
            $fCierre = strtr($fCierre, $meses);
        }

        $volumen = $cotizacion->volumen;
        $valorCot = $cotizacion->monto;
        $cliente = (string) ($cotizacion->nombre ?? '');

        $message = "Hola " . $cliente . ", te escribe el área de contabilidad de Probusiness. \n\n" .
            "Reserva de espacio:\n" .
            "*Consolidado #" . $carga . "-$anioContenedor*\n\n" .
            "Ahora tienes que hacer el pago del CBM preliminar para poder subir su carga en nuestro contenedor.\n\n" .
            "☑ CBM Preliminar: " . $volumen . " cbm\n" .
            "☑ Costo CBM: $" . $valorCot . "\n\n" .
            "📅 Fecha Limite de pago: " . $fCierre . "\n\n" .
            "⚠ Nota: Realizar el pago antes del llenado del contenedor.\n\n" .
            "📦 En caso hubiera variaciones en el cubicaje se cobrará la diferencia en la cotización final.\n\n" .
            "Apenas haga el pago, envíe por este medio para hacer la reserva.";

        $phone = $this->normalizePhone((string) ($cotizacion->telefono ?? ''));
        $excelUrl = $this->cdnStorageUrl($cotizacion->cotizacion_file_url ?? null);

        return [
            'nombre' => $cliente,
            'phone' => $phone,
            'phone_id' => $phone !== '' ? $phone . '@c.us' : '',
            'carga' => $carga,
            'id_container' => $containerId,
            'message' => $message,
            'has_excel' => $excelUrl !== null && $excelUrl !== '',
            'excel_url' => $excelUrl,
        ];
    }

    public function enqueue(int $idCotizacion, $idContainer = null, $domain = null): void
    {
        $containerId = $idContainer !== null ? (int) $idContainer : 0;
        if ($containerId <= 0) {
            $cotizacion = Cotizacion::find($idCotizacion);
            $containerId = $cotizacion ? (int) $cotizacion->id_contenedor : 0;
        }

        ForceSendCobrandoJob::dispatch($idCotizacion, $containerId, $domain);
    }

    public function normalizePhone(string $rawTelefono): string
    {
        $telefonoDigits = preg_replace('/\D/', '', $rawTelefono) ?? '';
        if (strlen($telefonoDigits) === 9) {
            return '51' . $telefonoDigits;
        }
        if (strlen($telefonoDigits) === 10 && substr($telefonoDigits, 0, 1) === '0') {
            return '51' . substr($telefonoDigits, 1);
        }

        return $telefonoDigits;
    }
}
