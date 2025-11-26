<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\AlmacenInspection;
use App\Traits\WhatsappTrait;
use Carbon\Carbon;

class SendInspectionMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, WhatsappTrait;

    protected $idProveedor;
    protected $idCotizacion;
    protected $idsProveedores;
    protected $userId;

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
    public function __construct($idProveedor, $idCotizacion, $idsProveedores, $userId = null)
    {
        $this->idProveedor = $idProveedor;
        $this->idCotizacion = $idCotizacion;
        $this->idsProveedores = $idsProveedores;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            Log::info("Iniciando job de envío de inspección", [
                'id_proveedor' => $this->idProveedor,
                'id_cotizacion' => $this->idCotizacion,
                'user_id' => $this->userId
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
                ->select(['estados_proveedor', 'code_supplier', 'qty_box_china', 'qty_box', 'id_cotizacion'])
                ->first();

            if (!$proveedor) {
                throw new \Exception('Proveedor no encontrado: ' . $this->idProveedor);
            }

            // Obtener datos de la cotización
            $cotizacion = Cotizacion::where('id', $proveedor->id_cotizacion)
                ->select(['volumen', 'monto', 'id_contenedor', 'nombre', 'telefono'])
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

            // Enviar mensaje principal si corresponde

            $qtyBox = $proveedor->qty_box_china ?? $proveedor->qty_box;
            $message = $cliente . '----' . $proveedor->code_supplier . '----' . $qtyBox . ' boxes. ' . "\n\n" .
                '📦 Tu carga llegó a nuestro almacén de Yiwu, te comparto las fotos y videos. ' . "\n\n";

            $this->sendMessage($message, $telefono);
            Log::info("Mensaje principal enviado", ['telefono' => $telefono]);


            // Obtener código del proveedor para el mensaje
            $codeSupplier = $proveedor->code_supplier;

            // Procesar y enviar imágenes
            $imagenesEnviadas = 0;
            foreach ($imagesUrls as $image) {
                // Generar URL pública del archivo
                $publicUrl = $this->generatePublicUrl($image->file_path);
                
                if ($publicUrl) {
                    // Mensaje con código del proveedor
                    $message = $codeSupplier;
                    $this->sendMediaInspectionToController($image->file_path, $image->file_type, $message, $telefono, 0, $image->id);
                    $imagenesEnviadas++;
                    Log::info('Imagen enviada con URL', [
                        'file_path' => $image->file_path,
                        'url' => $publicUrl,
                        'code_supplier' => $codeSupplier
                    ]);
                } else {
                    Log::error('No se pudo generar URL pública para imagen: ' . $image->file_path);
                }
            }

            // Procesar y enviar videos
            $videosEnviados = 0;
            foreach ($videosUrls as $video) {
                // Generar URL pública del archivo
                $publicUrl = $this->generatePublicUrl($video->file_path);
                
                if ($publicUrl) {
                    // Mensaje con código del proveedor
                    $message = $codeSupplier;
                    $this->sendMediaInspectionToController($video->file_path, $video->file_type, $message, $telefono, 0, $video->id);
                    $videosEnviados++;
                    Log::info('Video enviado con URL', [
                        'file_path' => $video->file_path,
                        'url' => $publicUrl,
                        'code_supplier' => $codeSupplier
                    ]);
                } else {
                    Log::error('No se pudo generar URL pública para video: ' . $video->file_path);
                }
            }

            Log::info("Job de inspección completado exitosamente", [
                'id_proveedor' => $this->idProveedor,
                'imagenes_enviadas' => $imagenesEnviadas,
                'videos_enviados' => $videosEnviados,
                'mensaje_principal_enviado' => $sendStatus
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

    /**
     * Genera una URL pública para un archivo almacenado
     * 
     * @param string $filePath Ruta del archivo (puede ser relativa o absoluta)
     * @return string|null URL pública del archivo o null si falla
     */
    private function generatePublicUrl($filePath)
    {
        try {
            // Si ya es una URL completa, devolverla tal como está
            if (filter_var($filePath, FILTER_VALIDATE_URL)) {
                return $filePath;
            }

            // Limpiar la ruta
            $ruta = ltrim($filePath, '/');

            // Si la ruta empieza con 'public/', removerlo
            if (strpos($ruta, 'public/') === 0) {
                $ruta = substr($ruta, 7);
            }

            // Construir URL manualmente
            $baseUrl = config('app.url');
            $baseUrl = rtrim($baseUrl, '/');
            $ruta = ltrim($ruta, '/');

            // Generar URL completa
            $publicUrl = $baseUrl . '/storage/' . $ruta;

            Log::info("URL pública generada", [
                'file_path' => $filePath,
                'public_url' => $publicUrl
            ]);

            return $publicUrl;
        } catch (\Exception $e) {
            Log::error("Error al generar URL pública: " . $e->getMessage(), [
                'file_path' => $filePath,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}
