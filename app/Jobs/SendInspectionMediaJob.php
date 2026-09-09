<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\AlmacenInspection;
use App\Support\WhatsApp\CoordinacionWhatsappPayload;
use App\Traits\WhatsappTrait;
use App\Traits\DatabaseConnectionTrait;
use Carbon\Carbon;

class SendInspectionMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, WhatsappTrait, DatabaseConnectionTrait;

    protected $idProveedor;
    protected $idCotizacion;
    protected $idsProveedores;
    protected $userId;
    protected $domain;

    /**
     * Número de intentos antes de fallar
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Tiempo de espera del job en segundos
     *
     * @var int
     */
    public $timeout = 300;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($idProveedor, $idCotizacion, $idsProveedores, $userId = null, $domain = null)
    {
        $this->idProveedor = $idProveedor;
        $this->idCotizacion = $idCotizacion;
        $this->idsProveedores = $idsProveedores;
        $this->userId = $userId;
        $this->domain = $domain;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            // Establecer la conexión de BD basándose en el dominio
            $this->setDatabaseConnection($this->domain);

            Log::info("Iniciando job de envío de inspección", [
                'id_proveedor' => $this->idProveedor,
                'id_cotizacion' => $this->idCotizacion,
                'user_id' => $this->userId,
                'domain' => $this->domain
            ]);

            // Obtener imágenes del proveedor
            $imagesUrls = AlmacenInspection::where('id_proveedor', $this->idProveedor)
                ->whereIn('file_type', ['image/jpeg', 'image/png', 'image/jpg'])
                ->select(['id', 'file_path', 'file_type', 'send_status'])
                ->get();

            // Obtener videos del proveedor
            $videosUrls = AlmacenInspection::where('id_proveedor', $this->idProveedor)
                ->where('file_type', 'video/mp4')
                ->select(['id', 'file_path', 'file_type', 'send_status'])
                ->get();

            // Obtener datos del proveedor
            $proveedor = CotizacionProveedor::where('id', $this->idProveedor)
                ->select(['estados_proveedor', 'code_supplier', 'qty_box_china', 'qty_box', 'qty_pallet_china', 'id_cotizacion'])
                ->first();

            if (!$proveedor) {
                throw new \Exception('Proveedor no encontrado: ' . $this->idProveedor);
            }

            // Obtener datos de la cotización (uuid para link de vista inspección)
            $cotizacion = Cotizacion::where('id', $proveedor->id_cotizacion)
                ->select(['volumen', 'monto', 'id_contenedor', 'nombre', 'telefono', 'uuid'])
                ->first();

            if (!$cotizacion) {
                throw new \Exception('Cotización no encontrada: ' . $proveedor->id_cotizacion);
            }

            // Obtener fecha de cierre del contenedor
            $contenedor = DB::table('carga_consolidada_contenedor')->where('id', $cotizacion->id_contenedor)->first();
            $fCierre = $contenedor ? $contenedor->f_cierre : null;

            // Formatear fecha de cierre
            if ($fCierre) {
                $fCierre = Carbon::parse($fCierre)->format('d F');
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
                $fCierre = str_replace(array_keys($meses), array_values($meses), $fCierre);
            }

            // Actualizar estado del proveedor
            $proveedorUpdate = CotizacionProveedor::find($this->idProveedor);
            $proveedorUpdate->estados_proveedor = 'INSPECTION';
            $proveedorUpdate->estados = 'INSPECCIONADO';
            $proveedorUpdate->save();

            // Actualizar tracking siguiendo el patrón correcto
            $ahora = now();
            
            // Obtener el registro más reciente del tracking
            $trackingActual = DB::table('contenedor_proveedor_estados_tracking')
                ->where('id_proveedor', $this->idProveedor)
                ->where('id_cotizacion', $this->idCotizacion)
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($trackingActual) {
                // Actualizar el registro existente con updated_at
                DB::table('contenedor_proveedor_estados_tracking')
                    ->where('id', $trackingActual->id)
                    ->update(['updated_at' => $ahora]);
            }

            // Insertar nuevo registro con el estado INSPECCIONADO
            DB::table('contenedor_proveedor_estados_tracking')
                ->insert([
                    'id_proveedor' => $this->idProveedor,
                    'id_cotizacion' => $this->idCotizacion,
                    'estado' => 'INSPECCIONADO',
                    'created_at' => $ahora,
                    'updated_at' => $ahora
                ]);

            Log::info("Estado del proveedor actualizado", [
                'id_proveedor' => $this->idProveedor,
                'nuevo_estado' => 'INSPECCIONADO'
            ]);

            // Preparar datos para el mensaje
            $cliente = $cotizacion->nombre;
            $telefono = $cotizacion->telefono;
            $telefono = preg_replace('/\s+/', '', $telefono);
            $telefono = $telefono . '@c.us';

            $sendStatus = true;
            $cotizacionProviders = CotizacionProveedor::where('id_cotizacion', $this->idCotizacion)->get();

            if (count($cotizacionProviders) != count($this->idsProveedores)) {
                $sendStatus = false;
            }

            // Solo pb_inspeccion_llegada_v1 (máx. 1 vez/proveedor). Sin imagen/video WA.
            $qtyBoxChina = (int) ($proveedor->qty_box_china ?? $proveedor->qty_box ?? 0);
            $qtyPalletChina = (int) ($proveedor->qty_pallet_china ?? 0);
            $baseUrl = rtrim((string) config('app.url_clientes'), '/');
            $inspeccionLink = $baseUrl . '/inspeccion/' . ($cotizacion->uuid ?? '') . '?id_proveedor=' . $this->idProveedor;
            $resolved = CoordinacionWhatsappPayload::resolveInspeccionLlegadaTemplate($qtyBoxChina, $qtyPalletChina);
            $message = CoordinacionWhatsappPayload::inspeccionLlegadaPreview(
                (string) $cliente,
                (string) $proveedor->code_supplier,
                $resolved['cantidad_line'],
                $inspeccionLink
            );

            $alreadySentLlegada = AlmacenInspection::where('id_proveedor', $this->idProveedor)
                ->where('send_status', 'SENDED')
                ->exists();

            $llegadaEnviada = false;
            if (!$alreadySentLlegada) {
                $metaLlegada = CoordinacionWhatsappPayload::inspeccionLlegada(
                    $telefono,
                    (string) $cliente,
                    (string) $proveedor->code_supplier,
                    $qtyBoxChina,
                    $qtyPalletChina,
                    $inspeccionLink,
                    $message
                );
                $this->sendMessage($message, $telefono, 0, 'consolidado', $metaLlegada);
                $llegadaEnviada = true;
                Log::info('Mensaje pb_inspeccion_llegada_v1 enviado', ['telefono' => $telefono]);
            } else {
                Log::info('Inspección job: se omite pb_inspeccion_llegada_v1 (ya enviado)', [
                    'id_proveedor' => $this->idProveedor,
                ]);
            }

            // Marcar media como SENDED sin enviar plantillas imagen/video
            $imagenesMarcadas = 0;
            foreach ($imagesUrls as $image) {
                if (($image->send_status ?? '') !== 'SENDED') {
                    AlmacenInspection::where('id', $image->id)->update(['send_status' => 'SENDED']);
                    $imagenesMarcadas++;
                }
            }
            $videosMarcados = 0;
            foreach ($videosUrls as $video) {
                if (($video->send_status ?? '') !== 'SENDED') {
                    AlmacenInspection::where('id', $video->id)->update(['send_status' => 'SENDED']);
                    $videosMarcados++;
                }
            }

            Log::info('Job de inspección completado exitosamente', [
                'id_proveedor' => $this->idProveedor,
                'llegada_enviada' => $llegadaEnviada,
                'imagenes_marcadas' => $imagenesMarcadas,
                'videos_marcados' => $videosMarcados,
                'mensaje_principal_enviado' => $sendStatus,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en SendInspectionMediaJob: ' . $e->getMessage(), [
                'id_proveedor' => $this->idProveedor,
                'id_cotizacion' => $this->idCotizacion,
                'trace' => $e->getTraceAsString()
            ]);

            // Re-lanzar la excepción para que el job falle y se reintente
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::error('SendInspectionMediaJob falló después de todos los intentos', [
            'id_proveedor' => $this->idProveedor,
            'id_cotizacion' => $this->idCotizacion,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // Aquí podrías enviar una notificación al administrador o revertir cambios
        // Por ejemplo, cambiar el estado del proveedor de vuelta si es necesario
    }
}
