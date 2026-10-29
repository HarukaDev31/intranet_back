<?php

namespace App\Services\PagoProveedor;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * 48 horas hábiles: horas corridas que saltan sábado y domingo (RN-21).
 * Los feriados no se descuentan (PV-17). El calendario es el de Lima.
 */
class PagoProveedorVigenciaService
{
    public const ZONA = 'America/Lima';

    public function sumarHorasHabiles(DateTimeInterface $inicio, int $horas): Carbon
    {
        $cursor = Carbon::parse($inicio)->timezone(self::ZONA);
        $restantes = max(0, $horas) * 60;

        while ($restantes > 0) {
            if ($cursor->isWeekend()) {
                $cursor = $cursor->next(Carbon::MONDAY)->startOfDay();
                continue;
            }

            $medianoche = $cursor->copy()->addDay()->startOfDay();
            $minutosDelDia = intdiv($medianoche->getTimestamp() - $cursor->getTimestamp(), 60);

            if ($minutosDelDia >= $restantes) {
                return $cursor->copy()->addMinutes($restantes);
            }

            $restantes -= $minutosDelDia;
            $cursor = $medianoche;
        }

        return $cursor;
    }

    public function texto(DateTimeInterface $fin, ?DateTimeInterface $ahora = null): string
    {
        $limite = Carbon::parse($fin)->timezone(self::ZONA);
        $referencia = $ahora ? Carbon::parse($ahora)->timezone(self::ZONA) : Carbon::now(self::ZONA);

        if ($referencia->lessThan($limite)) {
            return 'Vence en ' . $this->humano($referencia, $limite);
        }

        return 'Vencida hace ' . $this->humano($limite, $referencia);
    }

    public function estaVencida(DateTimeInterface $fin, ?DateTimeInterface $ahora = null): bool
    {
        $limite = Carbon::parse($fin)->timezone(self::ZONA);
        $referencia = $ahora ? Carbon::parse($ahora)->timezone(self::ZONA) : Carbon::now(self::ZONA);

        return $referencia->greaterThanOrEqualTo($limite);
    }

    private function humano(Carbon $desde, Carbon $hasta): string
    {
        $minutos = max(0, intdiv($hasta->getTimestamp() - $desde->getTimestamp(), 60));
        $dias = intdiv($minutos, 1440);
        $horas = intdiv($minutos % 1440, 60);
        $resto = $minutos % 60;

        $partes = [];
        if ($dias > 0) {
            $partes[] = $dias . ($dias === 1 ? ' día' : ' días');
        }
        if ($horas > 0) {
            $partes[] = $horas . ($horas === 1 ? ' hora' : ' horas');
        }
        if ($resto > 0 && $dias === 0) {
            $partes[] = $resto . ' min';
        }
        if ($partes === []) {
            return 'menos de 1 min';
        }

        return implode(' ', $partes);
    }
}
