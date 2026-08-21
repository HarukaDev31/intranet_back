<?php

namespace App\Support;

use App\Models\CalculadoraImportacion;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\Cotizacion;

class ContratoViewData
{
    /**
     * Datos comunes para contracts.contrato y contracts.contrato_firmado.
     * DNI/RUC y datos de partes salen de la calculadora vinculada (id_cotizacion).
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

        // Fuente de verdad: fila calculadora_importacion asociada a la cotización.
        $tipoDocumento = $calc && !empty($calc->tipo_documento)
            ? strtoupper(trim((string) $calc->tipo_documento))
            : 'DNI';
        if ($tipoDocumento !== 'RUC' && $tipoDocumento !== 'DNI') {
            $tipoDocumento = 'DNI';
        }
        $esRuc = $tipoDocumento === 'RUC';

        $razonSocial = $calc && !empty($calc->razon_social)
            ? trim((string) $calc->razon_social)
            : (string) ($cotizacion->nombre ?? '');

        $nombreCliente = $calc && !empty($calc->nombre_cliente)
            ? trim((string) $calc->nombre_cliente)
            : (string) ($cotizacion->nombre ?? '');

        if ($esRuc) {
            $documento = $calc && !empty($calc->ruc_cliente)
                ? trim((string) $calc->ruc_cliente)
                : trim((string) ($cotizacion->documento ?? ''));
            $nombreMostrar = $razonSocial !== '' ? $razonSocial : $nombreCliente;
        } else {
            $documento = $calc && !empty($calc->dni_cliente)
                ? trim((string) $calc->dni_cliente)
                : trim((string) ($cotizacion->documento ?? ''));
            $nombreMostrar = $nombreCliente !== '' ? $nombreCliente : (string) ($cotizacion->nombre ?? '');
        }

        $domicilioFiscal = $calc ? trim((string) ($calc->domicilio_fiscal ?? '')) : '';
        $coordNombre = $calc ? trim((string) ($calc->coordinador_operativo_nombre ?? '')) : '';
        $coordDni = $calc ? trim((string) ($calc->coordinador_operativo_dni ?? '')) : '';

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
            'cliente_nombre' => $nombreMostrar,
            'cliente_documento' => $documento,
            'cliente_domicilio' => $cotizacion->direccion ?? null,
            'tipo_documento' => $tipoDocumento,
            'es_ruc' => $esRuc,
            'cliente_razon_social' => $razonSocial !== '' ? $razonSocial : $nombreMostrar,
            'cliente_ruc' => $esRuc ? $documento : null,
            'cliente_domicilio_fiscal' => $esRuc && $domicilioFiscal !== '' ? $domicilioFiscal : null,
            'coordinador_operativo_nombre' => $esRuc && $coordNombre !== '' ? $coordNombre : null,
            'coordinador_operativo_dni' => $esRuc && $coordDni !== '' ? $coordDni : null,
            'carga' => $carga,
            'logo_contrato_url' => $logoPath,
            'cod_contract' => $cotizacion->cod_contract,
            'cod_contract_calculator' => $calc ? $calc->cod_cotizacion : null,
        ];

        return array_merge($base, $extra);
    }
}
