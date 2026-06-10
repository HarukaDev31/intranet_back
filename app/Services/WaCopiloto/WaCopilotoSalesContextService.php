<?php

namespace App\Services\WaCopiloto;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Contexto estático de ventas (reglas de negocio + conversaciones WON) para prompts Gemini.
 */
class WaCopilotoSalesContextService
{
    /**
     * Bloque completo para inyectar en el prompt de análisis.
     */
    /**
     * @param  bool  $includeWonExcerpts  Si false, solo reglas de negocio (fallback ante error Gemini).
     */
    public function buildKnowledgeBlock($includeWonExcerpts = true)
    {
        return $this->buildKnowledgeBlockForMessage('', '', $includeWonExcerpts);
    }

    /**
     * Bloque de ventas contextual al mensaje (elige excerpts WON más relevantes).
     *
     * @param  string  $latestBody
     * @param  string  $threadText
     * @param  bool  $includeWonExcerpts
     * @return string
     */
    public function buildKnowledgeBlockForMessage($latestBody = '', $threadText = '', $includeWonExcerpts = true)
    {
        $rules = self::businessRulesBlock();

        if (!$includeWonExcerpts || !config('meta_whatsapp_copiloto.analysis_sales_context_enabled', true)) {
            return $rules;
        }

        $maxChars = max(4000, (int) config('meta_whatsapp_copiloto.analysis_sales_context_max_chars', 18000));
        $budget = max(0, $maxChars - mb_strlen($rules) - 80);
        $combined = mb_strtolower(trim($latestBody . "\n" . $threadText));
        $excerpts = $combined !== ''
            ? $this->loadWonExcerptsForMessage($combined, $budget)
            : $this->loadWonExcerpts($budget);

        if ($excerpts === '') {
            return $rules;
        }

        return $rules
            . "\n\n--- EJEMPLOS WON (V:=vendedor, C:=cliente) — imita tono y tácticas de cierre/urgencia, no copies literal ---\n"
            . $excerpts;
    }

    public static function businessRulesBlock()
    {
        return <<<'RULES'
REGLAS DE NEGOCIO PROBUSINESS (carga consolidada China → Perú):

Servicio:
- Importación consolidada desde China con almacén propio en Yiwu.
- El proveedor del cliente entrega en Yiwu; Probusiness gestiona flete, aduana, permisos y logística hasta Lima.
- El cliente recoge mercadería en Lima (no es courier puerta a puerta salvo lo acordado).

Restricciones:
- NO se importan productos con marca o logotipos patentados sin autorización.
- Toda carga debe llevar rotulado especial Probusiness; sin rotulado no se acepta.
- Los datos del almacén/proveedor completos se entregan SOLO tras confirmación formal.

Flujo comercial típico (etapas):
1) Calificación: pedir nombre completo, DNI, correo; validar si producto tiene marca.
2) Documentos: solicitar proforma/invoice y packing list (PL) para cotizar.
3) Cotización: enviar consolidado (#), peso, volumen, resumen de costos; explicar dos pagos.
4) Cierre: pedir "Si confirmo" escrito (el sistema solo valida esa frase exacta).
5) Post-confirmación: rotulado, enlace datos proveedor, contrato firma-acuerdo-servicio, coordinación.

Pagos (siempre explicar así):
- Pago 1: SERVICIO DE IMPORTACIÓN — antes del zarpe del buque.
- Pago 2: IMPUESTOS — 10 días antes de llegada a puerto.
- No pagan nada al reservar; el pago del servicio es cuando la carga llega al almacén en Yiwu (reservan espacio sin adelanto).

Fechas consolidado (formato habitual):
- Fecha de Corte (China) → Llegada a puerto (aprox.) → Fecha de entrega (aprox.).
- La fecha de salida del contenedor es tentativa; la fecha de entrega es la referencia firme.
- Si el proveedor no llega al corte, la carga se rolea al siguiente consolidado.

Confirmación y urgencia (presión indirecta, estilo vendedor top):
- "Revísala y, si estás dentro, confirmamos de una vez para asegurar tu espacio."
- "No necesitas pagar nada ahora; cancelas cuando tu mercadería llegue a nuestro almacén."
- "El consolidado cierra el [fecha corte real] — si tu proveedor no llega a Yiwu antes, rola al siguiente."
- "Reserva tu espacio hoy; muchos clientes confirman en esta etapa."
- Cuando el cliente compara con DHL/courier: resalta gestión integral (aduana, permisos, volumen) sin despreciar su amigo; cierra con beneficio + siguiente paso concreto.
- Cuando pregunta tiempos: usa fechas REALES del bloque CONSOLIDADOS ACTIVOS (corte → puerto → entrega Lima).

Cliente antiguo:
- Requiere 2 participaciones seguidas; a la tercera aplica tarifa cliente antiguo.
- Si hubo error de cotización como antiguo sin cumplir requisito, explicar con transparencia y recotizar.

