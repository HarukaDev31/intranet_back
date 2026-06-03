<?php

namespace App\Traits;

use App\Jobs\WhatsApp\SendCoordinacionWhatsAppJob;
use App\Services\WhatsApp\WhatsAppCoordinacionBatchService;
use App\Support\WhatsApp\CoordinacionWhatsappPayload;
use App\Support\WhatsApp\WhatsappEnvironmentPhone;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

trait WhatsappTrait
{
    private $phoneNumberId = null;

    /** @var int|null Batch activo (rotulado u otros flujos Meta). */
    protected $whatsappCoordinacionBatchId = null;

    public function setWhatsAppCoordinacionBatchId(?int $batchId): void
    {
        $this->whatsappCoordinacionBatchId = $batchId;
    }

    public function getWhatsAppCoordinacionBatchId(): ?int
    {
        return $this->whatsappCoordinacionBatchId;
    }

    /**
     * Despacha los jobs Meta agrupados en Bus::batch (tras encolar todos los ítems).
     */
    protected function dispatchWhatsAppCoordinacionBatch(): ?string
    {
        if ($this->whatsappCoordinacionBatchId === null) {
            return null;
        }

        return app(WhatsAppCoordinacionBatchService::class)
            ->dispatchBuffered($this->whatsappCoordinacionBatchId);
    }

    /**
     * Agrupa varios envíos en un batch de Horizon (un grupo visible por operación).
     *
     * @param  array<string, mixed>  $context  id_cotizacion, cliente, carga, phone_e164, …
     * @param  callable(): void  $enqueue  Llama queueCoordinacionWhatsApp por cada paso
     */
    protected function resolveCoordinacionJobDomain(): ?string
    {
        if (property_exists($this, 'domain') && !empty($this->domain)) {
            return (string) $this->domain;
        }

        $domain = $this->getRequestDomain();

        return $domain !== null && $domain !== '' ? $domain : null;
    }

    protected function runWhatsAppCoordinacionBatch(string $tipo, array $context, callable $enqueue): ?string
    {
        if (empty($context['job_domain'])) {
            $resolved = $this->resolveCoordinacionJobDomain();
            if ($resolved !== null) {
                $context['job_domain'] = $resolved;
            }
        }

        $batch = app(WhatsAppCoordinacionBatchService::class)->create($tipo, $context);
        $previousBatchId = $this->whatsappCoordinacionBatchId;
        $this->setWhatsAppCoordinacionBatchId((int) $batch->id);

        try {
            $enqueue();
        } finally {
            $laravelBatchId = $this->dispatchWhatsAppCoordinacionBatch();
            $this->setWhatsAppCoordinacionBatchId($previousBatchId);

            return $laravelBatchId;
        }
    }
    
    /**
     * Mapeo inverso de conexión de BD a dominio del frontend
     * Múltiples dominios pueden usar la misma conexión (mysql)
     */
    private function getDomainFromDatabaseConnection($connection)
    {
        $connectionDomainMap = [
            'mysql' => 'intranetv2.probusiness.pe', // Dominio principal para producción
            'mysql_qa' => 'qaintranet.probusiness.pe',
            'mysql_local' => 'localhost',
        ];
        
        return $connectionDomainMap[$connection] ?? null;
    }

