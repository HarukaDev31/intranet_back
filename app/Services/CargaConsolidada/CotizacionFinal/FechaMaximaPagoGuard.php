<?php

namespace App\Services\CargaConsolidada\CotizacionFinal;

use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\Cotizacion;
use Carbon\Carbon;

class FechaMaximaPagoGuard
{
    const CODE = 'FECHA_MAXIMA_PAGO_REQUIRED';
    const MESSAGE = 'Define la fecha máxima de pago antes de cambiar el estado o enviar WhatsApp.';

    public static function isSet($value): bool
    {
        return self::toCarbon($value) !== null;
    }

    public static function toCarbon($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            if ((int) $value->year < 1971) {
                return null;
            }

            return $value->copy()->startOfDay();
        }

        $asString = trim((string) $value);
        if ($asString === '' || strpos($asString, '0000-00-00') === 0) {
            return null;
        }

        try {
            $dt = Carbon::parse($asString);
            if ((int) $dt->year < 1971) {
                return null;
            }

            return $dt->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function toIso($value)
    {
        $dt = self::toCarbon($value);

        return $dt ? $dt->format('Y-m-d') : null;
    }

    public static function toDisplay($value)
    {
        $dt = self::toCarbon($value);

        return $dt ? $dt->format('d/m/Y') : null;
    }

    /**
     * @return array{ok:bool,message?:string,not_found?:bool,contenedor?:Contenedor,fecha?:Carbon}
     */
    public static function forCotizacion(int $idCotizacion): array
    {
        $cotizacion = Cotizacion::query()->whereKey($idCotizacion)->first();
        if (!$cotizacion instanceof Cotizacion) {
            return [
                'ok' => false,
                'not_found' => true,
                'message' => 'Cotización no encontrada',
            ];
        }

        return self::forContenedorId((int) $cotizacion->getAttribute('id_contenedor'));
    }

    /**
     * @return array{ok:bool,message?:string,not_found?:bool,contenedor?:Contenedor,fecha?:Carbon}
     */
    public static function forContenedorId(int $idContenedor): array
    {
        $contenedor = Contenedor::query()
            ->select('id', 'fecha_maxima_pago', 'carga')
            ->whereKey($idContenedor)
            ->first();

        if (!$contenedor instanceof Contenedor) {
            return [
                'ok' => false,
                'not_found' => true,
                'message' => 'Contenedor no encontrado',
            ];
        }

        $fecha = self::toCarbon($contenedor->getAttribute('fecha_maxima_pago'));
        if ($fecha === null) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => self::MESSAGE,
                'contenedor' => $contenedor,
            ];
        }

        return [
            'ok' => true,
            'contenedor' => $contenedor,
            'fecha' => $fecha,
        ];
    }
}
