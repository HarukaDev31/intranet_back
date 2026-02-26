<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

trait WhatsappTrait
{
    private $phoneNumberId = null;

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

    private function _callApi($endpoint, $data)
    {
        try {
            $url = 'https://redis.probusiness.pe/api/whatsapp' . $endpoint;
            
            // Obtener dominio desde donde se hace la petición
            $domain = $this->getRequestDomain();
            $defaultWhatsapNumber = env('DEFAULT_WHATSAPP_NUMBER', '51912705923@c.us');
            Log::info('Domain: ' . $domain);
            // Validar dominio similar a DatabaseSelectionMiddleware
            // Si es localhost, desarrollo o QA, usar número por defecto
            $domainsForDefaultNumber = ['localhost', '127.0.0.1', 'qaintranet.probusiness.pe'];
            $shouldUseDefaultNumber = false;
            
            if ($domain) {
                foreach ($domainsForDefaultNumber as $allowedDomain) {
                    if (strpos($domain, $allowedDomain) !== false || $domain === $allowedDomain) {
                        $shouldUseDefaultNumber = true;
                        break;
                    }
                }
            }
            
            if ($shouldUseDefaultNumber) {
                $data['phoneNumberId'] = $defaultWhatsapNumber;
                Log::info('Dominio detectado para usar número por defecto', [
                    'domain' => $domain,
                    'phoneNumberId' => $defaultWhatsapNumber
                ]);
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
    public function sendWelcome($carga, $phoneNumberId = null, $sleep = 0): array
    {
        $phoneNumberId = $phoneNumberId ? $phoneNumberId : $this->phoneNumberId;
        return $this->_callApi('/welcomeV2', [
            'carga' => $carga,
            'phoneNumberId' => $phoneNumberId,
            'sleep' => $sleep
        ]);
    }

    public function sendDataItem($message, $filePath, $phoneNumberId = null, $sleep = 0, $fileName = null): array
    {
        try {
            $phoneNumberId = $phoneNumberId ? $phoneNumberId : $this->phoneNumberId;
            
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
     *   ─────────────────────────────────────────────────────────────────
     *   Nota: para rutas dedicadas sin fromNumber (inspecciones, bienvenida,
     *   cursos) usa sendMediaInspection(), sendWelcome() o sendMessageCurso().
     *
     * @return array ['status' => bool, 'response' => array]
     */
    public function sendMessage($message, $phoneNumberId = null, $sleep = 0, $fromNumber = 'consolidado'): array
    {
        $phoneNumberId = $phoneNumberId ? $phoneNumberId : $this->phoneNumberId;

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
     * @return array|false Respuesta de la API o false en caso de error.
     */
    public function sendMedia($filePath, $mimeType = null, $message = null, $phoneNumberId = null, $sleep = 0, $fromNumber = 'consolidado', $fileName = null)
    {
        try {
            $phoneNumberId = $phoneNumberId ? $phoneNumberId : $this->phoneNumberId;

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
    public function sendMediaInspection($filePath, $mimeType = null, $message = null, $phoneNumberId = null, $sleep = 0, $inspection_id = null,$fileName=null)
    {
        try {
            $phoneNumberId = $phoneNumberId ? $phoneNumberId : $this->phoneNumberId;
            
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
    public function sendMediaInspectionToController($filePath, $mimeType = null, $message = null, $phoneNumberId = null, $sleep = 0, $inspection_id = null, $fileName = null)
    {
        try {
            $phoneNumberId = $phoneNumberId ? $phoneNumberId : $this->phoneNumberId;
            
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
            // Si ya es una URL completa, devolverla tal como está
            if (filter_var($filePath, FILTER_VALIDATE_URL)) {
                return $filePath;
            }

            // Limpiar la ruta de barras iniciales para evitar doble slash
            $ruta = ltrim($filePath, '/');

            // Si es una ruta absoluta del sistema, convertirla a ruta relativa
            if (strpos($filePath, storage_path('app/public')) === 0) {
                // Es una ruta absoluta en storage/app/public
                $ruta = str_replace(storage_path('app/public'), '', $filePath);
                $ruta = ltrim($ruta, '/\\');
            } elseif (strpos($filePath, public_path('storage')) === 0) {
                // Es una ruta absoluta en public/storage
                $ruta = str_replace(public_path('storage'), '', $filePath);
                $ruta = ltrim($ruta, '/\\');
            }

            // Limpiar la ruta
            $ruta = str_replace('\\', '/', $ruta);
            $ruta = ltrim($ruta, '/');

            // Corregir rutas con doble storage
            if (strpos($ruta, 'storage//storage/') !== false) {
                $ruta = str_replace('storage//storage/', 'storage/', $ruta);
            }

            // Si la ruta ya contiene 'storage/', no agregar otro 'storage/'
            if (strpos($ruta, 'storage/') === 0) {
                $baseUrl = config('app.url');
                $publicUrl = rtrim($baseUrl, '/') . '/' . $ruta;
            } elseif (strpos($ruta, 'public/') === 0) {
                // Si la ruta empieza con 'public/', remover 'public/' y agregar 'storage/'
                $ruta = substr($ruta, 7); // Remover 'public/'
                $baseUrl = config('app.url');
                $publicUrl = rtrim($baseUrl, '/') . '/storage/' . $ruta;
            } else {
                // Construir URL manualmente (igual que generateImageUrl en CotizacionProveedorController)
                $baseUrl = config('app.url');
                $storagePath = 'storage/';

                // Asegurar que no haya doble slash
                $baseUrl = rtrim($baseUrl, '/');
                $storagePath = ltrim($storagePath, '/');
                $ruta = ltrim($ruta, '/');

                $publicUrl = $baseUrl . '/' . $storagePath . $ruta;
            }

            Log::info("URL pública generada desde ruta", [
                'file_path' => $filePath,
                'relative_path' => $ruta,
                'public_url' => $publicUrl
            ]);

            return $publicUrl;
        } catch (\Exception $e) {
            Log::error("Error al generar URL pública desde ruta: " . $e->getMessage(), [
                'file_path' => $filePath,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}
