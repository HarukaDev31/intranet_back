<?php

namespace App\Support\CargaConsolidada;

use App\Models\CargaConsolidada\CotizacionProveedor;

/**
 * Coord 2 (Daniela) → invoice/packing/excel_conf_status.
 * Coord 3 (José) y resto (VB final) → *_status_final.
 * Cuando Coord 2 pasa a Revisado, el final pasa a Recibido/Entregado (si aún no está Revisado).
 */
class DocumentStatusSync
{
    const COORD2_EMAIL = 'coordinacion2@probusiness.pe';
    const COORD3_EMAIL = 'coordinacion3@probusiness.pe';

    /** @var array<string, string> */
    public const COORD_TO_FINAL = [
        'invoice_status' => 'invoice_status_final',
        'packing_status' => 'packing_status_final',
        'excel_conf_status' => 'excel_conf_status_final',
    ];

    /** @var string[] */
    public const ALLOWED = ['Pendiente', 'Solicitado', 'Entregado', 'Observado', 'Revisado'];

    /**
     * @param  mixed  $user
     */
    public static function isCoord2User($user)
    {
        return self::matchesEmailPrefix($user, self::COORD2_EMAIL);
    }

    /**
     * @param  mixed  $user
     */
    public static function isCoord3User($user)
    {
        return self::matchesEmailPrefix($user, self::COORD3_EMAIL);
    }

    /**
     * @param  mixed  $user
     */
    private static function matchesEmailPrefix($user, string $targetEmail)
    {
        if ($user === null) {
            return false;
        }

        $candidates = [
            is_object($user) ? ($user->Txt_Email ?? null) : null,
            is_object($user) ? ($user->No_Usuario ?? null) : null,
            is_object($user) ? ($user->email ?? null) : null,
            is_array($user) ? ($user['Txt_Email'] ?? null) : null,
            is_array($user) ? ($user['No_Usuario'] ?? null) : null,
            is_array($user) ? ($user['email'] ?? null) : null,
        ];

        $prefix = strtolower(substr($targetEmail, 0, strpos($targetEmail, '@') + 1));

        foreach ($candidates as $candidate) {
            $email = strtolower(trim((string) $candidate));
            if ($email === '') {
                continue;
            }
            if ($email === $targetEmail || strpos($email, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  CotizacionProveedor|object  $proveedor
     */
    public static function markCoord2Revisado($proveedor, string $coordField)
    {
        if (!isset(self::COORD_TO_FINAL[$coordField])) {
            return;
        }

        $finalField = self::COORD_TO_FINAL[$coordField];
        $proveedor->{$coordField} = 'Revisado';

        $currentFinal = (string) ($proveedor->{$finalField} ?? 'Pendiente');
        if (strcasecmp($currentFinal, 'Revisado') !== 0) {
            $proveedor->{$finalField} = 'Entregado';
        }
    }

    /**
     * @param  CotizacionProveedor|object  $proveedor
     */
    public static function resetCoord2Pendiente($proveedor, string $coordField, $resetFinalIfNotRevisado = true)
    {
        if (!isset(self::COORD_TO_FINAL[$coordField])) {
            return;
        }

        $finalField = self::COORD_TO_FINAL[$coordField];
        $proveedor->{$coordField} = 'Pendiente';

        if (!$resetFinalIfNotRevisado) {
            return;
        }

        $currentFinal = (string) ($proveedor->{$finalField} ?? 'Pendiente');
        if (strcasecmp($currentFinal, 'Revisado') !== 0) {
            $proveedor->{$finalField} = 'Pendiente';
        }
    }

    /**
     * Aplica cambio de estado Coord 2 (Daniela) y, si pasa a Revisado, sincroniza el
     * campo final (Coord 3/José y resto). Para Excel Conf. el final pasa a Entregado;
     * para Invoice/Packing el final pasa a Entregado también (equivalente al antiguo "Recibido").
     *
     * @param  CotizacionProveedor  $proveedor
     * @return bool true si excel_conf_status acaba de pasar a Revisado
     */
    public static function applyCoord2Status(CotizacionProveedor $proveedor, string $coordField, string $newValue)
    {
        if (!isset(self::COORD_TO_FINAL[$coordField]) || !in_array($newValue, self::ALLOWED, true)) {
            return false;
        }

        $finalField = self::COORD_TO_FINAL[$coordField];
        $wasRevisado = strcasecmp((string) $proveedor->{$coordField}, 'Revisado') === 0;
        $becomesRevisado = $newValue === 'Revisado' && !$wasRevisado;

        $proveedor->{$coordField} = $newValue;

        if ($becomesRevisado) {
            $currentFinal = (string) ($proveedor->{$finalField} ?? 'Pendiente');
            if (strcasecmp($currentFinal, 'Revisado') !== 0) {
                $proveedor->{$finalField} = 'Entregado';
            }
        }

        if ($coordField === 'excel_conf_status') {
            $proveedor->excel_conf_form_cerrado = $newValue === 'Revisado';
        }

        return $coordField === 'excel_conf_status' && $becomesRevisado;
    }

    /**
     * Marca Invoice/Packing/Excel Conf. (perfil Daniela) como "Solicitado" para un
     * proveedor puntual, usado cuando se dispara la solicitud de "Pedir Documentos".
     * No toca los campos _final (Coord 3/José).
     *
     * @param  CotizacionProveedor  $proveedor
     */
    public static function markSolicitado(CotizacionProveedor $proveedor)
    {
        foreach (array_keys(self::COORD_TO_FINAL) as $coordField) {
            $current = (string) ($proveedor->{$coordField} ?? 'Pendiente');
            if (strcasecmp($current, 'Pendiente') === 0) {
                $proveedor->{$coordField} = 'Solicitado';
            }
        }
    }

    /**
     * @param  CotizacionProveedor  $proveedor
     */
    public static function applyFinalStatus(CotizacionProveedor $proveedor, string $finalField, string $newValue)
    {
        $allowedFinals = array_values(self::COORD_TO_FINAL);
        if (!in_array($finalField, $allowedFinals, true) || !in_array($newValue, self::ALLOWED, true)) {
            return;
        }

        $proveedor->{$finalField} = $newValue;
    }

    /**
     * Quita del payload todos los campos de estado documental (Coord2 y VB).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function stripStatusFields(array $data)
    {
        foreach (array_keys(self::COORD_TO_FINAL) as $coordField) {
            unset($data[$coordField]);
        }
        foreach (array_values(self::COORD_TO_FINAL) as $finalField) {
            unset($data[$finalField]);
        }
        unset($data['excel_conf_form_cerrado']);

        return $data;
    }
}
