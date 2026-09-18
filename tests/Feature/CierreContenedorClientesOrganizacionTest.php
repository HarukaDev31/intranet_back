<?php

namespace Tests\Feature;

use App\Jobs\ValidateCotizacionesWithLoadedProveedoresJob;
use App\Support\CargaConsolidada\ClientesVisibility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cierre de contenedor → tabla clientes con organizacion_id del padre.
 * Requiere MySQL local (no sqlite :memory:).
 */
class CierreContenedorClientesOrganizacionTest extends TestCase
{
    use DatabaseTransactions;

    private $marker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->marker = 'qa-cierre-' . substr(str_replace('-', '', (string) Str::uuid()), 0, 12);
    }

    public function test_job_crea_cliente_en_la_org_de_la_cotizacion_y_no_reusa_el_de_otra_org()
    {
        $this->skipSiFaltaSchema();

        $org1 = (int) DB::table('organizacion')->where('ID_Organizacion', 1)->value('ID_Organizacion');
        $org2 = (int) DB::table('organizacion')->where('ID_Organizacion', 2)->value('ID_Organizacion');
        if ($org1 !== 1 || $org2 !== 2) {
            $this->markTestSkipped('Se necesitan organizaciones 1 y 2.');
        }

        $idPais = (int) DB::table('pais')->orderBy('ID_Pais')->value('ID_Pais');
        $idTipo = (int) DB::table('contenedor_consolidado_tipo_cliente')->orderBy('id')->value('id');
        if ($idPais <= 0 || $idTipo <= 0) {
            $this->markTestSkipped('Faltan pais o tipo de cliente.');
        }

        $telefono = '59399' . substr(preg_replace('/[^0-9]/', '', $this->marker), 0, 7);
        $documentoOrg1 = '10000001';
        $documentoOrg2 = '20000002';
        $correo = $this->marker . '@example.test';

        $clienteOrg1Id = DB::table('clientes')->insertGetId([
            'nombre' => 'Cliente Probusiness ' . $this->marker,
            'documento' => $documentoOrg1,
            'correo' => 'pb-' . $correo,
            'telefono' => $telefono,
            'fecha' => now()->toDateString(),
            'organizacion_id' => $org1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $contenedorId = DB::table('carga_consolidada_contenedor')->insertGetId([
            'mes' => 'SEPTIEMBRE',
            'id_pais' => $idPais,
            'organizacion_id' => $org2,
            'carga' => 'QA-' . $this->marker,
            'empresa' => 2,
            'estado' => 'PENDIENTE',
            'estado_china' => 'COMPLETADO',
            'tipo_carga' => 'CARGA CONSOLIDADA',
            'f_inicio' => now()->toDateString(),
        ]);

        $cotizacionId = DB::table('contenedor_consolidado_cotizacion')->insertGetId([
            'organizacion_id' => $org2,
            'uuid' => (string) Str::uuid(),
            'id_contenedor' => $contenedorId,
            'id_tipo_cliente' => $idTipo,
            'fecha' => now(),
            'nombre' => 'Cliente Ecuador ' . $this->marker,
            'documento' => $documentoOrg2,
            'correo' => $correo,
            'telefono' => $telefono,
            'estado' => 'CONFIRMADO',
            'estado_cotizador' => 'CONFIRMADO',
            'estado_resumen' => 'CONFIRMADO',
            'estado_cliente' => 'RESERVADO',
            'id_cliente' => null,
        ]);

        DB::table('contenedor_consolidado_cotizacion_proveedores')->insert([
            'organizacion_id' => $org2,
            'id_cotizacion' => $cotizacionId,
            'id_contenedor' => $contenedorId,
            'products' => 'Item QA ' . $this->marker,
            'estados_proveedor' => 'LOADED',
            'cbm_total' => 1.5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new ValidateCotizacionesWithLoadedProveedoresJob($contenedorId))->handle();

        $cotizacion = DB::table('contenedor_consolidado_cotizacion')->where('id', $cotizacionId)->first();
        $this->assertNotNull($cotizacion->id_cliente, 'El job debe ligar id_cliente en la cotización.');
        $this->assertNotEquals(
            $clienteOrg1Id,
            (int) $cotizacion->id_cliente,
            'No debe reutilizar el cliente de otra organización aunque el teléfono coincida.'
        );

        $clienteNuevo = DB::table('clientes')->where('id', $cotizacion->id_cliente)->first();
        $this->assertNotNull($clienteNuevo);
        $this->assertEquals($org2, (int) $clienteNuevo->organizacion_id);
        $this->assertEquals($telefono, $clienteNuevo->telefono);

        $customers = DB::table('contenedor_consolidado_cotizacion as CC')
            ->join('carga_consolidada_contenedor as CONT', 'CONT.id', '=', 'CC.id_contenedor')
            ->where('CC.id', $cotizacionId);
        ClientesVisibility::excludeGraduadosDeCustomers(
            $customers,
            'CC',
            'CONT',
            'contenedor_consolidado_cotizacion_proveedores'
        );
        $this->assertNull(
            $customers->select('CC.id')->first(),
            'Con contenedor COMPLETADO y proveedor LOADED la fila no debe seguir en Customers.'
        );
    }

    public function test_job_reusa_cliente_de_la_misma_org_aunque_el_telefono_este_con_prefijo_51()
    {
        $this->skipSiFaltaSchema();

        $org2 = (int) DB::table('organizacion')->where('ID_Organizacion', 2)->value('ID_Organizacion');
        $idPais = (int) DB::table('pais')->orderBy('ID_Pais')->value('ID_Pais');
        $idTipo = (int) DB::table('contenedor_consolidado_tipo_cliente')->orderBy('id')->value('id');
        if ($org2 !== 2 || $idPais <= 0 || $idTipo <= 0) {
            $this->markTestSkipped('Faltan org 2, pais o tipo de cliente.');
        }

        $local = '987' . substr(preg_replace('/[^0-9]/', '', $this->marker), 0, 6);

        $clienteExistenteId = DB::table('clientes')->insertGetId([
            'nombre' => 'Cliente +51 ' . $this->marker,
            'documento' => '10987654',
            'correo' => 'fmt-' . $this->marker . '@example.test',
            'telefono' => '+51 ' . substr($local, 0, 3) . ' ' . substr($local, 3, 3) . ' ' . substr($local, 6),
            'fecha' => now()->toDateString(),
            'organizacion_id' => $org2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $contenedorId = DB::table('carga_consolidada_contenedor')->insertGetId([
            'mes' => 'SEPTIEMBRE',
            'id_pais' => $idPais,
            'organizacion_id' => $org2,
            'carga' => 'QA51-' . $this->marker,
            'empresa' => 2,
            'estado' => 'PENDIENTE',
            'estado_china' => 'COMPLETADO',
            'tipo_carga' => 'CARGA CONSOLIDADA',
            'f_inicio' => now()->toDateString(),
        ]);

        $cotizacionId = DB::table('contenedor_consolidado_cotizacion')->insertGetId([
            'organizacion_id' => $org2,
            'uuid' => (string) Str::uuid(),
            'id_contenedor' => $contenedorId,
            'id_tipo_cliente' => $idTipo,
            'fecha' => now(),
            'nombre' => 'Cliente local ' . $this->marker,
            'documento' => '20987654',
            'correo' => $this->marker . '@example.test',
            'telefono' => $local,
            'estado' => 'CONFIRMADO',
            'estado_cotizador' => 'CONFIRMADO',
            'estado_resumen' => 'CONFIRMADO',
            'estado_cliente' => 'RESERVADO',
            'id_cliente' => null,
        ]);

        DB::table('contenedor_consolidado_cotizacion_proveedores')->insert([
            'organizacion_id' => $org2,
            'id_cotizacion' => $cotizacionId,
            'id_contenedor' => $contenedorId,
            'products' => 'Item +51 ' . $this->marker,
            'estados_proveedor' => 'LOADED',
            'cbm_total' => 1.1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new ValidateCotizacionesWithLoadedProveedoresJob($contenedorId))->handle();

        $cotizacion = DB::table('contenedor_consolidado_cotizacion')->where('id', $cotizacionId)->first();
        $this->assertSame(
            (int) $clienteExistenteId,
            (int) $cotizacion->id_cliente,
            'Debe reutilizar el cliente de la misma org aunque el teléfono esté como +51.'
        );
    }

    public function test_listado_socio_abierto_incluye_cotizado_y_cerrado_solo_loaded()
    {
        $this->skipSiFaltaSchema();

        $queryAbierta = DB::table('contenedor_consolidado_cotizacion as CC');
        ClientesVisibility::applyListado($queryAbierta, 'CC', true, false);
        $sqlAbierta = $queryAbierta->toSql();
        $this->assertStringContainsString('estado_resumen', $sqlAbierta);
        $this->assertContains('COTIZADO', $queryAbierta->getBindings());

        $queryCerrada = DB::table('contenedor_consolidado_cotizacion as CC');
        ClientesVisibility::applyListado($queryCerrada, 'CC', true, true);
        $sqlCerrada = $queryCerrada->toSql();
        $this->assertStringContainsString('estado_cliente', $sqlCerrada);
        $this->assertStringNotContainsString('estado_resumen', $sqlCerrada);
    }

    private function skipSiFaltaSchema()
    {
        foreach ([
            'clientes',
            'organizacion',
            'pais',
            'carga_consolidada_contenedor',
            'contenedor_consolidado_cotizacion',
            'contenedor_consolidado_cotizacion_proveedores',
            'contenedor_consolidado_tipo_cliente',
        ] as $tabla) {
            if (!Schema::hasTable($tabla)) {
                $this->markTestSkipped('Falta la tabla ' . $tabla);
            }
        }

        if (!Schema::hasColumn('clientes', 'organizacion_id')) {
            $this->markTestSkipped('Falta clientes.organizacion_id. Corre la migración.');
        }
    }
}
