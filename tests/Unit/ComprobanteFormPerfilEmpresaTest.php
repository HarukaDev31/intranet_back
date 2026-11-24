<?php

namespace Tests\Unit;

use App\Helpers\ComprobanteFormResolverHelper;
use App\Models\UserBusiness;
use PHPUnit\Framework\TestCase;

class ComprobanteFormPerfilEmpresaTest extends TestCase
{
    private function cotizacion(): object
    {
        return (object) ['id' => 11097, 'id_contenedor' => 21];
    }

    public function test_arma_factura_con_la_empresa_del_perfil(): void
    {
        $business = new UserBusiness([
            'user_id' => 55,
            'name' => ' SUMINISTROS Y MATERIALES INDUSTRIALES S.A.C. ',
            'ruc' => '20615188191',
        ]);

        $data = ComprobanteFormResolverHelper::buildSyntheticFromUserBusiness($business, $this->cotizacion());

        $this->assertNotNull($data);
        $this->assertSame('FACTURA', $data['tipo_comprobante']);
        $this->assertSame('SUMINISTROS Y MATERIALES INDUSTRIALES S.A.C.', $data['razon_social']);
        $this->assertSame('20615188191', $data['ruc']);
        $this->assertSame(11097, $data['id_cotizacion']);
        $this->assertSame(21, $data['id_contenedor']);
        $this->assertSame(55, $data['id_user']);
        $this->assertTrue($data['prefill_from_usuario_datos_facturacion']);
        $this->assertSame('perfil_empresa', $data['prefill_source']);
    }

    public function test_normaliza_el_ruc(): void
    {
        $business = new UserBusiness(['user_id' => 1, 'name' => 'ACME SAC', 'ruc' => '206-1518-8191']);

        $data = ComprobanteFormResolverHelper::buildSyntheticFromUserBusiness($business, $this->cotizacion());

        $this->assertSame('20615188191', $data['ruc']);
    }

    public function test_sin_ruc_valido_o_sin_razon_social_no_arma_nada(): void
    {
        $sinRuc = new UserBusiness(['user_id' => 1, 'name' => 'ACME SAC', 'ruc' => '12345678']);
        $sinNombre = new UserBusiness(['user_id' => 1, 'name' => '  ', 'ruc' => '20615188191']);

        $this->assertNull(ComprobanteFormResolverHelper::buildSyntheticFromUserBusiness($sinRuc, $this->cotizacion()));
        $this->assertNull(ComprobanteFormResolverHelper::buildSyntheticFromUserBusiness($sinNombre, $this->cotizacion()));
    }
}
