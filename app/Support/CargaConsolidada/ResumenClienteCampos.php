<?php

namespace App\Support\CargaConsolidada;

/**
 * Limpia nombre, ID/RUC, WhatsApp y correo extraídos o enviados en cotización resumen.
 * Evita que un teléfono llene el ID y que el literal "null" de la IA llene el correo.
 */
class ResumenClienteCampos
{
    /** @var string[] */
    private static $rucsEmisor = ['20612452432', '206124524321'];

    /**
     * @param mixed $raw
     * @return string|null
     */
    public static function textoONulo($raw)
    {
        if ($raw === null || is_bool($raw)) {
            return null;
        }
        $texto = trim((string) $raw);
        if ($texto === '') {
            return null;
        }
        if (preg_match('/^(null|none|undefined|n\/a|na|-)$/i', $texto)) {
            return null;
        }

        return $texto;
    }

    /**
     * @param mixed $raw
     * @return string
     */
    public static function soloDigitos($raw)
    {
        return preg_replace('/\D+/', '', (string) self::textoONulo($raw));
    }

    /**
     * @param mixed $raw
     * @return string|null
     */
    public static function correo($raw)
    {
        $texto = self::textoONulo($raw);
        if ($texto === null) {
            return null;
        }
        if (!filter_var($texto, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $texto;
    }

    /**
     * @param mixed $raw
     * @return string|null
     */
    public static function whatsapp($raw)
    {
        $texto = self::textoONulo($raw);
        if ($texto === null) {
            return null;
        }
        $digitos = self::soloDigitos($texto);
        if (strlen($digitos) < 7) {
            return null;
        }

        return $texto;
    }

    /**
     * @param string $digitos
     * @param string $whatsappDigitos
     * @return bool
     */
    public static function pareceTelefono($digitos, $whatsappDigitos = '')
    {
        $digitos = (string) $digitos;
        if ($digitos === '') {
            return false;
        }
        if ($whatsappDigitos !== '' && $digitos === (string) $whatsappDigitos) {
            return true;
        }
        if (preg_match('/^(593|591|595|598|505|506|502|504|507|51|52|54|56|57|58)\d{7,12}$/', $digitos)) {
            return true;
        }
        if (preg_match('/^9\d{8}$/', $digitos)) {
            return true;
        }
        if (preg_match('/^09\d{8}$/', $digitos)) {
            return true;
        }

        return false;
    }

    /**
     * @param mixed $raw
     * @param mixed $whatsapp
     * @return string|null
     */
    public static function documentoIdentidad($raw, $whatsapp = null)
    {
        $texto = self::textoONulo($raw);
        if ($texto === null) {
            return null;
        }
        if (preg_match('/cotiz|boleta|factura|proforma|n[uú]mero\s*de\s*doc/i', $texto)) {
            return null;
        }

        $digitos = self::soloDigitos($texto);
        if ($digitos === '') {
            return null;
        }
        if (in_array($digitos, self::$rucsEmisor, true)) {
            return null;
        }
        if (self::pareceTelefono($digitos, self::soloDigitos($whatsapp))) {
            return null;
        }

        $len = strlen($digitos);
        if ($len < 6 || $len > 13) {
            return null;
        }

        return $digitos;
    }

    /**
     * @param  array<string, mixed>  $cliente
     * @param  bool  $conservarId  true al guardar (id de cliente de la org); false al extraer con IA
     * @return array{id?: int|null, nombre: string|null, documento: string|null, tipo_documento: string|null, whatsapp: string|null, correo: string|null}
     */
    public static function sanitizar(array $cliente, $conservarId = false)
    {
        $id = null;
        if ($conservarId && !empty($cliente['id']) && is_numeric($cliente['id']) && (int) $cliente['id'] > 0) {
            $id = (int) $cliente['id'];
        }

        $whatsapp = self::whatsapp(isset($cliente['whatsapp']) ? $cliente['whatsapp'] : null);
        $documentoRaw = isset($cliente['documento']) ? $cliente['documento'] : null;
        $docDigitos = self::soloDigitos($documentoRaw);

        if ($whatsapp === null && self::pareceTelefono($docDigitos)) {
            $whatsapp = self::textoONulo($documentoRaw);
        }

        $documento = self::documentoIdentidad($documentoRaw, $whatsapp);
        $tipo = null;
        if ($documento !== null) {
            $tipo = strlen(self::soloDigitos($documento)) >= 11 ? 'RUC' : 'ID';
        }

        return [
            'id' => $id,
            'nombre' => self::textoONulo(isset($cliente['nombre']) ? $cliente['nombre'] : null),
            'documento' => $documento,
            'tipo_documento' => $tipo,
            'whatsapp' => $whatsapp,
            'correo' => self::correo(isset($cliente['correo']) ? $cliente['correo'] : null),
        ];
    }
}
