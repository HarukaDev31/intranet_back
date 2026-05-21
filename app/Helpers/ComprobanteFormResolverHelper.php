<?php

namespace App\Helpers;

use App\Models\CargaConsolidada\ComprobanteForm;
use App\Models\UsuarioDatosFacturacion;
use Illuminate\Support\Facades\Log;

/**
 * Centraliza la lógica para resolver los datos de "formulario de comprobante" de una cotización:
 *
     *   - findLatestUsuarioDatosFacturacion: resuelve el cliente via UserLookupHelper; si no hay match
     *     en users, busca id_user en usuario_datos_facturacion por DNI/RUC (UsuarioDatosFacturacionLookupHelper).
 *   - buildSyntheticFromUdf: arma un payload con la misma forma de ComprobanteForm a partir
 *     de un UsuarioDatosFacturacion (para vistas que aceptan cualquiera de las dos fuentes).
 *   - resolveForListing: dada una cotización (y opcionalmente un ComprobanteForm pre-cargado),
 *     devuelve los campos que necesita un listado priorizando el ComprobanteForm persistente
 *     y cayendo al último usuario_datos_facturacion solo si el form no existe.
 */
class ComprobanteFormResolverHelper
{
    /**
     * Última fila de usuario_datos_facturacion del cliente vinculado a la cotización.
     * El id_user se resuelve con UserLookupHelper (correo, teléfono, documento) o, en fallback,
     * UsuarioDatosFacturacionLookupHelper (DNI/RUC en usuario_datos_facturacion).
     *
     * @param object $cotizacion Cualquier objeto con propiedades correo / telefono / documento / id.
     * @return UsuarioDatosFacturacion|null
     */
    public static function findLatestUsuarioDatosFacturacion($cotizacion)
    {
        $idUser = null;

        try {
            $user = UserLookupHelper::findUserByContact(
                $cotizacion->correo ?? null,
                $cotizacion->telefono ?? null,
                $cotizacion->documento ?? null
            );
            if ($user && !empty($user->id)) {
                $idUser = (int) $user->id;
            }
        } catch (\Exception $e) {
            Log::warning('ComprobanteFormResolverHelper::findLatestUsuarioDatosFacturacion - UserLookupHelper falló', [
                'id_cotizacion' => $cotizacion->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        if ($idUser === null) {
            $idUser = UsuarioDatosFacturacionLookupHelper::findIdUserByDniOrRuc(
                $cotizacion->documento ?? null,
                null
            );
        }

        if ($idUser === null || $idUser <= 0) {
            return null;
        }

        return UsuarioDatosFacturacion::query()
            ->where('id_user', $idUser)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Arma un payload con la misma forma que ComprobanteForm a partir de un UsuarioDatosFacturacion.
     * Devuelve null si los datos del UDF son insuficientes para inferir el tipo de comprobante.
     *
     * @param UsuarioDatosFacturacion $udf
     * @param object $cotizacion Objeto con id y, opcionalmente, id_contenedor.
     * @return array|null
     */
    public static function buildSyntheticFromUdf(UsuarioDatosFacturacion $udf, $cotizacion)
    {
        $tipo = self::inferirTipoComprobante($udf);
        if ($tipo === null) {
            return null;
        }

        $destino = in_array($udf->destino, ['Lima', 'Provincia'], true) ? $udf->destino : null;
        $idContenedor = isset($cotizacion->id_contenedor) && $cotizacion->id_contenedor !== null
            ? (int) $cotizacion->id_contenedor
            : null;

        $base = [
            'id' => null,
            'id_cotizacion' => (int) $cotizacion->id,
            'id_contenedor' => $idContenedor,
            'id_user' => $udf->id_user !== null ? (int) $udf->id_user : null,
            'tipo_comprobante' => $tipo,
            'destino_entrega' => $destino,
            'domicilio_fiscal' => null,
            'razon_social' => null,
            'ruc' => null,
            'nombre_completo' => null,
            'dni_carnet' => null,
            'distrito_id' => null,
            'created_at' => $udf->created_at ? $udf->created_at->toIso8601String() : null,
            'updated_at' => null,
            'prefill_from_usuario_datos_facturacion' => true,
        ];

        if ($tipo === 'FACTURA') {
            $base['razon_social'] = $udf->razon_social;
            $base['ruc'] = $udf->ruc !== null && $udf->ruc !== ''
                ? preg_replace('/\D+/', '', (string) $udf->ruc)
                : null;
            $base['domicilio_fiscal'] = $udf->domicilio_fiscal;
        } else {
            $base['nombre_completo'] = $udf->nombre_completo;
            $base['dni_carnet'] = $udf->dni;
        }

        return $base;
    }

    /**
     * El historial usuario_datos_facturacion no guarda tipo explícito; se infiere por campos típicos de FACTURA vs BOLETA.
     *
     * @param UsuarioDatosFacturacion $udf
     * @return string|null FACTURA / BOLETA / null si no se puede inferir.
     */
    public static function inferirTipoComprobante(UsuarioDatosFacturacion $udf)
    {
        $rucDigits = $udf->ruc !== null && $udf->ruc !== ''
            ? preg_replace('/\D/', '', (string) $udf->ruc)
            : '';

        $tieneFactura = ($rucDigits !== '' && strlen($rucDigits) === 11)
            || (is_string($udf->razon_social) && trim($udf->razon_social) !== '')
            || (is_string($udf->domicilio_fiscal) && trim($udf->domicilio_fiscal) !== '');

        $tieneBoleta = (is_string($udf->nombre_completo) && trim($udf->nombre_completo) !== '')
            || (is_string($udf->dni) && trim($udf->dni) !== '');

        if ($tieneFactura && ! $tieneBoleta) {
            return 'FACTURA';
        }
        if ($tieneBoleta && ! $tieneFactura) {
            return 'BOLETA';
        }
        if ($tieneFactura) {
            return 'FACTURA';
        }
        if ($tieneBoleta) {
            return 'BOLETA';
        }

        return null;
    }

    /**
     * Devuelve los campos que un listado de cotizaciones necesita para mostrar el formulario
     * de comprobante, priorizando:
     *   1) ComprobanteForm persistente (si existe).
     *   2) Último UsuarioDatosFacturacion del cliente (fallback).
     *
     * @param object $cotizacion        Objeto con id, correo, telefono, documento e id_contenedor.
     * @param ComprobanteForm|null $form Form ya pre-cargado (para evitar N+1 en listados).
     * @return array{
     *   comprobante_form: ComprobanteForm|array|null,
     *   tipo_entrega: string|null,
     *   form_tipo_comprobante: string|null,
     *   prefill_from_usuario_datos_facturacion: bool
     * }
     */
    public static function resolveForListing($cotizacion, ComprobanteForm $form = null)
    {
        if ($form) {
            return [
                'comprobante_form' => $form,
                'tipo_entrega' => $form->destino_entrega,
                'form_tipo_comprobante' => $form->tipo_comprobante,
                'prefill_from_usuario_datos_facturacion' => false,
            ];
        }

        $udf = self::findLatestUsuarioDatosFacturacion($cotizacion);
        if ($udf) {
            $synthetic = self::buildSyntheticFromUdf($udf, $cotizacion);
            if ($synthetic) {
                return [
                    'comprobante_form' => $synthetic,
                    'tipo_entrega' => $synthetic['destino_entrega'] ?? null,
                    'form_tipo_comprobante' => $synthetic['tipo_comprobante'] ?? null,
                    'prefill_from_usuario_datos_facturacion' => true,
                ];
            }
        }

        return [
            'comprobante_form' => null,
            'tipo_entrega' => null,
            'form_tipo_comprobante' => null,
            'prefill_from_usuario_datos_facturacion' => false,
        ];
    }
}
