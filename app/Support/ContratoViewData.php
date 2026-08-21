<?php

namespace App\Support;

use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\Cotizacion;

class ContratoViewData
{
    /**
     * Datos comunes para contracts.contrato y contracts.contrato_firmado.
     *
     * @param  Cotizacion  $cotizacion
     * @param  array  $extra
     * @return array
     */
    public static function fromCotizacion(Cotizacion $cotizacion, array $extra = [])
    {
        $cotizacion->loadMissing(['contenedor', 'calculadoraImportacion']);

        $calc = $cotizacion->calculadoraImportacion;
        $tipoDocumento = $calc && !empty($calc->tipo_documento)
            ? strtoupper((string) $calc->tipo_documento)
            : 'DNI';
        $esRuc = $tipoDocumento === 'RUC';

        $razonSocial = $calc && !empty($calc->razon_social)
            ? $calc->razon_social
            : $cotizacion->nombre;

        if ($esRuc) {
            $documento = ($calc && !empty($calc->ruc_cliente))
                ? $calc->ruc_cliente
                : $cotizacion->documento;
        } else {
            $documento = ($calc && !empty($calc->dni_cliente))
                ? $calc->dni_cliente
                : $cotizacion->documento;
        }

        $contenedor = $cotizacion->contenedor;
        if (!$contenedor && !empty($cotizacion->id_contenedor)) {
            $contenedor = Contenedor::find($cotizacion->id_contenedor);
        }
        $carga = $contenedor ? $contenedor->carga : '';

        $logoPath = public_path('storage/logo_icons/logo_contrato.png');
        if (!is_file($logoPath)) {
            $logoPath = public_path('storage/logo_contrato.png');
        }

        $base = [
            'fecha' => date('d-m-Y'),
            'cliente_nombre' => $cotizacion->nombre,
            'cliente_documento' => $documento,
            'cliente_domicilio' => $cotizacion->direccion ?? null,
            'tipo_documento' => $tipoDocumento,
            'es_ruc' => $esRuc,
            'cliente_razon_social' => $razonSocial,
            'cliente_ruc' => $esRuc ? $documento : (($calc && !empty($calc->ruc_cliente)) ? $calc->ruc_cliente : null),
            'cliente_domicilio_fiscal' => $calc ? ($calc->domicilio_fiscal ?? null) : null,
            'coordinador_operativo_nombre' => $calc ? ($calc->coordinador_operativo_nombre ?? null) : null,
            'coordinador_operativo_dni' => $calc ? ($calc->coordinador_operativo_dni ?? null) : null,
            'carga' => $carga,
            'logo_contrato_url' => $logoPath,
            'cod_contract' => $cotizacion->cod_contract,
            'cod_contract_calculator' => $calc ? $calc->cod_cotizacion : null,
        ];

        return array_merge($base, $extra);
    }
}
