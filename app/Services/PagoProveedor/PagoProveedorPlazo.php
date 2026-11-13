<?php

namespace App\Services\PagoProveedor;

use Carbon\Carbon;

class PagoProveedorPlazo
{
    /**
     * Texto corto de cuenta regresiva (p. ej. «quedan 42 h», «quedan 37 h 59 min»).
     *
     * @param  \Carbon\Carbon|string|null  $until
     */
    public static function remainingText($until, $suffix = '')
    {
        if ($until === null || $until === '') {
            return null;
        }
        $deadline = $until instanceof Carbon ? $until : Carbon::parse($until);
        $remaining = (int) now()->diffInMinutes($deadline, false);
        if ($remaining < 0) {
            return 'plazo vencido';
        }
        if ($remaining < 60) {
            $base = 'quedan '.$remaining.' min';
        } else {
            $hours = intdiv($remaining, 60);
            $mins = $remaining % 60;
            $base = $mins > 0
                ? 'quedan '.$hours.' h '.$mins.' min'
                : 'quedan '.$hours.' h';
        }

        return $suffix !== '' ? $base.' '.$suffix : $base;
    }
}