    /**
     * Obtener el dominio del frontend desde donde se hace la petición
     * Prioriza Origin/Referer, luego infiere desde la conexión de BD actual
     */
    private function getRequestDomain()
    {
        try {
            // Primero intentar obtener desde headers HTTP (Origin/Referer)
            $request = request();
            if ($request) {
                $origin = $request->headers->get('origin');
                $referer = $request->headers->get('referer');

                $sourceHost = null;
                if ($origin) {
                    $sourceHost = parse_url($origin, PHP_URL_HOST);
                }
                if (!$sourceHost && $referer) {
                    $sourceHost = parse_url($referer, PHP_URL_HOST);
                }

                if ($sourceHost) {
                    // Extraer solo el dominio (sin puerto o subdirectorios)
                    return $this->extractDomain($sourceHost);
                }
            }

            // Si no hay headers disponibles (ej: Jobs), inferir desde la conexión de BD actual
            try {
                $currentConnection = DB::getDefaultConnection();
                $domain = $this->getDomainFromDatabaseConnection($currentConnection);
                
                if ($domain) {
                    Log::info('Dominio inferido desde conexión de BD', [
                        'connection' => $currentConnection,
                        'domain' => $domain
                    ]);
                    return $domain;
                }
            } catch (\Exception $dbException) {
                Log::debug('No se pudo obtener conexión de BD: ' . $dbException->getMessage());
            }

            // Si no se pudo obtener de ninguna forma
            Log::warning('No se pudo obtener el dominio del frontend: Origin/Referer no disponibles y no se pudo inferir desde BD');
            return null;
        } catch (\Exception $e) {
            Log::warning('Error al obtener dominio del frontend: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extraer el dominio del host
     * Similar a DatabaseSelectionMiddleware
     */
    private function extractDomain($host)
    {
        // Remover el puerto si existe (ej: localhost:8000 -> localhost)
        $domain = explode(':', $host)[0];
        
        // Si tiene www, removerlo
        $domain = preg_replace('/^www\./', '', $domain);
        
        return $domain;
    }

    /**
     * Obtener el dominio del frontend desde la request actual
     * Método público para ser usado desde controladores al despachar Jobs
     * 
     * @return string|null
     */
    public static function getCurrentRequestDomain()
    {
        try {
            $request = request();
            if (!$request) {
                return null;
            }

            // Priorizar el dominio proveniente de Origin/Referer
            $origin = $request->headers->get('origin');
            $referer = $request->headers->get('referer');

            $sourceHost = null;
            if ($origin) {
                $sourceHost = parse_url($origin, PHP_URL_HOST);
            }
            if (!$sourceHost && $referer) {
                $sourceHost = parse_url($referer, PHP_URL_HOST);
            }

            if ($sourceHost) {
                // Remover el puerto si existe
                $domain = explode(':', $sourceHost)[0];
                // Si tiene www, removerlo
                $domain = preg_replace('/^www\./', '', $domain);
                return $domain;
            }

            return null;
        } catch (\Exception $e) {
            Log::warning('Error al obtener dominio desde request: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Misma lógica que _callApi: localhost / QA / FORCE_SEND_DEFAULT_NUMBER → DEFAULT_WHATSAPP_NUMBER.
     */
    protected function shouldUseDefaultWhatsAppNumber(): bool
    {
        return WhatsappEnvironmentPhone::shouldUseDefaultNumber($this->getRequestDomain());
    }

    /**
     * @param  string|null  $phoneNumberId  Formato legacy 51999999999@c.us
     */
    protected function resolvePhoneNumberForWhatsApp(?string $phoneNumberId): ?string
    {
        return WhatsappEnvironmentPhone::resolve($phoneNumberId, $this->getRequestDomain());
    }

    private function _callApi($endpoint, $data)
    {
        try {
            $url = 'https://redis.probusiness.pe/api/whatsapp' . $endpoint;

            Log::info('Domain: ' . $this->getRequestDomain());

            if (isset($data['phoneNumberId'])) {
                $data['phoneNumberId'] = $this->resolvePhoneNumberForWhatsApp($data['phoneNumberId']);
            }
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
            ]);

            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            if (isset($data['fileContent']) && strlen($data['fileContent']) > 1000000) { // > 1MB
                curl_setopt($ch, CURLOPT_BUFFERSIZE, 128000);
            }
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if ($error) {
                return [
                    'status' => false,
                    'response' => ['error' => 'Error de conexión: ' . $error]
                ];
            }

            $decodedResponse = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Respuesta no válida de API de WhatsApp', [
                    'endpoint' => $endpoint,
                    'response' => $response,
                    'jsonError' => json_last_error_msg()
                ]);
            }

            Log::info('Respuesta de API de WhatsApp', [
                'endpoint' => $endpoint,
                'httpCode' => $httpCode,
                'success' => $httpCode >= 200 && $httpCode < 300
            ]);

            return [
                'status' => $httpCode >= 200 && $httpCode < 300,
                'response' => $decodedResponse ?: ['raw_response' => $response]
            ];
        } catch (\Exception $e) {
            Log::error('Excepción en _callApi: ' . $e->getMessage(), [
                'endpoint' => $endpoint,
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'status' => false,
                'response' => ['error' => 'Excepción: ' . $e->getMessage()]
            ];
        }
    }
    /**
     * Texto del mensaje de bienvenida V2 (welcomeV2). Mismo contenido que recibe el cliente por WhatsApp.
     */
    public static function buildWelcomeRotuladoMessageText($carga): string
    {
        $carga = (string) $carga;

        return "Hola 🙋🏻‍♀, te escribe el área de coordinación de probusiness, \n"
            . "yo me encargaré de ayudarte en tu importación del *consolidado #{$carga}*.\n\n"
            . "📢 Preste atención al siguiente paso: \n"
            . "*Rotulado* 👇🏼\n"
            . "Tienes que indicarle a tu proveedor que las cajas máster 📦 cuenten con un rotulado para identificar tus paquetes y diferenciarlas de los demás cuando llegue a nuestro almacén.\n\n"
            . "☑ El documento está en idioma chino, solo debes enviarle a tu proveedor 📤\n\n"
            . "Nota: No cambiar ninguno de los datos, en caso tu proveedor tenga alguna consulta, se puede comunicarse:\n\n"
            . "🙍🏻‍♂ Álmacen China: Mr. Younus \n"
            . "📞 Wechat: 13185122926 ";
    }

    public function sendWelcome($carga, $phoneNumberId = null, $sleep = 0): array
    {
        $phoneNumberId = $this->resolvePhoneNumberForWhatsApp($phoneNumberId ? $phoneNumberId : $this->phoneNumberId);

        if ($this->shouldRouteCoordinacionToMeta('consolidado') && $phoneNumberId) {
            $welcomeText = self::buildWelcomeRotuladoMessageText($carga);

            return $this->queueCoordinacionWhatsApp(
                CoordinacionWhatsappPayload::welcomeRotulado((string) $phoneNumberId, (string) $carga, $welcomeText, $sleep)
            );
        }

        return $this->_callApi('/welcomeV2', [
            'carga' => $carga,
            'phoneNumberId' => $phoneNumberId,
            'sleep' => $sleep,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function sendDataItem($message, $filePath, $phoneNumberId = null, $sleep = 0, $fileName = null, $meta = null): array
    {
        try {
            $phoneNumberId = $this->resolvePhoneNumberForWhatsApp($phoneNumberId ? $phoneNumberId : $this->phoneNumberId);

            if ($this->shouldRouteCoordinacionToMeta('consolidado')) {
                return $this->sendMedia(
                    $filePath,
                    'application/pdf',
                    $message,
                    $phoneNumberId,
                    $sleep,
                    'consolidado',
                    $fileName ?? basename($filePath),
                    $meta
                );
            }

            if (!file_exists($filePath)) {
                Log::error('Error al enviar data item: El archivo no existe: ' . $filePath);
                return ['status' => false, 'response' => ['error' => 'Archivo no encontrado']];
            }
            
            // Verificar que el archivo es legible
            if (!is_readable($filePath)) {
                Log::error('Error al enviar data item: El archivo no es legible: ' . $filePath);
                return ['status' => false, 'response' => ['error' => 'Archivo no legible']];
            }
            
            $fileContent = file_get_contents($filePath);
            if ($fileContent === false) {
                Log::error('Error al enviar data item: No se pudo leer el archivo: ' . $filePath);
                return ['status' => false, 'response' => ['error' => 'No se pudo leer el archivo']];
            }
            
            $fileContent = base64_encode($fileContent);

            // Usar el nombre personalizado si se proporciona, de lo contrario usar basename
            $finalFileName = $fileName ?? basename($filePath);

            return $this->_callApi('/data-item', [
                'message' => $message,
                'fileContent' => $fileContent,
                'fileName' => $finalFileName,
                'phoneNumberId' => $phoneNumberId,
                'sleep' => $sleep
            ]);
        } catch (\Exception $e) {
            Log::error('Error al enviar data item: ' . $e->getMessage(), [
                'filePath' => $filePath,
                'trace' => $e->getTraceAsString()
            ]);
            return ['status' => false, 'response' => ['error' => $e->getMessage()]];
        }
    }

    /**
     * Envía un mensaje de texto por WhatsApp.
     *
     * @param string      $message       Texto del mensaje a enviar.
     * @param string|null $phoneNumberId Número de destino en formato internacional (ej: 51912345678@c.us).
     *                                   Si es null, usa $this->phoneNumberId.
     * @param int         $sleep         Segundos de espera antes de enviar (útil para Jobs encadenados).
     * @param string      $fromNumber    Instancia de WhatsApp desde la que se envía.
     *
     *   Instancias disponibles (fromNumber):
     *   ─────────────────────────────────────────────────────────────────
     *   'consolidado'    → Número principal del servicio de consolidado.
     *                      Usado para mensajes de cotizaciones, pagos, entrega.
     *                      (valor por defecto)
     *   'administracion' → Número de la oficina de administración.
     *                      Usado para factura comercial, guía de remisión,
     *                      viáticos y comunicaciones administrativas.
     *   'ventas'         → Número del equipo de ventas.
     *                      Usado para cotizaciones proveedor / propuestas comerciales.
     * '
     *   ─────────────────────────────────────────────────────────────────
     *   Nota: para rutas dedicadas sin fromNumber (inspecciones, bienvenida,
     *   cursos) usa sendMediaInspection(), sendWelcome() o sendMessageCurso().
     *
     * @param  array<string, mixed>|null  $meta  Payload Meta (template, body_parameters, chat_preview). Ver CoordinacionWhatsappPayload.
     * @return array ['status' => bool, 'response' => array]
     */
    public function sendMessage($message, $phoneNumberId = null, $sleep = 0, $fromNumber = 'consolidado', $meta = null): array
    {
        $phoneNumberId = $this->resolvePhoneNumberForWhatsApp($phoneNumberId ? $phoneNumberId : $this->phoneNumberId);

        if ($this->shouldRouteCoordinacionToMeta($fromNumber)) {
            if (is_array($meta) && !empty($meta['template'])) {
                $meta['chat_preview'] = $meta['chat_preview'] ?? $meta['bitrix_message'] ?? $message;
                $meta['phone'] = $meta['phone'] ?? $phoneNumberId;
                $meta['sleep'] = $meta['sleep'] ?? $sleep;

                return $this->queueCoordinacionWhatsApp($meta);
            }
            if (config('meta_whatsapp.legacy_fallback', true)) {
                return $this->queueCoordinacionWhatsApp([
                    'type' => 'legacy_message',
                    'message' => $message,
                    'phone' => $phoneNumberId,
                    'sleep' => $sleep,
                ]);
            }
        }

        return $this->_callApi('/messageV2', [
            'message' => $message,
            'phoneNumberId' => $phoneNumberId,
            'sleep' => $sleep,
            'fromNumber' => $fromNumber
        ]);
    }
    public function sendMessageVentas($message, $phoneNumberId = null, $sleep = 0): array
    {
        $phoneNumberId = $phoneNumberId ? $phoneNumberId : $this->phoneNumberId;

        return $this->_callApi('/message-ventas', [
            'message' => $message,
            'phoneNumberId' => $phoneNumberId,
            'sleep' => $sleep
        ]);
    }
    public function sendMessageCurso($message, $phoneNumberId = null, $sleep = 0): array
    {
        $phoneNumberId = $phoneNumberId ? $phoneNumberId : $this->phoneNumberId;

        return $this->_callApi('/message-curso', [
            'message' => $message,
            'phoneNumberId' => $phoneNumberId,
            'sleep' => $sleep
        ]);
    }
    /**
     * Envía un archivo (media) por WhatsApp.
     *
     * @param string      $filePath      Ruta absoluta del archivo en el servidor.
     * @param string|null $mimeType      MIME type del archivo (ej: 'application/pdf', 'image/jpg').
     * @param string|null $message       Mensaje de caption adjunto al archivo.
     * @param string|null $phoneNumberId Número de destino en formato internacional (ej: 51912345678@c.us).
     *                                   Si es null, usa $this->phoneNumberId.
     * @param int         $sleep         Segundos de espera antes de enviar.
     * @param string      $fromNumber    Instancia de WhatsApp desde la que se envía.
     *
     *   Instancias disponibles (fromNumber):
     *   ─────────────────────────────────────────────────────────────────
     *   'consolidado'    → Número principal del servicio de consolidado.
     *                      Usado para pagos (números de cuenta), rotulado,
     *                      cargo de entrega firmado.
     *                      (valor por defecto)
     *   'administracion' → Número de la oficina de administración.
     *                      Usado para envío de factura comercial, guía de
     *                      remisión, comprobantes contables y viáticos.
     *   'ventas'         → Número del equipo de ventas.
     *                      Usado para cotizaciones proveedor en PDF.
     *   ─────────────────────────────────────────────────────────────────
     *
     * @param string|null $fileName      Nombre del archivo que verá el destinatario.
     *                                   Si es null, usa basename($filePath).
     * @param  array<string, mixed>|null  $meta  Plantilla Meta con header document/image (template, body_parameters, header).
     * @return array|false Respuesta de la API o false en caso de error.
     */
    public function sendMedia($filePath, $mimeType = null, $message = null, $phoneNumberId = null, $sleep = 0, $fromNumber = 'consolidado', $fileName = null, $meta = null)
    {
        try {
            $phoneNumberId = $this->resolvePhoneNumberForWhatsApp($phoneNumberId ? $phoneNumberId : $this->phoneNumberId);

            if ($this->shouldRouteCoordinacionToMeta($fromNumber)) {
                if (is_array($meta) && !empty($meta['template'])) {
                    $meta['phone'] = $meta['phone'] ?? $phoneNumberId;
                    $meta['sleep'] = $meta['sleep'] ?? $sleep;
                    $meta['chat_preview'] = $meta['chat_preview'] ?? $meta['bitrix_message'] ?? (string) ($message ?? '');
                    if (empty($meta['header'])) {
                        $meta['header'] = [
                            'type' => is_string($mimeType) && strpos($mimeType, 'image/') === 0 ? 'image' : 'document',
                            'path' => $filePath,
                            'filename' => $fileName ?? basename($filePath),
                            'mimeType' => $mimeType,
                        ];
                    }

                    return $this->queueCoordinacionWhatsApp($meta);
                }
                if (config('meta_whatsapp.legacy_fallback', true)) {
                    return $this->queueCoordinacionWhatsApp([
                        'type' => 'legacy_media',
                        'path' => $filePath,
                        'mimeType' => $mimeType,
                        'caption' => $message,
                        'fileName' => $fileName ?? basename($filePath),
                        'phone' => $phoneNumberId,
                        'sleep' => $sleep,
                    ]);
                }
            }

            // Verificar que el archivo existe
            if (!file_exists($filePath)) {
                Log::error('Error al enviar media: El archivo no existe: ' . $filePath);
                return false;
            }
            
            // Verificar que el archivo es legible
            if (!is_readable($filePath)) {
                Log::error('Error al enviar media: El archivo no es legible: ' . $filePath);
                return false;
            }

            $fileContent = file_get_contents($filePath);
            if ($fileContent === false) {
                Log::error('Error al enviar media: No se pudo leer el archivo: ' . $filePath);
                return false;
            }
            
            $fileContent = base64_encode($fileContent);

            return $this->_callApi('/mediaV2', [
                'fileContent' => $fileContent,
                'fileName' => $fileName ?? basename($filePath),
                'mimeType' => $mimeType,
                'message' => $message,
                'phoneNumberId' => $phoneNumberId,
                'sleep' => $sleep,
                'fromNumber' => $fromNumber
            ]);
        } catch (\Exception $e) {
            Log::error('Error al enviar media: ' . $e->getMessage(), [
                'filePath' => $filePath,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    public function sendMediaInspection($filePath, $mimeType = null, $message = null, $phoneNumberId = null, $sleep = 0, $inspection_id = null, $fileName = null, $meta = null)
    {
        try {
            $phoneNumberId = $this->resolvePhoneNumberForWhatsApp($phoneNumberId ? $phoneNumberId : $this->phoneNumberId);

            if ($this->shouldRouteCoordinacionToMeta('consolidado') && is_array($meta) && !empty($meta['template'])) {
                $meta['phone'] = $meta['phone'] ?? $phoneNumberId;
                $meta['sleep'] = $meta['sleep'] ?? $sleep;
                $meta['chat_preview'] = $meta['chat_preview'] ?? $meta['bitrix_message'] ?? (string) ($message ?? '');
                if (empty($meta['header']) && $filePath !== '') {
                    $meta['header'] = [
                        'type' => is_string($mimeType) && strpos($mimeType, 'video/') === 0 ? 'video' : (
                            is_string($mimeType) && strpos($mimeType, 'image/') === 0 ? 'image' : 'document'
                        ),
                        'path' => $filePath,
                        'filename' => $fileName ?? basename($filePath),
                        'mimeType' => $mimeType,
                    ];
                }

                return $this->queueCoordinacionWhatsApp($meta);
            }

            if (!file_exists($filePath)) {
                Log::error('Error al enviar media de inspección: El archivo no existe: ' . $filePath);
                return false;
            }
            
            if (!is_readable($filePath)) {
                Log::error('Error al enviar media de inspección: El archivo no es legible: ' . $filePath);
                return false;
            }
            
            $fileContent = file_get_contents($filePath);
            if ($fileContent === false) {
                Log::error('Error al enviar media de inspección: No se pudo leer el archivo: ' . $filePath);
                return false;
            }
            
            $fileContent = base64_encode($fileContent);
            
            // Validar que el contenido no esté vacío
            if (empty($fileContent)) {
                Log::error('Error al enviar media de inspección: El archivo está vacío: ' . $filePath);
                return false;
            }
            
            Log::info('Enviando media de inspección', [
                'filePath' => $filePath,
                'fileName' => basename($filePath),
                'fileSize' => filesize($filePath),
                'mimeType' => $mimeType,
                'inspectionId' => $inspection_id
            ]);
            
            return $this->_callApi('/media-inspectionV2', [
                'fileContent' => $fileContent,
                'fileName' => $fileName ?? basename($filePath),
                'mimeType' => $mimeType,
                'message' => $message,
                'phoneNumberId' => $phoneNumberId,
                'sleep' => $sleep,
                'inspectionId' => $inspection_id
            ]);
        } catch (\Exception $e) {
            Log::error('Error al enviar media de inspección: ' . $e->getMessage(), [
                'filePath' => $filePath,
                'inspectionId' => $inspection_id,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Envía datos de media de inspección directamente al controlador local
     * El controlador procesará y encolará el job SendMediaInspectionMessageJobV2
     * Envía solo la URL pública del archivo (no base64)
     * 
     * @param string $filePath Ruta del archivo a enviar
     * @param string|null $mimeType Tipo MIME del archivo
     * @param string|null $message Mensaje opcional
     * @param string|null $phoneNumberId Número de teléfono
     * @param int $sleep Tiempo de espera
     * @param int|null $inspection_id ID de la inspección
     * @param string|null $fileName Nombre del archivo
     * @return array|false Respuesta del controlador o false en caso de error
     */
    public function sendMediaInspectionToController($filePath, $mimeType = null, $message = null, $phoneNumberId = null, $sleep = 0, $inspection_id = null, $fileName = null, $meta = null)
    {
        try {
            $phoneNumberId = $this->resolvePhoneNumberForWhatsApp($phoneNumberId ? $phoneNumberId : $this->phoneNumberId);

            if ($this->shouldRouteCoordinacionToMeta('consolidado') && is_array($meta) && !empty($meta['template'])) {
                $meta['phone'] = $meta['phone'] ?? $phoneNumberId;
                $meta['sleep'] = $meta['sleep'] ?? $sleep;
                $meta['chat_preview'] = $meta['chat_preview'] ?? $meta['bitrix_message'] ?? (string) ($message ?? '');
                if (empty($meta['header'])) {
                    $mediaPath = is_string($filePath) && $filePath !== '' ? $filePath : '';
                    if ($mediaPath !== '') {
                        $meta['header'] = [
                            'type' => is_string($mimeType) && strpos($mimeType, 'video/') === 0 ? 'video' : (
                                is_string($mimeType) && strpos($mimeType, 'image/') === 0 ? 'image' : 'document'
                            ),
                            'path' => $mediaPath,
                            'filename' => $fileName ?? basename($mediaPath),
                            'mimeType' => $mimeType,
                        ];
                    }
                }

                return $this->queueCoordinacionWhatsApp($meta);
            }

            // Validar que inspection_id esté presente
            if ($inspection_id === null) {
                Log::error('Error al enviar media de inspección al controlador: inspection_id es requerido');
                return false;
            }

            // Generar URL pública del archivo
            $publicUrl = $this->generatePublicUrlFromPath($filePath);
            
            if (!$publicUrl) {
                Log::error('Error al generar URL pública del archivo: ' . $filePath);
                return false;
            }

            Log::info('Enviando media de inspección al controlador local (URL)', [
                'filePath' => $filePath,
                'publicUrl' => $publicUrl,
                'fileName' => $fileName ?? basename($filePath),
                'mimeType' => $mimeType,
                'inspectionId' => $inspection_id
            ]);

            // Usar _callApi para enviar la URL al controlador
            return $this->_callApi('/media-inspectionV2', [
                'fileContent' => $publicUrl, // Enviar URL en lugar de base64
                'fileName' => $fileName ?? basename($filePath),
                'phoneNumberId' => $phoneNumberId,
                'mimeType' => $mimeType,
                'message' => $message,
                'inspectionId' => $inspection_id
            ]);
        } catch (\Exception $e) {
            Log::error('Excepción al enviar media de inspección al controlador: ' . $e->getMessage(), [
                'filePath' => $filePath,
                'inspectionId' => $inspection_id,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Genera una URL pública para un archivo desde su ruta
     * 
     * @param string $filePath Ruta del archivo (puede ser absoluta o relativa)
     * @return string|null URL pública del archivo o null si falla
     */
    private function generatePublicUrlFromPath($filePath)
    {
        try {
            return app(\App\Contracts\ObjectStorageConnectorInterface::class)->url($filePath);
        } catch (\Exception $e) {
            Log::error('Error al generar URL pública desde ruta: ' . $e->getMessage(), [
                'file_path' => $filePath,
            ]);

            return null;
        }
    }

    /**
     * ¿Enrutar sendMessage/sendMedia de coordinación al job Meta + Bitrix?
     */
    protected function shouldRouteCoordinacionToMeta(string $fromNumber): bool
    {
        if ($fromNumber !== 'consolidado') {
            return false;
        }

        return (bool) config('meta_whatsapp.coordinacion_enabled', false);
    }

    /**
     * Encola envío coordinación (Meta + wa_inbox). Usar desde Jobs o dentro de runWhatsAppCoordinacionBatch.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: bool, queued: bool}
     */
    /**
     * @param  array<string, mixed>  $payload
     */
    public function queueCoordinacionWhatsApp(array $payload, ?string $stepKey = null, ?string $label = null): array
    {
        if (isset($payload['_batch_step'], $payload['_batch_label'])) {
            $stepKey = (string) $payload['_batch_step'];
            $label = (string) $payload['_batch_label'];
            unset($payload['_batch_step']);
        }

        if (!empty($payload['phone'])) {
            $payload['phone'] = $this->resolvePhoneNumberForWhatsApp((string) $payload['phone']);
        }

        if (empty($payload['_domain'])) {
            if (property_exists($this, 'domain') && !empty($this->domain)) {
                $payload['_domain'] = (string) $this->domain;
            } else {
                $domain = $this->getRequestDomain();
                if ($domain !== null && $domain !== '') {
                    $payload['_domain'] = $domain;
                }
            }
        }

        if ($this->whatsappCoordinacionBatchId !== null) {
            $stepKey = $stepKey ?? (string) ($payload['template'] ?? $payload['type'] ?? 'mensaje');
            $label = $label ?? (string) ($payload['template'] ?? 'Mensaje coordinación');

            app(WhatsAppCoordinacionBatchService::class)->enqueueItem(
                $this->whatsappCoordinacionBatchId,
                $stepKey,
                $label,
                $payload
            );

            return [
                'status' => true,
                'queued' => true,
                'batch_id' => $this->whatsappCoordinacionBatchId,
            ];
        }

        SendCoordinacionWhatsAppJob::dispatch($payload);

        return [
            'status' => true,
            'queued' => true,
        ];
    }

    /**
     * Procesamiento síncrono (pruebas o casos puntuales).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function processCoordinacionWhatsApp(array $payload): array
    {
        return app(\App\Services\WhatsappInbox\WhatsappInboxCoordinacionOutboundService::class)->process($payload);
    }
}
