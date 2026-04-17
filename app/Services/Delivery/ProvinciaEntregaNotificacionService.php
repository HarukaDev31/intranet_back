<?php

namespace App\Services\Delivery;

use App\Models\CargaConsolidada\ConsolidadoDeliveryFormProvince;
use App\Models\User;

/**
 * Arma el texto de WhatsApp y los datos de vista para el correo de confirmación
 * de entrega a provincia (una sola fuente de verdad).
 */
class ProvinciaEntregaNotificacionService
{
    /**
     * @param ConsolidadoDeliveryFormProvince $form
     * @param string $carga Número o código de carga del consolidado
     * @param User|null $user Usuario que registró el envío (saludo)
     * @return array{
     *   whatsapp: string,
     *   tipoDocumento: string,
     *   nombreDestinatario: string,
     *   numeroDocumento: string,
     *   celularDestinatario: string,
     *   nombreAgencia: string,
     *   rucAgencia: string,
     *   destinoLinea: string,
     *   entregaEn: string,
     *   direccionEntrega: string|null,
     *   primerNombre: string
     * }
     */
    public static function datosVistaCorreo($form, $carga, $user)
    {
        $cargaStr = (string) $carga;
        $domicilio = self::esEntregaDomicilio($form);
        $dir = $domicilio ? trim((string) $form->home_adress_delivery) : null;
        if ($dir === '') {
            $dir = null;
        }

        $base = [
            'tipoDocumento' => self::etiquetaDocumento($form),
            'nombreDestinatario' => self::nonEmptyOrDash((string) $form->r_name),
            'numeroDocumento' => self::nonEmptyOrDash((string) $form->r_doc),
            'celularDestinatario' => self::nonEmptyOrDash((string) $form->r_phone),
            'nombreAgencia' => self::nombreAgencia($form),
            'rucAgencia' => self::rucAgencia($form),
            'destinoLinea' => self::lineaDestino($form),
            'entregaEn' => $domicilio ? 'Domicilio' : 'Agencia',
            'direccionEntrega' => $dir,
            'primerNombre' => self::primerNombre($user, $form),
        ];

        $base['whatsapp'] = self::armarTextoWhatsapp($cargaStr, $base);

        return $base;
    }

    /**
     * @param ConsolidadoDeliveryFormProvince $form
     * @param string $carga
     * @param User|null $user
     * @return string
     */
    public static function buildWhatsAppMessage($form, $carga, $user)
    {
        return self::datosVistaCorreo($form, $carga, $user)['whatsapp'];
    }

    /**
     * @param string $carga
     * @param array $d Datos de datosVistaCorreo sin clave whatsapp
     * @return string
     */
    private static function armarTextoWhatsapp($carga, array $d)
    {
        $primer = $d['primerNombre'];
        $nombre = $d['nombreDestinatario'];
        $docLabel = $d['tipoDocumento'];
        $docValor = $d['numeroDocumento'];
        $cel = $d['celularDestinatario'];
        $agencia = $d['nombreAgencia'];
        $ruc = $d['rucAgencia'];
        $destino = $d['destinoLinea'];
        $entrega = $d['entregaEn'];
        $consolidado = 'Consolidado #' . $carga;

        $lines = [
            '✅ *Envío registrado*',
            '',
            'Hola, *' . $primer . '* 👋',
            '',
            'Tu solicitud de envío para el *' . $consolidado . '* fue registrada correctamente.',
            '',
            '📦 *DESTINATARIO*',
            '*Nombre:* ' . $nombre,
            '*' . $docLabel . ':* ' . $docValor,
            '*Celular:* ' . $cel,
            '',
            '🚚 *TRANSPORTE*',
            '*Agencia:* ' . $agencia,
            '*RUC:* ' . $ruc,
            '*Destino:* ' . $destino,
            '*Entrega en:* ' . $entrega,
        ];

        if ($d['direccionEntrega'] !== null) {
            $lines[] = '*Dirección:* ' . $d['direccionEntrega'];
        }

        return implode("\n", $lines);
    }

    /**
     * @param User|null $user
     * @param ConsolidadoDeliveryFormProvince $form
     * @return string
     */
    public static function primerNombre($user, $form)
    {
        $full = '';
        if ($user) {
            $full = trim(trim((string) $user->name) . ' ' . trim((string) $user->lastname));
        }
        if ($full === '') {
            $full = trim((string) $form->r_name);
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
     * @param ConsolidadoDeliveryFormProvince $form
     * @return bool
     */
    public static function esEntregaDomicilio($form)
    {
        return trim((string) $form->home_adress_delivery) !== '';
    }

    /**
     * @param ConsolidadoDeliveryFormProvince $form
     * @return string
     */
    public static function lineaDestino($form)
    {
        $parts = [];
        if ($form->departamento) {
            $parts[] = (string) $form->departamento->No_Departamento;
        }
        if ($form->provincia) {
            $parts[] = (string) $form->provincia->No_Provincia;
        }
        if ($form->distrito) {
            $parts[] = (string) $form->distrito->No_Distrito;
        }
        $line = implode(' — ', $parts);

        return $line !== '' ? $line : '—';
    }

    /**
     * Agencia propia (id 3): nombre y RUC libres del formulario.
     *
     * @param ConsolidadoDeliveryFormProvince $form
     * @return string
     */
    public static function nombreAgencia($form)
    {
        if ((int) $form->id_agency === 3) {
            return self::nonEmptyOrDash((string) $form->agency_name);
        }
        if ($form->agency) {
            return self::nonEmptyOrDash((string) $form->agency->name);
        }

        return '—';
    }

    /**
     * @param ConsolidadoDeliveryFormProvince $form
     * @return string
     */
    public static function rucAgencia($form)
    {
        if ((int) $form->id_agency === 3) {
            return self::nonEmptyOrDash((string) $form->agency_ruc);
        }
        if ($form->agency) {
            return self::nonEmptyOrDash((string) $form->agency->ruc);
        }

        return '—';
    }

    /**
     * @param ConsolidadoDeliveryFormProvince $form
     * @return string
     */
    public static function etiquetaDocumento($form)
    {
        return $form->r_type === 'PERSONA NATURAL' ? 'DNI' : 'RUC';
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
}
