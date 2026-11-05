<?php

namespace Tests\Unit;

use App\Models\PagoProveedor\PagoProveedorDocumento;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;
use App\Services\PagoProveedor\PagoProveedorDocumentoService;
use PHPUnit\Framework\TestCase;

class PagoProveedorDocumentoTest extends TestCase
{
    public function test_el_paso_de_la_solicitud_es_el_del_proveedor_menos_avanzado(): void
    {
        $proveedores = collect([
            $this->proveedor(1, PagoProveedorSolicitudProveedor::STATUS_IN_PROGRESS),
            $this->proveedor(3, PagoProveedorSolicitudProveedor::STATUS_IN_PROGRESS),
            $this->proveedor(2, PagoProveedorSolicitudProveedor::STATUS_IN_PROGRESS),
        ]);

        $this->assertSame(PagoProveedorDocumento::STEP_DATA, PagoProveedorDocumentoService::pasoDeProveedores($proveedores));
    }

    public function test_un_proveedor_anulado_no_atrasa_el_paso(): void
    {
        $proveedores = collect([
            $this->proveedor(1, PagoProveedorSolicitudProveedor::STATUS_CANCELLED),
            $this->proveedor(3, PagoProveedorSolicitudProveedor::STATUS_IN_PROGRESS),
        ]);

        $this->assertSame(PagoProveedorDocumento::STEP_EXECUTION, PagoProveedorDocumentoService::pasoDeProveedores($proveedores));
    }

    public function test_un_proveedor_completado_no_frena_el_paso(): void
    {
        $proveedores = collect([
            $this->proveedor(4, PagoProveedorSolicitudProveedor::STATUS_COMPLETED),
            $this->proveedor(2, PagoProveedorSolicitudProveedor::STATUS_IN_PROGRESS),
        ]);

        $this->assertSame(PagoProveedorDocumento::STEP_VALIDATION, PagoProveedorDocumentoService::pasoDeProveedores($proveedores));
    }

    public function test_sin_proveedores_vigentes_se_queda_en_solicitud_y_datos(): void
    {
        $proveedores = collect([
            $this->proveedor(4, PagoProveedorSolicitudProveedor::STATUS_CANCELLED),
        ]);

        $this->assertSame(PagoProveedorDocumento::STEP_DATA, PagoProveedorDocumentoService::pasoDeProveedores($proveedores));
    }

    public function test_un_proveedor_completado_emite_el_pdf_en_confirmacion(): void
    {
        $servicio = $this->servicio();
        $proveedor = $this->proveedor(2, PagoProveedorSolicitudProveedor::STATUS_COMPLETED);

        $this->assertSame(PagoProveedorDocumento::STEP_CONFIRMATION, $servicio->pasoDeSolicitudProveedor($proveedor));
    }

    public function test_cada_paso_tiene_su_etiqueta(): void
    {
        $this->assertSame('Cotización enviada', PagoProveedorDocumento::stepLabel(PagoProveedorDocumento::STEP_QUOTE));
        $this->assertSame('Solicitud y datos de pago', PagoProveedorDocumento::stepLabel(PagoProveedorDocumento::STEP_DATA));
        $this->assertSame('Validación de pago', PagoProveedorDocumento::stepLabel(PagoProveedorDocumento::STEP_VALIDATION));
        $this->assertSame('Ejecución de pago', PagoProveedorDocumento::stepLabel(PagoProveedorDocumento::STEP_EXECUTION));
        $this->assertSame('Confirmación', PagoProveedorDocumento::stepLabel(PagoProveedorDocumento::STEP_CONFIRMATION));
    }

    private function servicio(): PagoProveedorDocumentoService
    {
        return new PagoProveedorDocumentoService(
            $this->createMock(\App\Services\PagoProveedor\PagoProveedorHistorialService::class)
        );
    }

    private function proveedor(int $etapa, string $estado): PagoProveedorSolicitudProveedor
    {
        $proveedor = new PagoProveedorSolicitudProveedor(['stage' => $etapa, 'status' => $estado]);
        $proveedor->id = $etapa;

        return $proveedor;
    }
}
