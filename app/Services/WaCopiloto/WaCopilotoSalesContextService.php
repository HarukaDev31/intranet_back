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
    public function buildKnowledgeBlock()
    {
        if (!config('meta_whatsapp_copiloto.analysis_sales_context_enabled', true)) {
            return self::businessRulesBlock();
        }

        $cacheKey = 'wa_copiloto_sales_knowledge_v2';
        $ttl = max(300, (int) config('meta_whatsapp_copiloto.analysis_sales_context_cache_ttl', 86400));

        return Cache::remember($cacheKey, $ttl, function () {
            $rules = self::businessRulesBlock();
            $maxChars = max(8000, (int) config('meta_whatsapp_copiloto.analysis_sales_context_max_chars', 80000));
            $budget = max(0, $maxChars - mb_strlen($rules) - 80);
            $excerpts = $this->loadWonExcerpts($budget);

            if ($excerpts === '') {
                return $rules;
            }

            return $rules
                . "\n\n--- CONVERSACIONES REALES CERRADAS (WON) — imita tono y respuestas del vendedor (V:) ---\n"
                . $excerpts;
        });
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

Confirmación y urgencia:
- "Revísala y, si estás dentro, confirmamos de una vez para asegurar tu espacio."
- "No necesitas pagar nada ahora; cancelas cuando tu mercadería llegue a nuestro almacén."
- "El contenedor está por cerrarse / espacio al X% — reserva con tiempo."

Cliente antiguo:
- Requiere 2 participaciones seguidas; a la tercera aplica tarifa cliente antiguo.
- Si hubo error de cotización como antiguo sin cumplir requisito, explicar con transparencia y recotizar.

Items por volumen (carga total, aunque sean varios proveedores):
- Menos de 1 CBM: 6 ítems gratis, máximo 10 (+20 USD por ítem extra).
- De 1 a 2 CBM: 8 ítems gratis, máximo 15 (+10 USD por ítem extra).
- Ante dudas con varios ítems, consultar ANTES de comprar.

Objeciones frecuentes y cómo responder:
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
        if ($maxChars <= 0) {
            return '';
        }

        $relativePath = (string) config('meta_whatsapp_copiloto.analysis_sales_context_path', 'ventas_contexto.txt');
        if (!Storage::disk('local')->exists($relativePath)) {
            return '';
        }

        $raw = Storage::disk('local')->get($relativePath);
        if (!is_string($raw) || trim($raw) === '') {
            return '';
        }

        $sections = $this->parseWonSections($raw);
        if (empty($sections)) {
            return $this->truncateText($raw, $maxChars);
        }

        return $this->sampleSections($sections, $maxChars);
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

        $picked = [];
        $usedChars = 0;
        $step = max(1, (int) floor($total / 20));

        for ($i = 0; $i < $total; $i += $step) {
            $section = $sections[$i];
            $len = mb_strlen($section) + 6;
            if ($usedChars + $len > $maxChars) {
                break;
            }
            $picked[] = $section;
            $usedChars += $len;
        }

        if (empty($picked)) {
            $picked[] = $this->truncateText($sections[0], $maxChars);
        }

        return implode("\n\n---\n\n", $picked);
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
