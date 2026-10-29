<?php

namespace App\Services\PagoProveedor;

use App\Models\PagoProveedor\PagoProveedorCotizacion;
use App\Models\PagoProveedor\PagoProveedorCotizacionProveedor;
use App\Traits\FileTrait;

class PagoProveedorCotizacionPresenter
{
    use FileTrait;

    public function __construct(
        private readonly PagoProveedorComisionService $comisiones,
        private readonly PagoProveedorVigenciaService $vigencia,
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>>  $codigosDisponibles
     * @return array<string, mixed>
     */
    public function cotizacion(
        PagoProveedorCotizacion $cotizacion,
        TarifaPagoProveedor $tarifa,
        bool $conHistorial = false,
        array $codigosDisponibles = []
    ): array {
        $cotizacion->loadMissing(['proveedores', 'solicitud']);
        $vencida = $cotizacion->estado === 'pendiente' && $this->vigencia->estaVencida($cotizacion->vigencia_fin);
        $calculo = $this->comisiones->calcular(
            $cotizacion->proveedores->map(fn (PagoProveedorCotizacionProveedor $proveedor) => $this->linea($proveedor))->all(),
            $tarifa
        );
        $porOrden = [];
        foreach ($calculo['proveedores'] as $linea) {
            $porOrden[$linea['orden']] = $linea;
        }

        $completa = $this->estaCompleta($cotizacion, $calculo);

        return [
            'id' => $cotizacion->id,
            'codigo' => $cotizacion->codigo,
            'solicitud_id' => $cotizacion->solicitud?->id,
            'estado' => $cotizacion->estado,
            'cod_cotizacion_cc' => $cotizacion->cod_cotizacion_cc,
            'consolidado' => $cotizacion->consolidado,
            'cliente_nombre' => $cotizacion->cliente_nombre,
            'cliente_documento' => $cotizacion->cliente_documento,
            'cliente_whatsapp' => $cotizacion->cliente_whatsapp,
            'cliente_correo' => $cotizacion->cliente_correo,
            'tipo_cambio' => $cotizacion->tipo_cambio,
            'tipo_cambio_confirmado' => $cotizacion->tipo_cambio_confirmado,
            'vigencia_fin' => optional($cotizacion->vigencia_fin)->toIso8601String(),
            'vigencia_texto' => $cotizacion->estado === 'confirmada'
                ? 'Confirmada'
                : $this->vigencia->texto($cotizacion->vigencia_fin),
            'vencida' => $vencida,
            'proveedores_cantidad' => $cotizacion->proveedores->count(),
            'total' => $calculo['total'],
            'total_ahora' => $calculo['total_ahora'],
            'comisiones' => $calculo['comisiones'],
            'puede_editar' => $cotizacion->estado === 'pendiente',
            'puede_eliminar' => $cotizacion->estado === 'pendiente',
            'puede_enviar' => $cotizacion->estado === 'pendiente' && !$vencida && $completa['pdf'],
            'puede_crear_solicitud' => $cotizacion->estado === 'pendiente' && !$vencida && $completa['solicitud'],
            'puede_renovar' => $vencida,
            'faltantes_pdf' => $completa['faltantes_pdf'],
            'faltantes' => $completa['faltantes'],
            'codigos_disponibles' => $codigosDisponibles,
            'proveedores' => $cotizacion->proveedores->map(function (PagoProveedorCotizacionProveedor $proveedor) use ($porOrden) {
                return $this->proveedor($proveedor, $porOrden[$proveedor->orden] ?? null);
            })->values()->all(),
            'resumen' => $calculo,
            'creado_en' => optional($cotizacion->created_at)->toIso8601String(),
            'historial' => $conHistorial ? $this->historial($cotizacion) : [],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $linea
     * @return array<string, mixed>
     */
    private function proveedor(PagoProveedorCotizacionProveedor $proveedor, ?array $linea): array
    {
        return [
            'id' => $proveedor->id,
            'orden' => $proveedor->orden,
            'code_supplier' => $proveedor->code_supplier,
            'id_proveedor_cc' => $proveedor->id_proveedor_cc,
            'nombre_proveedor' => $proveedor->nombre_proveedor,
            'producto' => $proveedor->producto,
            'moneda' => $proveedor->moneda,
            'tipo_pago' => $proveedor->tipo_pago,
            'valor_carga_usd' => $proveedor->valor_carga_usd,
            'modalidad_abono' => $proveedor->modalidad_abono,
            'modalidad_pago' => $proveedor->modalidad_pago,
            'porcentaje_1' => $proveedor->porcentaje_1,
            'porcentaje_2' => $proveedor->porcentaje_2,
            'fecha_cuota_2' => optional($proveedor->fecha_cuota_2)->format('Y-m-d'),
            'comision_bancaria' => $proveedor->comision_bancaria,
            'comision_administrativa' => $proveedor->comision_administrativa,
            'proforma_nombre' => $proveedor->proforma_nombre,
            'proforma_url' => $proveedor->proforma_path ? $this->cdnStorageUrl($proveedor->proforma_path) : null,
            'calculo' => $linea,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function linea(PagoProveedorCotizacionProveedor $proveedor): array
    {
        return [
            'orden' => $proveedor->orden,
            'moneda' => $proveedor->moneda,
            'valor_carga_usd' => $proveedor->valor_carga_usd,
            'modalidad_abono' => $proveedor->modalidad_abono,
            'modalidad_pago' => $proveedor->modalidad_pago,
            'porcentaje_1' => $proveedor->porcentaje_1,
            'porcentaje_2' => $proveedor->porcentaje_2,
            'comision_bancaria' => $proveedor->comision_bancaria,
            'comision_administrativa' => $proveedor->comision_administrativa,
        ];
    }

    /**
     * @param  array<string, mixed>  $calculo
     * @return array{pdf: bool, solicitud: bool, faltantes: array<int, string>, faltantes_pdf: array<int, string>}
     */
    private function estaCompleta(PagoProveedorCotizacion $cotizacion, array $calculo): array
    {
        $faltantes = [];
        if (!$cotizacion->tipo_cambio_confirmado || (float) $cotizacion->tipo_cambio <= 0) {
            $faltantes[] = 'Confirma el tipo de cambio.';
        }

        foreach ($cotizacion->proveedores as $proveedor) {
            $n = $proveedor->orden;
            if (!$proveedor->code_supplier) {
                $faltantes[] = "Proveedor {$n}: elige el código.";
            }
            if (!trim((string) $proveedor->producto)) {
                $faltantes[] = "Proveedor {$n}: falta el producto.";
            }
            if ((float) $proveedor->valor_carga_usd <= 0) {
                $faltantes[] = "Proveedor {$n}: el valor de carga debe ser mayor a 0.";
            }
            if ($proveedor->modalidad_abono === 'fraccionado') {
                $suma = round((float) $proveedor->porcentaje_1 + (float) $proveedor->porcentaje_2, 2);
                if ($suma !== 100.0) {
                    $faltantes[] = "Proveedor {$n}: los porcentajes deben sumar 100.";
                }
            }
        }

        $faltantesPdf = $faltantes;
        $faltantesSolicitud = $faltantes;
        foreach ($cotizacion->proveedores as $proveedor) {
            $n = $proveedor->orden;
            if (!$proveedor->proforma_path) {
                $faltantesSolicitud[] = "Proveedor {$n}: falta la proforma.";
            }
            if ($proveedor->modalidad_abono === 'fraccionado' && !$proveedor->fecha_cuota_2) {
                $faltantesSolicitud[] = "Proveedor {$n}: falta la fecha de la cuota 2.";
            }
        }

        if ($cotizacion->proveedores->isEmpty()) {
            $faltantesPdf[] = 'Agrega al menos un proveedor.';
            $faltantesSolicitud[] = 'Agrega al menos un proveedor.';
        }

        return [
            'pdf' => $faltantesPdf === [],
            'solicitud' => $faltantesSolicitud === [],
            'faltantes_pdf' => $faltantesPdf,
            'faltantes' => $faltantesSolicitud,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function historial(PagoProveedorCotizacion $cotizacion): array
    {
        return $cotizacion->historial()->limit(30)->get()->map(function ($evento) {
            return [
                'accion' => $evento->accion,
                'detalle' => $evento->detalle,
                'usuario' => trim($evento->usuario_nombre . ' · ' . $evento->area, ' ·'),
                'fecha' => optional($evento->created_at)->toIso8601String(),
            ];
        })->all();
    }
}
