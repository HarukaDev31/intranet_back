<?php

namespace Tests\Unit;

use App\Exceptions\ChinaBillingException;
use App\Models\Grupo;
use App\Models\Usuario;
use App\Services\ChinaBilling\ChinaBillingAuthorization;
use Tests\TestCase;

class ChinaBillingOperationScopeTest extends TestCase
{
    public function test_encargado_ve_las_organizaciones_que_se_le_pasan_como_activas()
    {
        $ids = $this->auth()->organizationIdsForOperations($this->user(Usuario::ROL_ENCARGADO_CHINA, 1), [1, 6, 7]);

        $this->assertSame([1, 6, 7], $ids);
    }

    public function test_otro_rol_solo_ve_su_organizacion_aunque_existan_otras()
    {
        $ids = $this->auth()->organizationIdsForOperations($this->user(Usuario::ROL_SOCIO, 6), [1, 6, 7]);

        $this->assertSame([6], $ids);
    }

    public function test_almacen_de_carga_no_hereda_la_vista_de_todas()
    {
        $ids = $this->auth()->organizationIdsForOperations($this->user(Usuario::ROL_ALMACEN_CHINA, 1), [1, 6, 7]);

        $this->assertSame([1], $ids);
    }

    public function test_sin_organizacion_no_ve_operaciones()
    {
        $this->expectException(ChinaBillingException::class);

        $this->auth()->organizationIdsForOperations($this->user(Usuario::ROL_COTIZADOR, 0), [1, 6]);
    }

    private function auth(): ChinaBillingAuthorization
    {
        return new ChinaBillingAuthorization();
    }

    private function user(string $rol, int $organizacionId): Usuario
    {
        $user = new Usuario();
        $user->ID_Organizacion = $organizacionId > 0 ? $organizacionId : null;
        $grupo = new Grupo();
        $grupo->No_Grupo = $rol;
        $user->setRelation('grupo', $grupo);

        return $user;
    }
}
