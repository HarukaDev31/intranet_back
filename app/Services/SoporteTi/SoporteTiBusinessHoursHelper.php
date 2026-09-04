<?php

namespace App\Services\SoporteTi;

use App\Models\SoporteTi\SoporteTiHorarioAtencion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Horario laboral Soporte TI (America/Lima por defecto).
 * dia_semana ISO: 1=Lunes … 7=Domingo.
 */
class SoporteTiBusinessHoursHelper
{
    const TZ_DEFAULT = 'America/Lima';
    const CACHE_KEY = 'soporte_ti:horario_atencion:v1';
    const CACHE_TTL = 300;

    /** @var array<int, array{activo: bool, inicio: string, fin: string}>|null */
    protected $schedule = null;

    /** @var string|null */
    protected $timezone = null;

    /**
     * @return array<int, array{dia_semana: int, activo: bool, hora_inicio: string, hora_fin: string, timezone: string, id?: int, updated_at?: string|null}>
     */
    public function listarDias()
    {
        $rows = SoporteTiHorarioAtencion::query()->orderBy('dia_semana')->get();
        $out = array();
        foreach ($rows as $row) {
            $out[] = $this->mapRow($row);
        }

        return $out;
    }

    /**
     * @param array $dias
     * @return array
     */
    public function actualizarDias(array $dias)
    {
        foreach ($dias as $item) {
            if (!is_array($item) || !isset($item['dia_semana'])) {
                throw new \InvalidArgumentException('Cada día debe incluir dia_semana.');
            }
            $dia = (int) $item['dia_semana'];
            if ($dia < 1 || $dia > 7) {
                throw new \InvalidArgumentException('dia_semana debe estar entre 1 (lun) y 7 (dom).');
            }

            $activo = !empty($item['activo']);
            $inicio = $this->normalizarHora(isset($item['hora_inicio']) ? $item['hora_inicio'] : '09:00');
            $fin = $this->normalizarHora(isset($item['hora_fin']) ? $item['hora_fin'] : '18:00');
            if ($inicio >= $fin) {
                throw new \InvalidArgumentException('hora_inicio debe ser anterior a hora_fin (día ' . $dia . ').');
            }

            $tz = isset($item['timezone']) && $item['timezone']
                ? trim((string) $item['timezone'])
                : self::TZ_DEFAULT;

            $row = SoporteTiHorarioAtencion::query()->firstOrNew(array('dia_semana' => $dia));
            $row->activo = $activo;
            $row->hora_inicio = $inicio;
            $row->hora_fin = $fin;
            $row->timezone = $tz;
            $row->save();
        }

        $this->forgetCache();

        return $this->listarDias();
    }

    /**
     * @return bool
     */
    public function estaDentroDeHorario(Carbon $momento = null)
    {
        $tz = $this->timezone();
        $m = $momento ? $momento->copy()->setTimezone($tz) : Carbon::now($tz);
        $ventana = $this->ventanaDelDia($m);
        if (!$ventana) {
            return false;
        }

        $t = $m->format('H:i:s');

        return $t >= $ventana['inicio'] && $t < $ventana['fin'];
    }

    /**
     * Segundos hábiles entre dos instantes (excluye fuera de horario).
     *
     * @param Carbon $from
     * @param Carbon $to
     * @return int
     */
    public function segundosHabilesEntre(Carbon $from, Carbon $to)
    {
        $tz = $this->timezone();
        $start = $from->copy()->setTimezone($tz);
        $end = $to->copy()->setTimezone($tz);
        if ($end->lte($start)) {
            return 0;
        }

        if (!$this->tieneAlgunaVentanaActiva()) {
            return max(0, $end->getTimestamp() - $start->getTimestamp());
        }

        $total = 0;
        $cursor = $start->copy()->startOfDay();
        $lastDay = $end->copy()->startOfDay();
        $guard = 0;

        while ($cursor->lte($lastDay) && $guard < 3700) {
            $guard++;
            $ventana = $this->ventanaDelDia($cursor);
            if ($ventana) {
                $winStart = $cursor->copy()->setTimeFromTimeString($ventana['inicio']);
                $winEnd = $cursor->copy()->setTimeFromTimeString($ventana['fin']);
                $segStart = $start->gt($winStart) ? $start->copy() : $winStart;
                $segEnd = $end->lt($winEnd) ? $end->copy() : $winEnd;
                if ($segEnd->gt($segStart)) {
                    $total += $segEnd->getTimestamp() - $segStart->getTimestamp();
                }
            }
            $cursor->addDay();
        }

        return (int) max(0, $total);
    }

