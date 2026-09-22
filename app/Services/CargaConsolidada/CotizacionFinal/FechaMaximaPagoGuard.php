<?php

namespace App\Services\CargaConsolidada\CotizacionFinal;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
     * @return array{ok:bool,message?:string,not_found?:bool,contenedor?:object,fecha?:Carbon}
     */
    public static function forCotizacion(int $idCotizacion): array
    {
        $cotizacion = DB::table('contenedor_consolidado_cotizacion')
            ->select('id', 'id_contenedor')
            ->where('id', $idCotizacion)
            ->first();

        if (!$cotizacion) {
            $asContenedor = self::contenedorRow($idCotizacion);
            if ($asContenedor) {
                return [
                    'ok' => false,
                    'not_found' => true,
                    'message' => 'El recordatorio usa el id de la cotización del cliente, no el del contenedor.',
                ];
            }

            return [
                'ok' => false,
                'not_found' => true,
                'message' => 'Cotización no encontrada',
            ];
        }

        return self::forContenedorId((int) $cotizacion->id_contenedor);
    }

    /**
     * @return array{ok:bool,message?:string,not_found?:bool,contenedor?:object,fecha?:Carbon}
     */
    public static function forContenedorId(int $idContenedor): array
    {
        $contenedor = self::contenedorRow($idContenedor);

        if (!$contenedor) {
            return [
                'ok' => false,
                'not_found' => true,
                'message' => 'Contenedor no encontrado',
            ];
        }

        $fecha = self::toCarbon(isset($contenedor->fecha_maxima_pago) ? $contenedor->fecha_maxima_pago : null);
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

    /**
     * Fila cruda del contenedor (sin casts Eloquent ni soft deletes).
     *
     * @return object|null
     */
    public static function contenedorRow(int $idContenedor)
    {
        $query = DB::table('carga_consolidada_contenedor')
            ->select('id', 'carga', 'fecha_arribo')
            ->where('id', $idContenedor);

        if (self::contenedorHasFechaColumn()) {
            $query->addSelect('fecha_maxima_pago');
        }

        return $query->first();
    }

    private static function contenedorHasFechaColumn(): bool
    {
        static $has = null;
        if ($has === null) {
            $has = Schema::hasColumn('carga_consolidada_contenedor', 'fecha_maxima_pago');
        }

        return (bool) $has;
    }
}