Items por volumen (carga total, aunque sean varios proveedores):
- Menos de 1 CBM: 6 ítems gratis, máximo 10 (+20 USD por ítem extra).
- De 1 a 2 CBM: 8 ítems gratis, máximo 15 (+10 USD por ítem extra).
- Ante dudas con varios ítems, consultar ANTES de comprar.

Objeciones frecuentes y cómo responder:
- "Está caro" / "sale más" → Ver PERFIL COMERCIAL: si RECURRENTE/ANTIGUO/PREMIUM, ofrecer recotizar con tarifa preferencial o descuento por fidelidad; si NUEVO, reforzar valor (aduana, permisos, sin costos ocultos) y revisar puntos de la cotización. No inventar monto de descuento sin dato en calculadora.
- "¿Por qué subió el precio?" → Explicar recotización por volumen/medidas, tarifa cliente nuevo vs antiguo, o actualización de sistema.
- "¿Puedo pagar con RUC empresa?" → Contrato lo firma la persona; facturación puede salir con RUC 20 si lo indica a facturación.
- "¿Qué pasa si mi proveedor se demora?" → Se rolea al siguiente consolidado; no pierde la reserva.
- "¿Cuándo me dan dirección de China?" → Tras "Si confirmo", con rotulado y datos de contacto coordinación.

