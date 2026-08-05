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
            'Extrae los datos indicados. ' .
            'Reglas: ' .
            '- tipo_comprobante: "Factura" si es FACTURA ELECTRÓNICA, "Boleta" si es BOLETA DE VENTA, null si no aplica. ' .
            '- valor_comprobante: el importe TOTAL del comprobante (TOTAL con IGV). Solo el número, sin símbolo de moneda. null si no se encuentra. ' .
            '- tiene_detraccion: true si menciona detracción o "Sistema de Pago de Obligaciones Tributarias", false si no. ' .
            '- monto_detraccion_dolares: si hay detracción, monto en moneda del comprobante (usualmente USD); si no, null. ' .
            '- monto_detraccion_soles: si hay detracción, monto en soles ("Importe de la detracción (SOLES)"); si no, null. ' .
            'Responde solo con el JSON del schema (una línea, compacto).';

        // Schema + tokens altos: Gemini 2.5 gasta output en "thinking" y truncaba el JSON (MAX_TOKENS).
        $result = $this->callGemini(
            $filePath,
            $mimeType,
            $prompt,
            (int) env('GEMINI_COMPROBANTE_MAX_TOKENS', 4096),
            self::comprobanteResponseSchema()
        );

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

    /**
     * Genera y parsea JSON desde un prompt de texto (sin archivo adjunto).
     *
     * @param  string  $prompt
     * @param  int  $maxOutputTokens
     * @param  float  $temperature
     * @return array{success: bool, data: array|null, error: string|null}
     */
    /**
     * @param  string  $prompt
     * @param  int  $maxOutputTokens
     * @param  float  $temperature
     * @param  array<string, mixed>|null  $responseSchema  Schema Gemini (tipos OBJECT, STRING, etc.)
     * @return array{success: bool, data: array|null, error: string|null, finish_reason: string|null}
     */
    public function analyzeTextAsJson($prompt, $maxOutputTokens = 2048, $temperature = 0.2, $responseSchema = null)
    {
        $apiKey = env('GEMINI_API_KEY');

        if (!$apiKey) {
            Log::error('GeminiService: GEMINI_API_KEY no configurado en .env');
            return ['success' => false, 'data' => null, 'error' => 'GEMINI_API_KEY no configurado', 'finish_reason' => null];
        }

        $generationConfig = [
            'temperature' => (float) $temperature,
            'maxOutputTokens' => (int) $maxOutputTokens,
            'responseMimeType' => 'application/json',
        ];
        if (is_array($responseSchema) && !empty($responseSchema)) {
            $generationConfig['responseSchema'] = $responseSchema;
        }

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => (string) $prompt],
                    ],
                ],
            ],
            'generationConfig' => $generationConfig,
        ];

        $url = self::getApiUrl() . '?key=' . $apiKey;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            Log::error('GeminiService analyzeTextAsJson: cURL error', ['error' => $curlError]);
            return ['success' => false, 'data' => null, 'error' => 'Error de conexión: ' . $curlError, 'finish_reason' => null];
        }

        if ($httpCode !== 200) {
            Log::error('GeminiService analyzeTextAsJson: HTTP ' . $httpCode, ['response' => $response]);
            return ['success' => false, 'data' => null, 'error' => 'Error de API Gemini (HTTP ' . $httpCode . ')', 'finish_reason' => null];
        }

        $decoded = json_decode($response, true);
        if (!$decoded) {
            return ['success' => false, 'data' => null, 'error' => 'Respuesta inválida de Gemini', 'finish_reason' => null];
        }

        $candidate = isset($decoded['candidates'][0]) ? $decoded['candidates'][0] : [];
        $finishReason = isset($candidate['finishReason']) ? (string) $candidate['finishReason'] : null;

        $textContent = '';
        $parts = isset($candidate['content']['parts']) ? $candidate['content']['parts'] : [];
        foreach ($parts as $part) {
            if (!empty($part['text'])) {
                $textContent .= $part['text'];
            }
        }

        $textContent = preg_replace('/```(?:json)?\s*/', '', trim($textContent));
        $extracted = self::parseJsonPayload($textContent);
        if (!$extracted) {
            $extracted = self::salvageTruncatedJsonObject($textContent);
            if ($extracted) {
                Log::warning('GeminiService analyzeTextAsJson: JSON truncado recuperado parcialmente', [
                    'finish_reason' => $finishReason,
                    'keys' => array_keys($extracted),
                ]);
            }
        }

        if (!$extracted || !is_array($extracted)) {
            Log::warning('GeminiService analyzeTextAsJson: JSON inválido', [
                'finish_reason' => $finishReason,
                'text_preview' => mb_substr($textContent, 0, 600),
                'text_len' => mb_strlen($textContent),
            ]);
            $error = 'No se pudo parsear el JSON de Gemini';
            if ($finishReason === 'MAX_TOKENS') {
                $error = 'Respuesta Gemini truncada (MAX_TOKENS)';
            }

            return ['success' => false, 'data' => null, 'error' => $error, 'finish_reason' => $finishReason];
        }

        return ['success' => true, 'data' => $extracted, 'error' => null, 'finish_reason' => $finishReason];
    }

    /**
     * @param  string  $text
     * @return array<string, mixed>|null
     */
    private static function parseJsonPayload($text)
    {
        $text = trim((string) $text);
        if ($text === '') {
            return null;
        }

        $direct = json_decode($text, true);
        if (is_array($direct)) {
            return $direct;
        }

        $jsonString = self::extractJsonFromText($text);
        if ($jsonString === null) {
            return null;
        }

        $extracted = json_decode($jsonString, true);

        return is_array($extracted) ? $extracted : null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Privados
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Schema Gemini para comprobantes (fuerza JSON compacto y completo).
     *
     * @return array<string, mixed>
     */
    private static function comprobanteResponseSchema()
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'tipo_comprobante' => [
                    'type' => 'STRING',
                    'nullable' => true,
                ],
                'valor_comprobante' => [
                    'type' => 'NUMBER',
                    'nullable' => true,
                ],
                'tiene_detraccion' => [
                    'type' => 'BOOLEAN',
                ],
                'monto_detraccion_dolares' => [
                    'type' => 'NUMBER',
                    'nullable' => true,
                ],
                'monto_detraccion_soles' => [
                    'type' => 'NUMBER',
                    'nullable' => true,
                ],
            ],
            'required' => [
                'tipo_comprobante',
                'valor_comprobante',
                'tiene_detraccion',
                'monto_detraccion_dolares',
                'monto_detraccion_soles',
            ],
        ];
    }

    /**
     * Realiza la llamada HTTP a la API de Gemini y retorna el JSON extraído.
     *
     * @param  array<string, mixed>|null  $responseSchema
     * @return array ['success' => bool, 'data' => array|null, 'error' => string|null]
     */
    private function callGemini($filePath, $mimeType, $prompt, $maxOutputTokens = 256, $responseSchema = null)
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

        $fileBase64 = base64_encode($fileContent);
        $result = $this->requestGeminiJson(
            $mimeType,
            $prompt,
            $maxOutputTokens,
            $responseSchema,
            $fileBase64
        );

        // Reintento solo si Gemini cortó por MAX_TOKENS.
        if (
            empty($result['success'])
            && ($result['finish_reason'] ?? null) === 'MAX_TOKENS'
            && $maxOutputTokens < 8192
        ) {
            $retryTokens = min(8192, max(4096, (int) $maxOutputTokens * 2));
            Log::warning('GeminiService: reintento por MAX_TOKENS', [
                'max_output_tokens' => $retryTokens,
            ]);

            $retry = $this->requestGeminiJson(
                $mimeType,
                $prompt,
                $retryTokens,
                $responseSchema,
                $fileBase64
            );
            if (!empty($retry['success'])) {
                return $retry;
            }

            return $retry;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>|null  $responseSchema
     * @return array{success: bool, data: array|null, error: string|null, finish_reason?: string|null}
     */
    private function requestGeminiJson(
        $mimeType,
        $prompt,
        $maxOutputTokens,
        $responseSchema,
        $fileBase64,
        $useThinkingBudgetZero = true
    ) {
        $apiKey = env('GEMINI_API_KEY');

        $generationConfig = [
            'temperature' => 0,
            'maxOutputTokens' => (int) $maxOutputTokens,
            'responseMimeType' => 'application/json',
        ];
        if (is_array($responseSchema) && $responseSchema !== []) {
            $generationConfig['responseSchema'] = $responseSchema;
        }

        // Gemini 2.5: thinking consume maxOutputTokens y deja el JSON a medias.
        $model = strtolower(self::getModel());
        $supportsThinkingConfig = str_contains($model, '2.5') || str_contains($model, '2.0');
        if ($useThinkingBudgetZero && $supportsThinkingConfig) {
            $generationConfig['thinkingConfig'] = [
                'thinkingBudget' => 0,
            ];
        }

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => $fileBase64,
                            ],
                        ],
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => $generationConfig,
        ];

        $url = self::getApiUrl() . '?key=' . $apiKey;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            Log::error('GeminiService: Error cURL: ' . $curlError);

            return ['success' => false, 'data' => null, 'error' => 'Error de conexión: ' . $curlError, 'finish_reason' => null];
        }

        if ($httpCode !== 200) {
            // Si thinkingConfig no es soportado por el modelo, reintentar sin él.
            if (
                $httpCode === 400
                && $useThinkingBudgetZero
                && $supportsThinkingConfig
                && isset($generationConfig['thinkingConfig'])
            ) {
                Log::warning('GeminiService: thinkingConfig rechazado, reintento sin thinkingBudget');

                return $this->requestGeminiJson(
                    $mimeType,
                    $prompt,
                    $maxOutputTokens,
                    $responseSchema,
                    $fileBase64,
                    false
                );
            }

            Log::error('GeminiService: Error HTTP ' . $httpCode, ['response' => $response]);

            return ['success' => false, 'data' => null, 'error' => 'Error de API Gemini (HTTP ' . $httpCode . ')', 'finish_reason' => null];
        }

        $decoded = json_decode($response, true);
        if (!$decoded) {
            Log::error('GeminiService: Respuesta inválida de Gemini', ['response' => $response]);

            return ['success' => false, 'data' => null, 'error' => 'Respuesta inválida de Gemini', 'finish_reason' => null];
        }

        $candidate = isset($decoded['candidates'][0]) ? $decoded['candidates'][0] : [];
        $finishReason = isset($candidate['finishReason']) ? (string) $candidate['finishReason'] : null;

        $textContent = '';
        $parts = isset($candidate['content']['parts']) ? $candidate['content']['parts'] : [];
        foreach ($parts as $part) {
            if (!empty($part['text'])) {
                $textContent .= $part['text'];
            }
        }

        if (trim($textContent) === '') {
            Log::warning('GeminiService: Respuesta vacía', [
                'finish_reason' => $finishReason,
                'decoded' => $decoded,
            ]);

            return ['success' => false, 'data' => null, 'error' => 'Respuesta vacía de Gemini', 'finish_reason' => $finishReason];
        }

        $textContent = preg_replace('/```(?:json)?\s*/', '', $textContent);
        $textContent = trim($textContent);

        $extracted = self::parseJsonPayload($textContent);
        if (!$extracted) {
            $extracted = self::salvageTruncatedJsonObject($textContent);
            if ($extracted) {
                Log::warning('GeminiService: JSON truncado recuperado parcialmente', [
                    'finish_reason' => $finishReason,
                    'keys' => array_keys($extracted),
                    'text_preview' => mb_substr($textContent, 0, 400),
                ]);
            }
        }

        if (!$extracted || !is_array($extracted)) {
            Log::warning('GeminiService: No se pudo parsear JSON', [
                'text' => $textContent,
                'parts_count' => count($parts),
                'finish_reason' => $finishReason,
            ]);

            $error = 'No se pudo parsear el JSON extraído';
            if ($finishReason === 'MAX_TOKENS') {
                $error = 'Respuesta Gemini truncada (MAX_TOKENS)';
            }

            return ['success' => false, 'data' => null, 'error' => $error, 'finish_reason' => $finishReason];
        }

        return ['success' => true, 'data' => $extracted, 'error' => null, 'finish_reason' => $finishReason];
    }

    /**
     * Recupera un objeto JSON cortado a mitad (p. ej. ..."monto_detraccion_dolares": 106.00, "monto_).
     *
     * @return array<string, mixed>|null
     */
    private static function salvageTruncatedJsonObject($text)
    {
        $text = trim((string) $text);
        $first = strpos($text, '{');
        if ($first === false) {
            return null;
        }

        $chunk = substr($text, $first);

        // Quitar solo basura incompleta al final (no borrar el último campo válido).
        $chunk = preg_replace('/,\s*"[^"]*$/', '', $chunk);                 // ,"monto_
        $chunk = preg_replace('/,\s*"[^"]+"\s*:\s*"[^"]*$/', '', $chunk); // ,"k": "inc
        $chunk = preg_replace('/,\s*"[^"]+"\s*:\s*-?\d*\.$/', '', $chunk); // ,"k": 12.
        $chunk = preg_replace('/,\s*"[^"]+"\s*:\s*-$/', '', $chunk);       // ,"k": -
        $chunk = preg_replace('/,\s*"[^"]+"\s*:\s*$/', '', $chunk);        // ,"k":
        $chunk = rtrim((string) $chunk, ", \n\r\t");

        if ($chunk === '' || $chunk[0] !== '{') {
            return null;
        }

        if (substr($chunk, -1) !== '}') {
            $chunk .= '}';
        }

        $decoded = json_decode($chunk, true);

        return is_array($decoded) && $decoded !== [] ? $decoded : null;
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
        $inString = false;
        $escape = false;
        $len = strlen($text);

        for ($i = $first; $i < $len; $i++) {
            $c = $text[$i];

            if ($escape) {
                $escape = false;
                continue;
            }

            if ($inString) {
                if ($c === '\\') {
                    $escape = true;
                } elseif ($c === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($c === '"') {
                $inString = true;
                continue;
            }

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
