<?php

namespace App\Services\WaCopiloto;

use App\Models\CargaConsolidada\Contenedor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Próximos consolidados activos (fechas reales) para prompts del copiloto.
 */
class WaCopilotoConsolidadoContextService
{
    /**
     * Bloque compacto con cortes y entregas de consolidados abiertos.
     *
     * @return string
     */
    public function buildUpcomingBlock()
    {
        if (!config('meta_whatsapp_copiloto.analysis_consolidado_context_enabled', true)) {
            return '';
        }

        $ttl = max(300, (int) config('meta_whatsapp_copiloto.analysis_consolidado_context_cache_ttl', 1800));
        $limit = max(1, min(5, (int) config('meta_whatsapp_copiloto.analysis_consolidado_context_max_items', 3)));

        return Cache::remember('wa_copiloto_consolidados_upcoming_v1_' . $limit, $ttl, function () use ($limit) {
            return $this->formatUpcomingBlock($this->fetchUpcoming($limit));
        });
    }

    /**
     * @param  int  $limit
     * @return \Illuminate\Support\Collection
     */
    protected function fetchUpcoming($limit)
    {
        $today = Carbon::today();

        return Contenedor::query()
            ->whereIn('estado', ['PENDIENTE', 'RECIBIENDO'])
            ->whereNotNull('f_cierre')
            ->where('f_cierre', '>=', $today)
            ->orderBy('f_cierre')
            ->orderBy('id')
            ->limit($limit)
            ->get([
                'id',
                'carga',
                'f_cierre',
                'f_puerto',
                'f_entrega',
                'estado',
                'estado_china',
            ]);
    }

    /**
     * @param  \Illuminate\Support\Collection  $rows
     * @return string
     */
    protected function formatUpcomingBlock($rows)
    {
        if ($rows->isEmpty()) {
            return '';
        }

        $lines = [
            'CONSOLIDADOS ACTIVOS (fechas reales del sistema — úsalas para urgencia y logística, no inventes otras):',
        ];

        foreach ($rows as $row) {
            $parts = [];
            $carga = trim((string) $row->carga);
            if ($carga !== '') {
                $parts[] = 'Consolidado #' . $carga;
            } else {
                $parts[] = 'Consolidado ID ' . (int) $row->id;
            }

            $cierre = $this->formatDate($row->f_cierre);
            if ($cierre !== '') {
                $parts[] = 'Fecha de corte (China): ' . $cierre;
            }

            $puerto = $this->formatDate($row->f_puerto);
            if ($puerto !== '') {
                $parts[] = 'Llegada a puerto (aprox.): ' . $puerto;
            }

            $entrega = $this->formatDate($row->f_entrega);
            if ($entrega !== '') {
                $parts[] = 'Entrega Lima (aprox.): ' . $entrega;
            }

            $estado = trim((string) $row->estado);
            if ($estado !== '') {
                $parts[] = 'Estado: ' . $estado;
            }

            $lines[] = '- ' . implode(' | ', $parts);
        }

        $lines[] = 'Si el cliente duda tiempos o compara con courier, usa estas fechas para explicar el proceso y crear urgencia suave (corte próximo, reservar espacio sin pagar ahora).';

        return implode("\n", $lines);
    }

    /**
     * @param  mixed  $value
     * @return string
     */
    protected function formatDate($value)
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->locale('es')->isoFormat('D MMM YYYY');
        } catch (\Exception $e) {
            return '';
        }
    }
}
