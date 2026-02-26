<?php

namespace App\Services\CargaConsolidada;

use Illuminate\Support\Facades\Log;

/**
 * Servicio para extracción de datos de documentos (PDF/Word) usando Gemini.
 *
 * Modelo usado: gemini-1.5-flash (soporta visión/documentos; estable y disponible).
 * Puedes cambiar a gemini-2.5-flash en GEMINI_MODEL si tu cuenta tiene acceso.
 *
 * Configuración:
 *   GEMINI_API_KEY=<tu clave en Google AI Studio>
 *   GEMINI_MODEL=gemini-1.5-flash (opcional; por defecto gemini-1.5-flash)
 */
class GeminiService
{
    const GEMINI_MODEL_DEFAULT = 'gemini-1.5-flash';

    protected static function getModel(): string
    {
        return env('GEMINI_MODEL', self::GEMINI_MODEL_DEFAULT);
    }

    protected static function getApiUrl(): string
    {
        return 'https://generativelanguage.googleapis.com/v1beta/models/' . self::getModel() . ':generateContent';
    }

    /**
     * Extrae datos de un comprobante (factura/boleta) peruano.
     *
     * Campos extraídos:
     *   - tipo_comprobante: 'Factura' | 'Boleta' | null
     *   - valor_comprobante: float|null — importe TOTAL del comprobante
     *   - tiene_detraccion: bool — true si el documento tiene detracción
     *   - monto_detraccion_dolares: float|null — monto de detracción en la moneda del comprobante
     *   - monto_detraccion_soles: float|null — monto de detracción en soles (campo "Importe de la detracción (SOLES)")
     *
     * @param string $filePath  Ruta absoluta del archivo
     * @param string $mimeType  MIME type: 'application/pdf' | 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
     * @return array
     */
    public function extractFromComprobante($filePath, $mimeType)
    {
        $prompt = 'Analiza este documento que es una factura electrónica o boleta de venta peruana. ' .
            'Extrae los datos indicados y devuelve ÚNICAMENTE un JSON válido con esta estructura exacta: ' .
            '{"tipo_comprobante": "Factura" | "Boleta" | null, ' .
            '"valor_comprobante": numero | null, ' .
            '"tiene_detraccion": true | false, ' .
            '"monto_detraccion_dolares": numero | null, ' .
            '"monto_detraccion_soles": numero | null}. ' .
            'Reglas: ' .
            '- tipo_comprobante: "Factura" si es FACTURA ELECTRÓNICA, "Boleta" si es BOLETA DE VENTA, null si no aplica. ' .
            '- valor_comprobante: el importe TOTAL del comprobante (el campo TOTAL que incluye IGV). Solo el número, sin símbolo de moneda. null si no se encuentra. ' .
            '- tiene_detraccion: true si el documento menciona detracción o "Sistema de Pago de Obligaciones Tributarias", false en caso contrario. ' .
            '- monto_detraccion_dolares: si tiene_detraccion es true, el monto de la detracción en la moneda del comprobante (usualmente USD). Solo el número. null si no hay detracción. ' .
            '- monto_detraccion_soles: si tiene_detraccion es true, el monto de la detracción en soles (campo "Importe de la detracción (SOLES)" o similar). Solo el número. null si no hay detracción. ' .
            'Responde ÚNICAMENTE con el objeto JSON. No escribas frases como "Here is the JSON" ni ningún texto antes o después del JSON.';

        $result = $this->callGemini($filePath, $mimeType, $prompt, 512);

        if (!$result['success']) {
            return array_merge($this->errorResultComprobante(), ['error' => $result['error']]);
        }

        $extracted = $result['data'];

        Log::info('GeminiService extractFromComprobante: datos extraídos', [
            'file'      => basename($filePath),
            'extracted' => $extracted,
        ]);

        return [
            'success'                  => true,
            'error'                    => null,
            'tipo_comprobante'         => isset($extracted['tipo_comprobante']) ? $extracted['tipo_comprobante'] : null,
            'valor_comprobante'        => isset($extracted['valor_comprobante']) ? (float)$extracted['valor_comprobante'] : null,
            'tiene_detraccion'         => !empty($extracted['tiene_detraccion']),
            'monto_detraccion_dolares' => isset($extracted['monto_detraccion_dolares']) ? (float)$extracted['monto_detraccion_dolares'] : null,
            'monto_detraccion_soles'   => isset($extracted['monto_detraccion_soles']) ? (float)$extracted['monto_detraccion_soles'] : null,
        ];
    }

    /**
     * Extrae el monto de depósito de una constancia de pago de detracción (SPOT/Banco de la Nación).
     *
     * Campos extraídos:
     *   - monto_constancia_soles: float|null — "Monto depósito" en soles
     *
     * @param string $filePath
     * @param string $mimeType
     * @return array
     */
    public function extractFromConstancia($filePath, $mimeType)
    {
        $prompt = 'Analiza este documento que es una constancia de depósito del Sistema de Pago de Obligaciones Tributarias (SPOT/Detracción) del Banco de la Nación de Perú. ' .
            'Extrae ÚNICAMENTE el monto del depósito y devuelve un JSON con esta estructura exacta: ' .
            '{"monto_constancia_soles": numero | null}. ' .
            'Reglas: ' .
            '- monto_constancia_soles: el valor del campo "Monto depósito" o "Monto de depósito" en soles. Solo el número, sin el prefijo "S/". null si no se encuentra. ' .
            'Responde ÚNICAMENTE con el objeto JSON. No escribas ninguna frase antes o después del JSON.';

        $result = $this->callGemini($filePath, $mimeType, $prompt, 256);

        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error'], 'monto_constancia_soles' => null];
        }

