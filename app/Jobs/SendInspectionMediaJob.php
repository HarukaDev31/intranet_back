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


            // Procesar y enviar imágenes
            $imagenesEnviadas = 0;
            foreach ($imagesUrls as $image) {
                $filePath = $this->resolveMediaPath($image->file_path);

                if ($filePath) {
                    $this->sendMediaInspection($filePath, $image->file_type, '', $telefono, 0, $image->id);
                    $imagenesEnviadas++;

                    // Si es archivo temporal, eliminarlo después del envío
                    if (strpos($filePath, sys_get_temp_dir()) !== false) {
                        unlink($filePath);
                    }
                } else {
                    Log::error('No se pudo resolver la ruta del archivo imagen: ' . $image->file_path);
                }
            }

            // Procesar y enviar videos
            $videosEnviados = 0;
            foreach ($videosUrls as $video) {
                $filePath = $this->resolveMediaPath($video->file_path);

                if ($filePath) {
                    $this->sendMediaInspection($filePath, $video->file_type, '', $telefono, 0, $video->id);
                    $videosEnviados++;

                    // Si es archivo temporal, eliminarlo después del envío
                    if (strpos($filePath, sys_get_temp_dir()) !== false) {
                        unlink($filePath);
                    }
                } else {
                    Log::error('No se pudo resolver la ruta del archivo video: ' . $video->file_path);
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
     * Resuelve la ruta de un archivo, manejando tanto rutas locales como URLs externas
     * 
     * @param string $filePath Ruta del archivo (puede ser local o URL)
     * @return string|false Ruta del archivo accesible o false si falla
     */
    private function resolveMediaPath($filePath)
    {
        try {
            Log::info("Resolviendo ruta de archivo: " . $filePath);

            // Verificar si es una URL externa
            if (filter_var($filePath, FILTER_VALIDATE_URL)) {
                Log::info("URL externa detectada, descargando: " . $filePath);
                return $this->downloadExternalMedia($filePath);
            }

            // Si no es URL, intentar como ruta local
            $possiblePaths = [
                // Ruta directa si ya es absoluta
                $filePath,
                // Ruta en storage/app/public
                storage_path('app/public/' . $filePath),
                // Ruta en public
                public_path($filePath),
                // Ruta relativa desde storage
                storage_path($filePath),
                // Limpiar posibles barras dobles y probar
                storage_path('app/public/' . ltrim($filePath, '/')),
                public_path(ltrim($filePath, '/'))
            ];

            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    Log::info("Archivo encontrado en: " . $path);
                    return $path;
                }
            }

            Log::error("Archivo no encontrado en ninguna ruta", [
                'file_path' => $filePath,
                'attempted_paths' => $possiblePaths
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error("Error al resolver ruta de archivo: " . $e->getMessage(), [
                'file_path' => $filePath,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Descarga un archivo desde una URL externa y lo guarda temporalmente
     * 
     * @param string $url URL del archivo a descargar
     * @return string|false Ruta del archivo temporal o false si falla
     */
    private function downloadExternalMedia($url)
    {
        try {
            Log::info("Descargando archivo externo: " . $url);

            // Verificar si cURL está disponible
            if (!function_exists('curl_init')) {
                Log::error("cURL no está disponible en el servidor");
                return false;
            }

            // Inicializar cURL
            $ch = curl_init();

            if (!$ch) {
                Log::error("No se pudo inicializar cURL");
                return false;
            }

            // Configurar opciones de cURL
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                CURLOPT_HTTPHEADER => [
                    'Accept: image/*,video/*,*/*',
                    'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
                ],
            ]);

            // Ejecutar la petición
            $fileContent = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

            curl_close($ch);

            // Verificar errores
            if ($fileContent === false || !empty($error)) {
                Log::error("Error cURL al descargar archivo: " . $error, ['url' => $url]);
                return false;
            }

            if ($httpCode !== 200) {
                Log::error("Error HTTP al descargar archivo. Código: " . $httpCode, [
                    'url' => $url,
                    'content_type' => $contentType
                ]);
                return false;
            }

            if (empty($fileContent)) {
                Log::error("Archivo descargado está vacío", ['url' => $url]);
                return false;
            }

            // Determinar extensión del archivo
            $extension = $this->getFileExtensionFromUrl($url, $contentType);

            // Crear archivo temporal
            $tempFile = tempnam(sys_get_temp_dir(), 'media_') . '.' . $extension;

            if (file_put_contents($tempFile, $fileContent) === false) {
                Log::error("No se pudo crear el archivo temporal");
                return false;
            }

            Log::info("Archivo descargado exitosamente", [
                'url' => $url,
                'temp_file' => $tempFile,
                'size' => strlen($fileContent),
                'content_type' => $contentType
            ]);

            return $tempFile;
        } catch (\Exception $e) {
            Log::error("Excepción al descargar archivo externo: " . $e->getMessage(), [
                'url' => $url,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Obtiene la extensión de archivo basada en la URL y content-type
     * 
     * @param string $url URL del archivo
     * @param string $contentType Content-Type del archivo
     * @return string Extensión del archivo
     */
    private function getFileExtensionFromUrl($url, $contentType = null)
    {
        // Intentar obtener extensión de la URL
        $pathInfo = pathinfo(parse_url($url, PHP_URL_PATH));
        if (!empty($pathInfo['extension'])) {
            return strtolower($pathInfo['extension']);
        }

        // Si no hay extensión en la URL, usar content-type
        if ($contentType) {
            $mimeToExtension = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                'video/mp4' => 'mp4',
                'video/avi' => 'avi',
                'video/mov' => 'mov',
                'video/wmv' => 'wmv',
                'application/pdf' => 'pdf'
            ];

            $mainType = strtok($contentType, ';'); // Remover parámetros como charset
            if (isset($mimeToExtension[$mainType])) {
                return $mimeToExtension[$mainType];
            }
        }

        // Por defecto, usar extensión genérica
        return 'tmp';
    }
}
