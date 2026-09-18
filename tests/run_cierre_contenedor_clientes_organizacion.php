<?php

/**
 * Runner compatible con PHP 8.2 (phpunit 12 del proyecto pide 8.3).
 * Uso: php tests/run_cierre_contenedor_clientes_organizacion.php
 */
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Jobs\ValidateCotizacionesWithLoadedProveedoresJob;
use App\Support\CargaConsolidada\ClientesVisibility;
use App\Support\Phone\CountryPhoneHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

$failed = 0;
$passed = 0;

function qa_assert($ok, $message)
{
    global $failed, $passed;
    if ($ok) {
        $passed++;
        echo "  OK  {$message}\n";
        return;
    }
    $failed++;
    echo "  FAIL  {$message}\n";
}

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
        fwrite(STDERR, "Falta la tabla {$tabla}\n");
        exit(1);
    }
}
if (!Schema::hasColumn('clientes', 'organizacion_id')) {
    fwrite(STDERR, "Falta clientes.organizacion_id\n");
    exit(1);
}

$org1 = (int) DB::table('organizacion')->where('ID_Organizacion', 1)->value('ID_Organizacion');
$org2 = (int) DB::table('organizacion')->where('ID_Organizacion', 2)->value('ID_Organizacion');
if ($org1 !== 1 || $org2 !== 2) {
    fwrite(STDERR, "Se necesitan organizaciones 1 y 2\n");
    exit(1);
}

$idPais = (int) DB::table('pais')->orderBy('ID_Pais')->value('ID_Pais');
$idTipo = (int) DB::table('contenedor_consolidado_tipo_cliente')->orderBy('id')->value('id');
if ($idPais <= 0 || $idTipo <= 0) {
    fwrite(STDERR, "Faltan pais o tipo de cliente\n");
    exit(1);
}

echo "1) Job de cierre: cliente en org 2, no reusa org 1\n";

