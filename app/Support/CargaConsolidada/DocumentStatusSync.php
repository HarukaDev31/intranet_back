<?php

namespace App\Support\CargaConsolidada;

use App\Models\CargaConsolidada\CotizacionProveedor;

/**
 * Coord 2 → invoice/packing/excel_conf_status.
 * Resto (VB final) → *_status_final.
 * Cuando Coord 2 pasa a Revisado, el final pasa a Recibido (si aún no está Revisado).
 */
class DocumentStatusSync
{
    /** @var array<string, string> */
    public const COORD_TO_FINAL = [
        'invoice_status' => 'invoice_status_final',
        'packing_status' => 'packing_status_final',
        'excel_conf_status' => 'excel_conf_status_final',
    ];

    /** @var string[] */
    public const ALLOWED = ['Pendiente', 'Recibido', 'Observado', 'Revisado'];

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
            $proveedor->{$finalField} = 'Recibido';
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
     * Aplica cambio de estado Coord 2 y, si pasa a Revisado, sincroniza el final a Recibido.
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
                $proveedor->{$finalField} = 'Recibido';
            }
        }

        if ($coordField === 'excel_conf_status') {
            $proveedor->excel_conf_form_cerrado = $newValue === 'Revisado';
        }

        return $coordField === 'excel_conf_status' && $becomesRevisado;
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
}
