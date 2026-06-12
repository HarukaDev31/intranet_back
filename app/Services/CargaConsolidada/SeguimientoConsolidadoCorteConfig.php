<?php

namespace App\Services\CargaConsolidada;

use App\Services\SystemConfigService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hora de corte configurable para bloques históricos de CARGA POR CONTACTAR.
 */
class SeguimientoConsolidadoCorteConfig
{
    /**
     * @return array{hora: string, timezone: string}
     */
    public static function settings()
    {
        /** @var SystemConfigService $configs */
        $configs = app(SystemConfigService::class);

        $hora = $configs->get(
            SystemConfigService::KEY_EXCEL_SEGUIMIENTO_HORA_CORTE,
            (string) config('carga_consolidada.seguimiento_corte_hora', '20:00')
        );

        if (!preg_match('/^\d{1,2}:\d{2}$/', (string) $hora)) {
            $hora = '20:00';
        }

        $timezone = $configs->get(
            SystemConfigService::KEY_EXCEL_SEGUIMIENTO_TIMEZONE,
            (string) config('carga_consolidada.seguimiento_corte_timezone', 'America/Lima')
        );

        if ($timezone === '') {
            $timezone = 'America/Lima';
        }

        return [
            'hora' => $hora,
            'timezone' => $timezone,
        ];
    }

    /**
     * Payload para API / UI.
     *
     * @return array<string, mixed>
     */
    public static function toPublicArray()
    {
        $settings = self::settings();
        $periodo = self::periodoContactarVigente();

        return [
            'hora_corte' => $settings['hora'],
            'timezone' => $settings['timezone'],
            'periodo_contactar_inicio' => $periodo['inicio']->format('d/m/Y H:i'),
            'periodo_contactar_fin' => $periodo['fin']->format('d/m/Y H:i'),
            'excel_config_label' => self::excelConfigLabel(),
        ];
    }

    /**
     * Último instante de corte <= ahora (inicio del periodo vigente CONTACTAR).
     *
     * @param Carbon|null $now
     * @return Carbon
     */
    public static function ultimoCorteFin(Carbon $now = null)
    {
        $settings = self::settings();
        $now = ($now ?: Carbon::now($settings['timezone']))->copy()->timezone($settings['timezone']);

        [$hour, $minute] = array_map('intval', explode(':', $settings['hora']));
        $corteHoy = $now->copy()->setTime($hour, $minute, 0);

        if ($now->gte($corteHoy)) {
            return $corteHoy;
        }

        return $corteHoy->subDay();
    }

    /**
     * Periodo de la tabla CONTACTAR vigente (desde último corte hasta ahora).
     *
     * @return array{inicio: Carbon, fin: Carbon, hora: string, timezone: string}
     */
    public static function periodoContactarVigente()
    {
        $settings = self::settings();
        $fin = Carbon::now($settings['timezone']);
        $inicio = self::ultimoCorteFin($fin);

        return [
            'inicio' => $inicio,
            'fin' => $fin,
            'hora' => $settings['hora'],
            'timezone' => $settings['timezone'],
        ];
    }

    /**
     * Periodo cerrado al ejecutar el job de corte (últimas 24 h entre cortes).
     *
     * @return array{inicio: Carbon, fin: Carbon}
     */
    public static function periodoCorteJob()
    {
        $settings = self::settings();
        $fin = self::ultimoCorteFin();
        $inicio = $fin->copy()->subDay();

        return [
            'inicio' => $inicio->timezone($settings['timezone']),
            'fin' => $fin->timezone($settings['timezone']),
        ];
    }

    /**
     * Periodo CONTACTAR abierto (desde último corte hasta ahora).
     *
     * @return array{inicio: Carbon, fin: Carbon, hora: string, timezone: string}
     */
    public static function periodoContactarAbierto()
    {
        return self::periodoContactarVigente();
    }

    /**
     * Inicio del día calendario actual (primera vinculación: CONTACTAR solo desde hoy).
     *
     * @param Carbon|null $now
     * @return Carbon
     */
    public static function inicioDiaHoy(Carbon $now = null)
    {
        $settings = self::settings();
        $now = ($now ?: Carbon::now($settings['timezone']))->copy()->timezone($settings['timezone']);

        return $now->copy()->startOfDay();
    }

    /**
     * Periodo CONTACTAR abierto en la primera creación del Excel (desde 00:00 de hoy).
     *
     * @param Carbon|null $now
     * @return array{inicio: Carbon, fin: Carbon, hora: string, timezone: string}
     */
    public static function periodoContactarDesdeInicioDia(Carbon $now = null)
    {
        $settings = self::settings();
        $fin = ($now ?: Carbon::now($settings['timezone']))->copy()->timezone($settings['timezone']);

        return [
            'inicio' => self::inicioDiaHoy($fin),
            'fin' => $fin,
            'hora' => $settings['hora'],
            'timezone' => $settings['timezone'],
        ];
    }

    /**
     * @param int $idContenedor
     * @return bool
     */
    public static function contenedorTieneHistoricoContactar($idContenedor)
    {
        if (!Schema::hasTable('contenedor_seguimiento_corte_periodos')) {
            return false;
        }

        return DB::table('contenedor_seguimiento_corte_periodos')
            ->where('id_contenedor', (int) $idContenedor)
            ->exists();
    }

    /**
     * Fin del periodo CONTACTAR recién cerrado (si ya pasó la hora de corte hoy).
     *
     * @param Carbon|null $now
     * @return Carbon|null
     */
    public static function ultimoPeriodoCerradoFin(Carbon $now = null)
    {
        $settings = self::settings();
        $now = ($now ?: Carbon::now($settings['timezone']))->copy()->timezone($settings['timezone']);
        $corte = self::ultimoCorteFin($now);

        if ($now->lte($corte)) {
            return null;
        }

        return $corte;
    }

    /**
     * Texto para la sección de configuración en el Excel.
     *
     * @return string
     */
    public static function excelConfigLabel()
    {
        $settings = self::settings();
        $abierto = self::periodoContactarAbierto();

        return 'Hora de corte: '
            . $settings['hora']
            . ' ('
            . $settings['timezone']
            . ') | CONTACTAR: histórico congelado arriba, periodo abierto al final ('
            . $abierto['inicio']->format('d/m/Y H:i')
            . ' → ahora)';
    }

    /**
     * Texto de configuración cuando aún no hay cortes históricos (primera vinculación).
     *
     * @return string
     */
    public static function excelConfigLabelPrimeraVez()
    {
        $settings = self::settings();
        $abierto = self::periodoContactarDesdeInicioDia();

        return 'Hora de corte: '
            . $settings['hora']
            . ' ('
            . $settings['timezone']
            . ') | CONTACTAR: solo ingresos desde hoy ('
            . $abierto['inicio']->format('d/m/Y H:i')
            . ' → ahora)';
    }
}
