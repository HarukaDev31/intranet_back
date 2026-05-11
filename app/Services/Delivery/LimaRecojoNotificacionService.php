<?php

namespace App\Services\Delivery;

use App\Models\CargaConsolidada\ConsolidadoDeliveryFormLima;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Arma el texto de WhatsApp y los datos de vista para el correo de confirmación
 * de recojo en almacén Lima (una sola fuente de verdad).
 */
class LimaRecojoNotificacionService
{
    /**
     * URL pública de Google Maps del almacén Lima.
     */
    const MAPS_URL = 'https://maps.app.goo.gl/5raLmkvX65nNHB2Fr9';

    /**
     * Dirección del almacén Lima (línea principal).
     */
    const DIRECCION = 'Calle Rio Nazca 243 - San Luis';

    /**
     * Referencia de la dirección del almacén Lima.
     */
    const REFERENCIA = 'Ref. Al costado de la Agencia Antezana';

    /**
     * @param ConsolidadoDeliveryFormLima $form
     * @param string|int $carga Número o código de carga del consolidado
     * @param User|null $user Usuario que registró el recojo (saludo)
     * @return array{
     *   whatsapp: string,
     *   primerNombre: string,
     *   carga: string,
     *   pickName: string,
     *   pickDoc: string,
     *   pickPhone: string,
     *   fechaTextual: string,
     *   fechaRecojo: string,
     *   horaInicio: string,
     *   horaFin: string,
     *   horaRecojo: string,
     *   direccion: string,
     *   referencia: string,
     *   mapsUrl: string
     * }
     */
    public static function datosVistaCorreo($form, $carga, $user)
    {
        $cargaStr = (string) $carga;
        $rango = self::obtenerRangoFecha($form);

        $base = [
            'primerNombre' => self::primerNombre($user, $form),
            'carga' => $cargaStr,
            'pickName' => self::nonEmptyOrDash((string) $form->pick_name),
            'pickDoc' => self::nonEmptyOrDash((string) $form->pick_doc),
            'pickPhone' => self::formatearTelefonoVisual((string) $form->pick_phone),
            'fechaTextual' => self::fechaTextual($rango),
            'fechaRecojo' => self::fechaCorta($rango),
            'horaInicio' => self::horaSinSegundos($rango ? $rango->start_time : null),
            'horaFin' => self::horaSinSegundos($rango ? $rango->end_time : null),
            'direccion' => self::DIRECCION,
            'referencia' => self::REFERENCIA,
            'mapsUrl' => self::MAPS_URL,
        ];

        $base['horaRecojo'] = ($base['horaInicio'] !== '' && $base['horaFin'] !== '')
            ? $base['horaInicio'] . ' - ' . $base['horaFin']
            : 'Hora no especificada';

        $base['whatsapp'] = self::armarTextoWhatsapp($base);

        return $base;
    }

    /**
     * Atajo para obtener solo el mensaje de WhatsApp.
     *
     * @param ConsolidadoDeliveryFormLima $form
     * @param string|int $carga
     * @param User|null $user
     * @return string
     */
    public static function buildWhatsAppMessage($form, $carga, $user)
    {
        return self::datosVistaCorreo($form, $carga, $user)['whatsapp'];
    }

    /**
     * @param array $d Datos retornados por datosVistaCorreo (sin la clave whatsapp).
     * @return string
     */
    private static function armarTextoWhatsapp(array $d)
    {
        $primer = $d['primerNombre'];
        $consolidado = 'Consolidado #' . $d['carga'];
        $fechaHora = trim($d['fechaTextual'] . ' - ' . $d['horaRecojo'] . ' hrs', ' -');

        $lines = [
            'Hola, *' . $primer . '* 👋',
            '',
            'Tu recojo del "*' . $consolidado . '*" ha sido registrado. Aquí el resumen:',
            '',
            '👤 *DESTINATARIO*',
            $d['pickName'],
            '*DNI:* ' . $d['pickDoc'],
            '*Cel.:* ' . $d['pickPhone'],
            '',
            '📅 *FECHA Y HORA DE RECOJO*',
            $fechaHora,
            '',
            '📍 *DIRECCIÓN DE RECOJO*',
            $d['direccion'],
            $d['referencia'],
            '',
            '🔗 *Ver en Google Maps*',
            $d['mapsUrl'],
            '',
            'Gracias por confiar en *Pro Business* 🤝',
        ];

        return implode("\n", $lines);
    }

