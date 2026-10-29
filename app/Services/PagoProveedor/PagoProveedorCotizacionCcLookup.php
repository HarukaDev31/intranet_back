<?php

namespace App\Services\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Models\CalculadoraImportacion;
use App\Models\PagoProveedor\PagoProveedorCotizacion;
use App\Support\CargaConsolidada\CargaLabel;

class PagoProveedorCotizacionCcLookup
{
    /**
     * @return array<string, mixed>
     */
    public function buscar(string $codigo): array
    {
        $codigo = strtoupper(trim($codigo));
        if (!preg_match('/^CO\d{8}$/', $codigo)) {
            throw new PagoProveedorException('El número de cotización de Consolidada debe tener el formato CO y 8 dígitos.');
        }

        $calculadora = CalculadoraImportacion::query()
            ->with(['cotizacion.contenedor', 'cotizacion.proveedores', 'proveedores', 'contenedor'])
            ->where('cod_cotizacion', $codigo)
            ->first();

        if (!$calculadora) {
            throw new PagoProveedorException('Ese número no existe en Carga Consolidada.', 404);
        }

        $cotizacion = $calculadora->cotizacion;
        $contenedor = ($cotizacion instanceof \Illuminate\Database\Eloquent\Model ? $cotizacion->getRelationValue('contenedor') : null)
            ?: $calculadora->contenedor;
        $documento = $calculadora->dni_cliente ?: $calculadora->ruc_cliente;
        $nombre = $calculadora->razon_social ?: $calculadora->nombre_cliente;

        if ($cotizacion instanceof \Illuminate\Database\Eloquent\Model) {
            $nombre = $cotizacion->getAttribute('nombre') ?: $nombre;
            $documento = $cotizacion->getAttribute('documento') ?: $documento;
        }

        $codigos = $this->codigos($calculadora);

        $yaUsada = PagoProveedorCotizacion::query()
            ->where('cod_cotizacion_cc', $codigo)
            ->exists();

        return [
            'cod_cotizacion_cc' => $codigo,
            'calculadora_importacion_id' => $calculadora->id,
            'id_cotizacion_cc' => $cotizacion instanceof \Illuminate\Database\Eloquent\Model ? $cotizacion->getAttribute('id') : null,
            'cliente' => [
                'nombre' => $nombre,
                'documento' => $documento,
                'whatsapp' => ($cotizacion instanceof \Illuminate\Database\Eloquent\Model ? $cotizacion->getAttribute('telefono') : null) ?: $calculadora->whatsapp_cliente,
                'correo' => ($cotizacion instanceof \Illuminate\Database\Eloquent\Model ? $cotizacion->getAttribute('correo') : null) ?: $calculadora->correo_cliente,
            ],
            'consolidado' => $this->consolidado($contenedor),
            'proveedores' => $codigos,
            'ya_usada' => $yaUsada,
            'aviso' => $yaUsada
                ? 'Ya existe una cotización de pago a proveedor con este número. Puedes continuar.'
                : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function codigos(CalculadoraImportacion $calculadora): array
    {
        $cotizacion = $calculadora->cotizacion;
        $fuente = $calculadora->proveedores;
        if ($cotizacion instanceof \Illuminate\Database\Eloquent\Model) {
            $deCotizacion = $cotizacion->getRelationValue('proveedores');
            if ($deCotizacion instanceof \Illuminate\Support\Collection && $deCotizacion->isNotEmpty()) {
                $fuente = $deCotizacion;
            }
        }

        $codigos = [];
        foreach ($fuente as $proveedor) {
            if (!$proveedor instanceof \Illuminate\Database\Eloquent\Model) {
                continue;
            }
            $codigo = trim((string) $proveedor->getAttribute('code_supplier'));
            if ($codigo === '') {
                continue;
            }
            $codigos[] = [
                'id_proveedor_cc' => $proveedor->getAttribute('id'),
                'code_supplier' => $codigo,
                'producto' => $proveedor->getAttribute('products'),
                'nombre' => $proveedor->getAttribute('supplier'),
            ];
        }

        return $codigos;
    }

    private function consolidado(mixed $contenedor): ?string
    {
        if (!$contenedor) {
            return null;
        }

        $label = CargaLabel::format($contenedor->carga ?? '', $contenedor->f_inicio ?? null, $contenedor->parte ?? null);
        if (preg_match('/^(.+)-(\d{4})$/', $label, $coincidencias)) {
            return $coincidencias[1] . ' - ' . $coincidencias[2];
        }

        return $label !== '' ? $label : null;
    }
}