    /**
     * @param Carbon $from
     * @param int    $segundos
     * @return Carbon
     */
    public function addSegundosHabiles(Carbon $from, $segundos)
    {
        $segundos = (int) max(0, $segundos);
        $tz = $this->timezone();
        $cursor = $from->copy()->setTimezone($tz);

        if ($segundos === 0) {
            return $cursor;
        }

        if (!$this->tieneAlgunaVentanaActiva()) {
            return $cursor->addSeconds($segundos);
        }

        $remaining = $segundos;
        $guard = 0;

        while ($remaining > 0 && $guard < 20000) {
            $guard++;
            $ventana = $this->ventanaDelDia($cursor);
            if (!$ventana) {
                $cursor->addDay()->startOfDay();
                continue;
            }

            $winStart = $cursor->copy()->startOfDay()->setTimeFromTimeString($ventana['inicio']);
            $winEnd = $cursor->copy()->startOfDay()->setTimeFromTimeString($ventana['fin']);

            if ($cursor->lt($winStart)) {
                $cursor = $winStart->copy();
            }

            if ($cursor->gte($winEnd)) {
                $cursor->addDay()->startOfDay();
                continue;
            }

            $disponible = $winEnd->getTimestamp() - $cursor->getTimestamp();
            if ($disponible <= 0) {
                $cursor->addDay()->startOfDay();
                continue;
            }

            if ($remaining <= $disponible) {
                return $cursor->copy()->addSeconds($remaining);
            }

            $remaining -= $disponible;
            $cursor = $winEnd->copy()->addDay()->startOfDay();
        }

        return $cursor->addSeconds($remaining);
    }

    /**
     * @param Carbon $from
     * @param int|float $horas
     * @return Carbon
     */
    public function addHorasHabiles(Carbon $from, $horas)
    {
        return $this->addSegundosHabiles($from, (int) round(((float) $horas) * 3600));
    }

    public function forgetCache()
    {
        Cache::forget(self::CACHE_KEY);
        $this->schedule = null;
        $this->timezone = null;
    }

    /**
     * @return string
     */
    public function timezone()
    {
        $this->ensureLoaded();

        return $this->timezone ?: self::TZ_DEFAULT;
    }

    /**
     * @param SoporteTiHorarioAtencion $row
     * @return array
     */
    protected function mapRow(SoporteTiHorarioAtencion $row)
    {
        return array(
            'id' => (int) $row->id,
            'dia_semana' => (int) $row->dia_semana,
            'activo' => (bool) $row->activo,
            'hora_inicio' => $this->horaSinSegundos($row->hora_inicio),
            'hora_fin' => $this->horaSinSegundos($row->hora_fin),
            'timezone' => $row->timezone ?: self::TZ_DEFAULT,
            'updated_at' => $row->updated_at ? $row->updated_at->toIso8601String() : null,
        );
    }

    /**
     * @param mixed $hora
     * @return string H:i
     */
    protected function horaSinSegundos($hora)
    {
        $s = $this->normalizarHora($hora);

        return substr($s, 0, 5);
    }

    /**
     * @param mixed $hora
     * @return string H:i:s
     */
    protected function normalizarHora($hora)
    {
        $raw = trim((string) $hora);
        if (preg_match('/^\d{1,2}:\d{2}$/', $raw)) {
            $raw .= ':00';
        }
        if (!preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $raw)) {
            throw new \InvalidArgumentException('Formato de hora inválido: ' . $hora);
        }
        $parts = explode(':', $raw);
        $h = (int) $parts[0];
        $m = (int) $parts[1];
        $s = (int) $parts[2];
        if ($h > 23 || $m > 59 || $s > 59) {
            throw new \InvalidArgumentException('Hora fuera de rango: ' . $hora);
        }

        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }

    protected function ensureLoaded()
    {
        if ($this->schedule !== null) {
            return;
        }

        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached) && isset($cached['schedule'], $cached['timezone'])) {
            $this->schedule = $cached['schedule'];
            $this->timezone = $cached['timezone'];

            return;
        }

        $schedule = array();
        $timezone = self::TZ_DEFAULT;
        $rows = SoporteTiHorarioAtencion::query()->orderBy('dia_semana')->get();
        if ($rows->isEmpty()) {
            for ($d = 1; $d <= 7; $d++) {
                $schedule[$d] = array(
                    'activo' => $d <= 5,
                    'inicio' => '09:00:00',
                    'fin' => '18:00:00',
                );
            }
        } else {
            foreach ($rows as $row) {
                $dia = (int) $row->dia_semana;
                $schedule[$dia] = array(
                    'activo' => (bool) $row->activo,
                    'inicio' => $this->normalizarHora($row->hora_inicio),
                    'fin' => $this->normalizarHora($row->hora_fin),
                );
                if ($row->timezone) {
                    $timezone = (string) $row->timezone;
                }
            }
            for ($d = 1; $d <= 7; $d++) {
                if (!isset($schedule[$d])) {
                    $schedule[$d] = array(
                        'activo' => false,
                        'inicio' => '09:00:00',
                        'fin' => '18:00:00',
                    );
                }
            }
        }

        $this->schedule = $schedule;
        $this->timezone = $timezone;
        Cache::put(self::CACHE_KEY, array(
            'schedule' => $schedule,
            'timezone' => $timezone,
        ), self::CACHE_TTL);
    }

    /**
     * @param Carbon $dia
     * @return array{inicio: string, fin: string}|null
     */
    protected function ventanaDelDia(Carbon $dia)
    {
        $this->ensureLoaded();
        $iso = (int) $dia->dayOfWeekIso;
        $cfg = isset($this->schedule[$iso]) ? $this->schedule[$iso] : null;
        if (!$cfg || empty($cfg['activo'])) {
            return null;
        }

        return array(
            'inicio' => $cfg['inicio'],
            'fin' => $cfg['fin'],
        );
    }

    /**
     * @return bool
     */
    protected function tieneAlgunaVentanaActiva()
    {
        $this->ensureLoaded();
        foreach ($this->schedule as $cfg) {
            if (!empty($cfg['activo'])) {
                return true;
            }
        }

        return false;
    }
}