        $extracted = $result['data'];

        Log::info('GeminiService extractFromConstancia: datos extraídos', [
            'file'      => basename($filePath),
            'extracted' => $extracted,
        ]);

        return [
            'success'               => true,
            'error'                 => null,
            'monto_constancia_soles' => isset($extracted['monto_constancia_soles']) ? (float)$extracted['monto_constancia_soles'] : null,
        ];
    }

    /**
     * Método legado — llama a extractFromComprobante para compatibilidad.
     *
     * @deprecated Usar extractFromComprobante() directamente.
     */
    public function extractFromDocument($filePath, $mimeType)
    {
        return $this->extractFromComprobante($filePath, $mimeType);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Privados
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Realiza la llamada HTTP a la API de Gemini y retorna el JSON extraído.
     *
     * @return array ['success' => bool, 'data' => array|null, 'error' => string|null]
     */
    private function callGemini($filePath, $mimeType, $prompt, $maxOutputTokens = 256)
    {
        $apiKey = env('GEMINI_API_KEY');

        if (!$apiKey) {
            Log::error('GeminiService: GEMINI_API_KEY no configurado en .env');
            return ['success' => false, 'data' => null, 'error' => 'GEMINI_API_KEY no configurado'];
        }

        if (!file_exists($filePath)) {
            Log::error('GeminiService: Archivo no encontrado: ' . $filePath);
            return ['success' => false, 'data' => null, 'error' => 'Archivo no encontrado'];
        }

        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            return ['success' => false, 'data' => null, 'error' => 'No se pudo leer el archivo'];
        }

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data'      => base64_encode($fileContent),
                            ],
                        ],
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature'      => 0,
                'maxOutputTokens'  => $maxOutputTokens,
                'responseMimeType' => 'application/json',
            ],
        ];

        $url = self::getApiUrl() . '?key=' . $apiKey;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            Log::error('GeminiService: Error cURL: ' . $curlError);
            return ['success' => false, 'data' => null, 'error' => 'Error de conexión: ' . $curlError];
        }

        if ($httpCode !== 200) {
            Log::error('GeminiService: Error HTTP ' . $httpCode, ['response' => $response]);
            return ['success' => false, 'data' => null, 'error' => 'Error de API Gemini (HTTP ' . $httpCode . ')'];
        }

        $decoded = json_decode($response, true);
        if (!$decoded) {
            Log::error('GeminiService: Respuesta inválida de Gemini', ['response' => $response]);
            return ['success' => false, 'data' => null, 'error' => 'Respuesta inválida de Gemini'];
        }

        // Concatenar todos los parts (Gemini a veces devuelve "Here is the JSON:" en part[0] y el JSON en part[1])
        $textContent = '';
        $parts = $decoded['candidates'][0]['content']['parts'] ?? [];
        foreach ($parts as $part) {
            if (!empty($part['text'])) {
                $textContent .= $part['text'];
            }
        }

        if (trim($textContent) === '') {
            Log::warning('GeminiService: Respuesta vacía', ['decoded' => $decoded]);
            return ['success' => false, 'data' => null, 'error' => 'Respuesta vacía de Gemini'];
        }

        // Limpiar posibles markdown code blocks y texto previo/posterior al JSON
        $textContent = preg_replace('/```(?:json)?\s*/', '', $textContent);
        $textContent = trim($textContent);

        $jsonString = self::extractJsonFromText($textContent);
        $extracted = $jsonString !== null ? json_decode($jsonString, true) : null;

        if (!$extracted || !is_array($extracted)) {
            Log::warning('GeminiService: No se pudo parsear JSON', [
                'text' => $textContent,
                'parts_count' => count($parts),
            ]);
            return ['success' => false, 'data' => null, 'error' => 'No se pudo parsear el JSON extraído'];
        }

        return ['success' => true, 'data' => $extracted, 'error' => null];
    }

    /**
     * Extrae el primer objeto JSON de un texto que puede tener prefijo/sufijo
     * (ej. "Here is the JSON requested:\n\n{...}").
     */
    private static function extractJsonFromText(string $text): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        $first = strpos($text, '{');
        if ($first === false) {
            return null;
        }

        $depth = 0;
        $len = strlen($text);
        for ($i = $first; $i < $len; $i++) {
            $c = $text[$i];
            if ($c === '{') {
                $depth++;
            } elseif ($c === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($text, $first, $i - $first + 1);
                }
            }
        }

        return null;
    }

    private function errorResultComprobante()
    {
        return [
            'success'                  => false,
            'error'                    => null,
            'tipo_comprobante'         => null,
            'valor_comprobante'        => null,
            'tiene_detraccion'         => false,
            'monto_detraccion_dolares' => null,
            'monto_detraccion_soles'   => null,
        ];
    }
}
