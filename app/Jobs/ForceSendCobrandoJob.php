<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\Contenedor;
use App\Traits\WhatsappTrait;
use App\Traits\DatabaseConnectionTrait;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class ForceSendCobrandoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, WhatsappTrait, DatabaseConnectionTrait;

    protected $idCotizacion;
    protected $idContainer;
    protected $domain;

    /**
     * Create a new job instance.
     */
    public function __construct($idCotizacion, $idContainer, $domain = null)
    {
        $this->idCotizacion = $idCotizacion;
        $this->idContainer = $idContainer;
        $this->domain = $domain;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Establecer la conexión de BD basándose en el dominio
            $this->setDatabaseConnection($this->domain);

            Log::info("Iniciando Job ForceSendCobrando", [
                'id_cotizacion' => $this->idCotizacion,
                'id_container' => $this->idContainer,
                'domain' => $this->domain
            ]);

            // Obtener información de la cotización
            $cotizacionInfo = Cotizacion::findOrFail($this->idCotizacion);
            
            $volumen = $cotizacionInfo->volumen;
            $valorCot = $cotizacionInfo->monto;
            $telefono = $cotizacionInfo->telefono;
            $cliente = $cotizacionInfo->nombre;

            // Obtener información del contenedor
            $contenedor = Contenedor::findOrFail($this->idContainer);
            $carga = $contenedor->carga;
            $fechaCierre = $contenedor->f_cierre;
            $anioContenedor = Carbon::parse($fechaCierre)->year;
            // Formatear fecha de cierre
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
                'December' => 'Diciembre'
            ];
            $fCierre = strtr($fCierre, $meses);

            // Configurar teléfono para WhatsApp
            $telefono = preg_replace('/\s+/', '', $telefono);
            $this->phoneNumberId = $telefono ? $telefono . '@c.us' : '';

            // Construir mensaje de cobranza
            // Calcular suma y conteo de pagos del concepto LOGISTICA para esta cotización
            try {
                $queryPagos = DB::table('contenedor_consolidado_cotizacion_coordinacion_pagos as P')
                    ->join('cotizacion_coordinacion_pagos_concept as C', 'P.id_concept', '=', 'C.id')
                    ->where('P.id_cotizacion', $this->idCotizacion)
                    ->where('C.name', 'LOGISTICA');

                $totalPagosLogistica = $queryPagos->sum('P.monto');
                $countPagosLogistica = $queryPagos->count();
            } catch (\Exception $e) {
                Log::warning('Error calculando pagos LOGISTICA (ForceSendCobrandoJob): ' . $e->getMessage(), ['id_cotizacion' => $this->idCotizacion]);
                $totalPagosLogistica = 0;
                $countPagosLogistica = 0;
            }

            $pendiente = (float)($valorCot ?? 0) - (float)$totalPagosLogistica;
            if ($pendiente < 0) {
                $pendiente = 0;
            }

            // Tomar el año de la fecha de inicio del contenedor
            $message = "Reserva de espacio:\n" .
                "*Consolidado #" . $carga . "-$anioContenedor*\n\n" .
                "Ahora tienes que hacer el pago del CBM preliminar para poder subir su carga en nuestro contenedor.\n\n" .
                "☑ CBM Preliminar: " . $volumen . " cbm\n" .
                "☑ Costo CBM: $" . $valorCot . "\n";

            if (!empty($countPagosLogistica) && $countPagosLogistica > 0) {
                $message .= "☑ Pendiente de pago CBM: $" . number_format($pendiente, 2) . "\n\n";
            }

            $message .= "📅 Fecha Limite de pago: " . $fCierre . "\n\n" .
                "⚠ Nota: Realizar el pago antes del llenado del contenedor.\n\n" .
                "📦 En caso hubiera variaciones en el cubicaje se cobrará la diferencia en la cotización final.\n\n" .
                "Apenas haga el pago, envíe por este medio para hacer la reserva.";

            // Enviar mensaje
            $this->sendMessage($message,'administracion');

            // Enviar imagen de pagos
            $pagosUrl = public_path('assets/images/pagos-full.jpg');
            $this->sendMedia($pagosUrl, 'image/jpg',null,$telefono,10,'administracion');

            Log::info("Mensaje de cobranza enviado exitosamente via Job", [
                'id_cotizacion' => $this->idCotizacion,
                'cliente' => $cliente,
                'telefono' => $telefono,
                'volumen' => $volumen,
                'monto' => $valorCot
            ]);

        } catch (Exception $e) {
            Log::error('Error en ForceSendCobrandoJob: ' . $e->getMessage(), [
                'id_cotizacion' => $this->idCotizacion,
                'id_container' => $this->idContainer,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('ForceSendCobrandoJob falló', [
            'id_cotizacion' => $this->idCotizacion,
            'id_container' => $this->idContainer,
            'error' => $exception->getMessage()
        ]);
    }
}
