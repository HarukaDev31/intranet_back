<?php

namespace Tests\Unit;

use App\Exceptions\OrganizacionClienteException;
use App\Helpers\CodeIgniterEncryption;
use App\Services\ChinaBilling\ChinaClientService;
use App\Services\Organizacion\OrganizacionClienteService as Service;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * PRY-26-0003: reglas del alta/edición de organizaciones cliente (sin BD).
 */
class OrganizacionClienteServiceTest extends TestCase
{
    public function test_tipo_se_normaliza_a_socio_o_importador(): void
    {
        $this->assertSame('socio', Service::normalizarTipo('partner'));
        $this->assertSame('socio', Service::normalizarTipo(null));
        $this->assertSame('socio', Service::normalizarTipo('SOCIO'));
        $this->assertSame('importador', Service::normalizarTipo('importer'));
        $this->assertSame('importador', Service::normalizarTipo(' Importador '));
    }

    public function test_nombre_del_rol_segun_tipo(): void
    {
        $this->assertSame('Socio', Service::nombreGrupo('socio'));
        $this->assertSame('Importador', Service::nombreGrupo('importador'));
    }

    public function test_socio_consolida_por_defecto_en_su_pais_de_origen(): void
    {
        $this->assertSame([4], Service::paisesParaConsolidar('socio', 4, []));
        $this->assertSame([4], Service::paisesParaConsolidar('socio', 4, null));
        $this->assertSame([7, 9], Service::paisesParaConsolidar('socio', 4, [7, '9', 7, ['value' => 9]]));
        $this->assertSame([], Service::paisesParaConsolidar('socio', null, []));
    }

    public function test_importador_no_tiene_paises_para_consolidar(): void
    {
        $this->assertSame([], Service::paisesParaConsolidar('importador', 4, [4, 5]));
    }

    public function test_tarifas_vacias_o_cero_quedan_sin_tarifa(): void
    {
        $this->assertNull(Service::normalizarTarifa(null, 'Tarifa 1'));
        $this->assertNull(Service::normalizarTarifa('', 'Tarifa 1'));
        $this->assertNull(Service::normalizarTarifa(0, 'Tarifa 1'));
        $this->assertSame(85.5, Service::normalizarTarifa('85.50', 'Tarifa 1'));
    }

    public function test_tarifa_negativa_es_error(): void
    {
        $this->expectException(OrganizacionClienteException::class);
        Service::normalizarTarifa(-1, 'Tarifa 2');
    }

    public function test_detecta_cambio_de_tarifas_para_el_historial(): void
    {
        $this->assertTrue(Service::tarifasCambiaron(null, null, 80.0, 70.0));
        $this->assertFalse(Service::tarifasCambiaron('80.00', '70.00', 80.0, 70.0));
        $this->assertTrue(Service::tarifasCambiaron(80.0, 70.0, 80.0, 65.0));
        $this->assertFalse(Service::tarifasCambiaron(null, null, null, null));
    }

    public function test_payload_de_clientes_china_se_mapea_a_la_organizacion(): void
    {
        $partner = ChinaClientService::organizationData([
            'type' => 'partner', 'name' => 'Socio Bolivia', 'origin_country_id' => 3, 'consolidation_country_ids' => [3, 8],
            'whatsapp' => '+591 7000', 'contact_name' => 'Ana', 'email' => 'ana@socio.com', 'rate_1' => 90, 'rate_2' => 80,
        ]);
        $this->assertSame('socio', $partner['tipo_cliente']);
        $this->assertSame([3, 8], $partner['paises_habilitados']);
        $this->assertSame(90, $partner['tarifa_1']);
        $this->assertSame('ana@socio.com', $partner['correo']);

        $importer = ChinaClientService::organizationData([
            'type' => 'importer', 'name' => 'Importa SAC', 'origin_country_id' => 1, 'consolidation_country_ids' => [1],
            'whatsapp' => '999', 'rate_1' => 90, 'rate_2' => 80,
        ]);
        $this->assertSame('importador', $importer['tipo_cliente']);
        $this->assertSame([], $importer['paises_habilitados']);
        $this->assertNull($importer['tarifa_1']);
        $this->assertNull($importer['tarifa_2']);
    }

    public function test_login_acepta_contrasenas_bcrypt(): void
    {
        $hash = Hash::make('socio@correo.com');
        $this->assertTrue(CodeIgniterEncryption::isBcryptHash($hash));
        $this->assertFalse(CodeIgniterEncryption::isBcryptHash('abc123=='));

        $encryption = new CodeIgniterEncryption();
        $this->assertTrue($encryption->verifyPassword('socio@correo.com', $hash));
        $this->assertFalse($encryption->verifyPassword('otra', $hash));
    }
}