DB::beginTransaction();
try {
    $marker = 'qa-cierre-' . substr(str_replace('-', '', (string) Str::uuid()), 0, 12);
    $telefono = '59399' . substr(preg_replace('/[^0-9]/', '', $marker), 0, 7);
    $correo = $marker . '@example.test';

    $clienteOrg1Id = DB::table('clientes')->insertGetId([
        'nombre' => 'Cliente Probusiness ' . $marker,
        'documento' => '10000001',
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
        'carga' => 'QA-' . $marker,
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
        'nombre' => 'Cliente Ecuador ' . $marker,
        'documento' => '20000002',
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
        'products' => 'Item QA ' . $marker,
        'estados_proveedor' => 'LOADED',
        'cbm_total' => 1.5,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    (new ValidateCotizacionesWithLoadedProveedoresJob($contenedorId))->handle();

    $cotizacion = DB::table('contenedor_consolidado_cotizacion')->where('id', $cotizacionId)->first();
    qa_assert($cotizacion && $cotizacion->id_cliente, 'Liga id_cliente en la cotización');
    qa_assert(
        $cotizacion && (int) $cotizacion->id_cliente !== $clienteOrg1Id,
        'No reutiliza el cliente de org 1 con el mismo teléfono'
    );

    $clienteNuevo = $cotizacion
        ? DB::table('clientes')->where('id', $cotizacion->id_cliente)->first()
        : null;
    qa_assert($clienteNuevo && (int) $clienteNuevo->organizacion_id === $org2, 'Nuevo cliente nace en org 2');
    qa_assert($clienteNuevo && $clienteNuevo->telefono === $telefono, 'Teléfono del cliente org 2');

    $customers = DB::table('contenedor_consolidado_cotizacion as CC')
        ->join('carga_consolidada_contenedor as CONT', 'CONT.id', '=', 'CC.id_contenedor')
        ->where('CC.id', $cotizacionId);
    ClientesVisibility::excludeGraduadosDeCustomers(
        $customers,
        'CC',
        'CONT',
        'contenedor_consolidado_cotizacion_proveedores'
    );
    qa_assert($customers->select('CC.id')->first() === null, 'Sale de Customers al estar COMPLETADO + LOADED');
} catch (Throwable $e) {
    echo "  FAIL  excepción: " . $e->getMessage() . "\n";
    $failed++;
} finally {
    DB::rollBack();
}

echo "2) Detecta cliente existente con +51 aunque la cotización venga en 9 dígitos\n";
DB::beginTransaction();
try {
    $marker = 'qa-51-' . substr(str_replace('-', '', (string) Str::uuid()), 0, 10);
    $local = '987' . substr(preg_replace('/[^0-9]/', '', $marker), 0, 6);
    $correo = $marker . '@example.test';

    $clienteExistenteId = DB::table('clientes')->insertGetId([
        'nombre' => 'Cliente +51 ' . $marker,
        'documento' => '10987654',
        'correo' => 'fmt-' . $correo,
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
        'carga' => 'QA51-' . $marker,
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
        'nombre' => 'Cliente local ' . $marker,
        'documento' => '20987654',
        'correo' => $correo,
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
        'products' => 'Item +51 ' . $marker,
        'estados_proveedor' => 'LOADED',
        'cbm_total' => 1.1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    (new ValidateCotizacionesWithLoadedProveedoresJob($contenedorId))->handle();

    $cotizacion = DB::table('contenedor_consolidado_cotizacion')->where('id', $cotizacionId)->first();
    qa_assert(
        $cotizacion && (int) $cotizacion->id_cliente === (int) $clienteExistenteId,
        'Reutiliza el cliente de la misma org aunque el teléfono esté guardado como +51'
    );
} catch (Throwable $e) {
    echo "  FAIL  excepción: " . $e->getMessage() . "\n";
    $failed++;
} finally {
    DB::rollBack();
}

echo "3) Prefijo de país: guardar y comparar sin hardcodear solo +51\n";
qa_assert(CountryPhoneHelper::ensureCountryCode('987654321', '51') === '51987654321', 'Perú local → 51 + número');
qa_assert(CountryPhoneHelper::ensureCountryCode('991234567', '593') === '593991234567', 'Ecuador local → 593 + número');
qa_assert(CountryPhoneHelper::ensureCountryCode('+51 987 654 321', '593') === '51987654321', 'Ya tiene +51: no se pisa con 593');
qa_assert(in_array('987654321', CountryPhoneHelper::searchVariants('51987654321'), true), 'Buscar 51… también por nacionales');
qa_assert(in_array('991234567', CountryPhoneHelper::searchVariants('593991234567'), true), 'Buscar 593… también por nacionales');
qa_assert(!in_array('593987654321', CountryPhoneHelper::searchVariants('987654321'), true), 'Un local no inventa el prefijo del otro país');

echo "4) Visibilidad socio: abierto vs cerrado\n";
$queryAbierta = DB::table('contenedor_consolidado_cotizacion as CC');
ClientesVisibility::applyListado($queryAbierta, 'CC', true, false);
$sqlAbierta = $queryAbierta->toSql();
$bindingsAbierta = $queryAbierta->getBindings();
qa_assert(strpos($sqlAbierta, 'estado_resumen') !== false, 'Abierto incluye estado_resumen');
qa_assert(in_array('COTIZADO', $bindingsAbierta, true), 'Abierto incluye COTIZADO');

$queryCerrada = DB::table('contenedor_consolidado_cotizacion as CC');
ClientesVisibility::applyListado($queryCerrada, 'CC', true, true);
$sqlCerrada = $queryCerrada->toSql();
qa_assert(strpos($sqlCerrada, 'estado_cliente') !== false, 'Cerrado filtra estado_cliente');
qa_assert(strpos($sqlCerrada, 'estado_resumen') === false, 'Cerrado no usa estado_resumen');

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