    /**
     * Obtiene el rango de fecha/hora asociado al formulario.
     *
     * @param ConsolidadoDeliveryFormLima $form
     * @return object|null Objeto con day, month, year, start_time, end_time o null.
     */
    private static function obtenerRangoFecha($form)
    {
        if (!$form || empty($form->id_range_date)) {
            return null;
        }

        return DB::table('consolidado_delivery_range_date as r')
            ->join('consolidado_delivery_date as d', 'r.id_date', '=', 'd.id')
            ->where('r.id', $form->id_range_date)
            ->select('d.day', 'd.month', 'd.year', 'r.start_time', 'r.end_time')
            ->first();
    }

    /**
     * Formatea la fecha como "Miércoles 23 de abril" (locale es).
     *
     * @param object|null $rango
     * @return string
     */
    private static function fechaTextual($rango)
    {
        if (!$rango) {
            return 'Fecha no especificada';
        }
        try {
            $fecha = Carbon::create((int) $rango->year, (int) $rango->month, (int) $rango->day);
            $texto = $fecha->locale('es')->isoFormat('dddd D [de] MMMM');

            return self::ucfirstUtf8($texto);
        } catch (\Exception $e) {
            return self::fechaCorta($rango);
        }
    }

    /**
     * Formatea la fecha como "DD/MM/YYYY" (compatibilidad).
     *
     * @param object|null $rango
     * @return string
     */
    private static function fechaCorta($rango)
    {
        if (!$rango) {
            return 'Fecha no especificada';
        }

        return sprintf('%02d/%02d/%04d', (int) $rango->day, (int) $rango->month, (int) $rango->year);
    }

    /**
     * Devuelve la hora truncada a HH:MM.
     *
     * @param string|null $hora
     * @return string
     */
    private static function horaSinSegundos($hora)
    {
        if (!is_string($hora) || $hora === '') {
            return '';
        }

        return substr($hora, 0, 5);
    }

    /**
     * Aplica un formato visual al teléfono para mostrar en mensajes (no para envío).
     * Ej: "987654321" -> "987 654 321".
     *
     * @param string $phone
     * @return string
     */
    private static function formatearTelefonoVisual($phone)
    {
        $limpio = preg_replace('/[^0-9+]/', '', (string) $phone);
        if ($limpio === '' || $limpio === null) {
            return '—';
        }
        if (preg_match('/^\d{9}$/', $limpio)) {
            return substr($limpio, 0, 3) . ' ' . substr($limpio, 3, 3) . ' ' . substr($limpio, 6, 3);
        }

        return $limpio;
    }

    /**
     * Obtiene el primer nombre del usuario (o de la persona que recoge si no hay usuario).
     *
     * @param User|null $user
     * @param ConsolidadoDeliveryFormLima $form
     * @return string
     */
    public static function primerNombre($user, $form)
    {
        $full = '';
        if ($user) {
            $full = trim(trim((string) $user->name) . ' ' . trim((string) $user->lastname));
        }
        if ($full === '') {
            $full = trim((string) $form->pick_name);
        }
        if ($full === '') {
            return 'Cliente';
        }
        $parts = preg_split('/\s+/u', $full, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts) || count($parts) === 0) {
            return 'Cliente';
        }

        return $parts[0];
    }

    /**
     * @param string $value
     * @return string
     */
    private static function nonEmptyOrDash($value)
    {
        $t = trim($value);

        return $t !== '' ? $t : '—';
    }

    /**
     * ucfirst seguro para UTF-8 (para fechas con acentos: "miércoles" -> "Miércoles").
     *
     * @param string $text
     * @return string
     */
    private static function ucfirstUtf8($text)
    {
        if ($text === '') {
            return $text;
        }
        $first = mb_substr($text, 0, 1, 'UTF-8');
        $rest = mb_substr($text, 1, null, 'UTF-8');

        return mb_strtoupper($first, 'UTF-8') . $rest;
    }
}
