<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait WhatsappTrait
{
    private $phoneNumberId = null;

    private function _callApi($endpoint, $data)
    {
        try {
            $url = 'https://redis.probusiness.pe/api/whatsapp' . $endpoint;
            $envUrl = env('APP_URL');
            $defaultWhatsapNumber=env('DEFAULT_WHATSAPP_NUMBER','912705923@c.us');
            if (strpos($envUrl, 'localhost') !== false) {
                $data['phoneNumberId'] = '51931629529@c.us';
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
                Log::error('Error de cURL en API de WhatsApp: ' . $error, [
                    'endpoint' => $endpoint,
                    'url' => $url
                ]);
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
        return $this->_callApi('/welcome', [
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

    public function sendMessage($message, $phoneNumberId = null, $sleep = 0): array
    {
        $phoneNumberId = $phoneNumberId ? $phoneNumberId : $this->phoneNumberId;

        return $this->_callApi('/message', [
            'message' => $message,
            'phoneNumberId' => $phoneNumberId,
            'sleep' => $sleep
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
    public function sendMedia($filePath, $mimeType = null, $message = null, $phoneNumberId = null, $sleep = 0)
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

            return $this->_callApi('/media', [
                'fileContent' => $fileContent,
                'fileName' => basename($filePath),
                'mimeType' => $mimeType,
                'message' => $message,
                'phoneNumberId' => $phoneNumberId,
                'sleep' => $sleep
            ]);
        } catch (\Exception $e) {
            Log::error('Error al enviar media: ' . $e->getMessage(), [
                'filePath' => $filePath,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    public function sendMediaInspection($filePath, $mimeType = null, $message = null, $phoneNumberId = null, $sleep = 0, $inspection_id = null)
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
            
            return $this->_callApi('/media-inspection', [
                'fileContent' => $fileContent,
                'fileName' => basename($filePath),
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
}
