<?php

namespace App\Support;

use App\Models\CalculadoraImportacion;
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
        if (!$calc && !empty($cotizacion->id)) {
            $calc = CalculadoraImportacion::where('id_cotizacion', $cotizacion->id)->first();
        }

        $tipoDocumento = $calc && !empty($calc->tipo_documento)
            ? strtoupper((string) $calc->tipo_documento)
            : '';

        $rucCliente = $calc && !empty($calc->ruc_cliente)
            ? trim((string) $calc->ruc_cliente)
            : '';
        $documentoCotizacion = trim((string) ($cotizacion->documento ?? ''));
        $docDigits = preg_replace('/\D+/', '', $rucCliente !== '' ? $rucCliente : $documentoCotizacion);

        $domicilioFiscal = $calc ? trim((string) ($calc->domicilio_fiscal ?? '')) : '';
        $coordNombre = $calc ? trim((string) ($calc->coordinador_operativo_nombre ?? '')) : '';
        $coordDni = $calc ? trim((string) ($calc->coordinador_operativo_dni ?? '')) : '';

        // RUC: tipo_documento, documento de 11 dígitos, o campos de contrato RUC cargados.
        $esRuc = $tipoDocumento === 'RUC'
            || strlen((string) $docDigits) === 11
            || $domicilioFiscal !== ''
            || $coordNombre !== '';

        if ($tipoDocumento === '') {
            $tipoDocumento = $esRuc ? 'RUC' : 'DNI';
        }

        $razonSocial = $calc && !empty($calc->razon_social)
            ? $calc->razon_social
            : $cotizacion->nombre;

        if ($esRuc) {
            $documento = $rucCliente !== '' ? $rucCliente : $documentoCotizacion;
        } else {
            $documento = ($calc && !empty($calc->dni_cliente))
                ? $calc->dni_cliente
                : $documentoCotizacion;
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
            'cliente_ruc' => $esRuc ? $documento : ($rucCliente !== '' ? $rucCliente : null),
            'cliente_domicilio_fiscal' => $domicilioFiscal !== '' ? $domicilioFiscal : null,
            'coordinador_operativo_nombre' => $coordNombre !== '' ? $coordNombre : null,
            'coordinador_operativo_dni' => $coordDni !== '' ? $coordDni : null,
            'carga' => $carga,
            'logo_contrato_url' => $logoPath,
            'cod_contract' => $cotizacion->cod_contract,
            'cod_contract_calculator' => $calc ? $calc->cod_cotizacion : null,
        ];

        return array_merge($base, $extra);
    }
}
