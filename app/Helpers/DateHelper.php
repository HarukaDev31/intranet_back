<?php

namespace App\Helpers;

class DateHelper
{
    /**
     * Formatea una fecha según el formato especificado
     * 
     * @param string|null $date Fecha a formatear
     * @param string $separator Separador para el formato (por defecto '-')
     * @param int $format Tipo de formato (0: dd-mm-yyyy, 1: yyyy-mm-dd, 2: dd/mm/yyyy)
     * @return string|null Fecha formateada o null si no hay fecha
     */
    public static function formatDate($date, $separator = '-', $format = 0)
    {
        if (empty($date)) {
            return null;
        }

        // Si es un objeto Carbon o DateTime, convertirlo a string
        if ($date instanceof \Carbon\Carbon || $date instanceof \DateTime) {
            $date = $date->format('Y-m-d');
        }

        // Si ya es un string, intentar parsearlo
        if (is_string($date)) {
            try {
                $carbon = \Carbon\Carbon::parse($date);
            } catch (\Exception $e) {
                return $date; // Si no se puede parsear, devolver el valor original
            }
        } else {
            return null;
        }

        switch ($format) {
            case 0: // dd-mm-yyyy
                return $carbon->format('d' . $separator . 'm' . $separator . 'Y');
            case 1: // yyyy-mm-dd
                return $carbon->format('Y' . $separator . 'm' . $separator . 'd');
            case 2: // dd/mm/yyyy
                return $carbon->format('d/m/Y');
            default:
                return $carbon->format('d' . $separator . 'm' . $separator . 'Y');
        }
    }

    /**
     * Formatea una fecha y hora
     * 
     * @param string|null $dateTime Fecha y hora a formatear
     * @param string $separator Separador para la fecha (por defecto '-')
     * @return string|null Fecha y hora formateada o null si no hay fecha
     */
    public static function formatDateTime($dateTime, $separator = '-')
    {
        if (empty($dateTime)) {
            return null;
        }

        try {
            $carbon = \Carbon\Carbon::parse($dateTime);
            return $carbon->format('d' . $separator . 'm' . $separator . 'Y H:i:s');
        } catch (\Exception $e) {
            return $dateTime;
        }
    }

    /**
     * Obtiene la edad a partir de una fecha de nacimiento
     * 
     * @param string|null $birthDate Fecha de nacimiento
     * @return int|null Edad calculada o null si no hay fecha
     */
    public static function calculateAge($birthDate)
    {
        if (empty($birthDate)) {
            return null;
        }

        try {
            $carbon = \Carbon\Carbon::parse($birthDate);
            return $carbon->age;
        } catch (\Exception $e) {
            return null;
        }
    }
} 