Tono del vendedor:
- Cercano, profesional, emojis moderados (📅 🧮 🛳 🤝).
- Frases cortas, claras, orientadas a acción.
- Nunca inventar precios, fechas ni políticas no mencionadas en el hilo actual.
RULES;
    }

    /**
     * @param  int  $maxChars
     * @return string
     */
    protected function loadWonExcerpts($maxChars)
    {
        return $this->loadWonExcerptsForMessage('', $maxChars);
    }

    /**
     * Prioriza ventas WON cuyo contenido coincide con el mensaje del cliente.
     *
     * @param  string  $messageLower
     * @param  int  $maxChars
     * @return string
     */
    protected function loadWonExcerptsForMessage($messageLower, $maxChars)
    {
        if ($maxChars <= 0) {
            return '';
        }

        $sections = $this->loadParsedWonSections();
        if (empty($sections)) {
            return '';
        }

        $ttl = max(300, (int) config('meta_whatsapp_copiloto.analysis_sales_context_cache_ttl', 86400));
        $excerptTtl = min(3600, $ttl);
        $cacheKey = 'wa_copiloto_sales_won_excerpt_v3_' . md5($messageLower . '|' . $maxChars);

        return Cache::remember($cacheKey, $excerptTtl, function () use ($sections, $messageLower, $maxChars) {
            if ($messageLower === '') {
                return $this->sampleSections($sections, $maxChars);
            }

            return $this->sampleRelevantSections($sections, $messageLower, $maxChars);
        });
    }

    /**
     * @return array<int, string>
     */
    protected function loadParsedWonSections()
    {
        $ttl = max(300, (int) config('meta_whatsapp_copiloto.analysis_sales_context_cache_ttl', 86400));

        return Cache::remember('wa_copiloto_sales_won_sections_v3', $ttl, function () {
            $relativePath = (string) config('meta_whatsapp_copiloto.analysis_sales_context_path', 'ventas_contexto.txt');
            if (!Storage::disk('local')->exists($relativePath)) {
                return [];
            }

            $raw = Storage::disk('local')->get($relativePath);
            if (!is_string($raw) || trim($raw) === '') {
                return [];
            }

            $sections = $this->parseWonSections($raw);
            if (!empty($sections)) {
                return $sections;
            }

            $fallback = trim($raw);
            if ($fallback === '') {
                return [];
            }

            return [$fallback];
        });
    }

    /**
     * @param  string  $raw
     * @return array<int, string>
     */
    protected function parseWonSections($raw)
    {
        $parts = preg_split('/\n---\s*\n/', $raw);
        if (!is_array($parts)) {
            return [];
        }

        $sections = [];
        foreach ($parts as $part) {
            $chunk = trim((string) $part);
            if ($chunk === '') {
                continue;
            }
            if (stripos($chunk, '## VENTA #') === false && stripos($chunk, 'CONVERSACIONES REALES') === false) {
                continue;
            }
            if (stripos($chunk, 'CONVERSACIONES REALES') !== false && stripos($chunk, '## VENTA #') === false) {
                continue;
            }
            $sections[] = $chunk;
        }

        return $sections;
    }

    /**
     * Muestra secciones WON distribuidas en el archivo para cubrir variedad de casos.
     *
     * @param  array<int, string>  $sections
     * @param  int  $maxChars
     * @return string
     */
    protected function sampleSections(array $sections, $maxChars)
    {
        $total = count($sections);
        if ($total === 0) {
            return '';
        }

        $maxSections = max(3, min(10, (int) config('meta_whatsapp_copiloto.analysis_sales_context_max_sections', 6)));
        $maxSectionChars = max(600, min(3500, (int) config('meta_whatsapp_copiloto.analysis_sales_context_section_max_chars', 2000)));

        $picked = [];
        $usedChars = 0;
        $step = max(1, (int) floor($total / $maxSections));

        for ($i = 0; $i < $total && count($picked) < $maxSections; $i += $step) {
            $section = $this->truncateText($sections[$i], $maxSectionChars);
            $len = mb_strlen($section) + 6;
            if ($usedChars + $len > $maxChars) {
                break;
            }
            $picked[] = $section;
            $usedChars += $len;
        }

        if (empty($picked)) {
            $picked[] = $this->truncateText($sections[0], min($maxChars, $maxSectionChars));
        }

        return implode("\n\n---\n\n", $picked);
    }

    /**
     * @param  array<int, string>  $sections
     * @param  string  $messageLower
     * @param  int  $maxChars
     * @return string
     */
    protected function sampleRelevantSections(array $sections, $messageLower, $maxChars)
    {
        $maxSections = max(3, min(10, (int) config('meta_whatsapp_copiloto.analysis_sales_context_max_sections', 6)));
        $maxSectionChars = max(600, min(3500, (int) config('meta_whatsapp_copiloto.analysis_sales_context_section_max_chars', 2000)));

        $scored = [];
        foreach ($sections as $idx => $section) {
            $scored[] = [
                'idx' => $idx,
                'score' => $this->scoreWonSection($section, $messageLower),
                'text' => $section,
            ];
        }

        usort($scored, function ($a, $b) {
            if ($a['score'] === $b['score']) {
                return $a['idx'] - $b['idx'];
            }

            return $b['score'] - $a['score'];
        });

        $picked = [];
        $usedChars = 0;
        $usedIdx = [];

        foreach ($scored as $row) {
            if (count($picked) >= $maxSections) {
                break;
            }
            if ($row['score'] <= 0 && count($picked) >= 2) {
                continue;
            }

            $section = $this->truncateText($row['text'], $maxSectionChars);
            $len = mb_strlen($section) + 6;
            if ($usedChars + $len > $maxChars) {
                continue;
            }

            $picked[] = $section;
            $usedIdx[$row['idx']] = true;
            $usedChars += $len;
        }

        if (count($picked) < 2) {
            foreach ($scored as $row) {
                if (isset($usedIdx[$row['idx']])) {
                    continue;
                }
                $section = $this->truncateText($row['text'], $maxSectionChars);
                $len = mb_strlen($section) + 6;
                if ($usedChars + $len > $maxChars) {
                    break;
                }
                $picked[] = $section;
                $usedIdx[$row['idx']] = true;
                $usedChars += $len;
                if (count($picked) >= $maxSections) {
                    break;
                }
            }
        }

        if (empty($picked)) {
            return $this->sampleSections($sections, $maxChars);
        }

        return implode("\n\n---\n\n", $picked);
    }

    /**
     * @param  string  $section
     * @param  string  $messageLower
     * @return int
     */
    protected function scoreWonSection($section, $messageLower)
    {
        $haystack = mb_strtolower($section);
        $score = 0;

        $topicKeywords = [
            'objecion' => ['dhl', 'courier', 'amigo', 'barato', 'caro', 'precio', 'por qué', 'porque', 'compar'],
            'tiempo' => ['tiempo', 'demora', 'demoran', 'cuanto tarda', 'cuánto tarda', 'largo', 'dias', 'días', 'semanas', 'yiwu', 'lima'],
            'cierre' => ['confirmo', 'confirmar', 'reserva', 'espacio', 'cierre', 'cerramos', 'asegurar'],
            'documentos' => ['proforma', 'invoice', 'packing', 'packing list', 'documento', 'pdf'],
            'marca' => ['marca', 'nike', 'logo', 'patent', 'autoriz'],
            'cotizacion' => ['cotiz', 'cuanto sale', 'cuánto sale', 'costo', 'presupuesto', 'cbm', 'volumen', 'caro', 'cara', 'barato', 'descuento', 'precio'],
        ];

        foreach ($topicKeywords as $topic => $keywords) {
            $topicHit = false;
            foreach ($keywords as $keyword) {
                if (mb_strpos($messageLower, $keyword) !== false) {
                    $topicHit = true;
                    $score += 3;
                }
                if (mb_strpos($haystack, $keyword) !== false) {
                    $score += 2;
                }
            }
            if ($topicHit && mb_strpos($haystack, $topic) !== false) {
                $score += 2;
            }
        }

        if (mb_strpos($haystack, 'v:') !== false && mb_strpos($haystack, 'c:') !== false) {
            $score += 1;
        }

        return $score;
    }

    /**
     * @param  string  $text
     * @param  int  $maxChars
     * @return string
     */
    protected function truncateText($text, $maxChars)
    {
        $text = trim((string) $text);
        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }

        return mb_substr($text, 0, $maxChars - 1) . '…';
    }
}
