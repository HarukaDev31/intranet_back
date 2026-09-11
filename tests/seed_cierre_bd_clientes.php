<?php

/**
 * Crea un contenedor org 2 con 2 cotizaciones CONFIRMADO + proveedor LOADED
 * y corre el job de cierre. Deja los datos para verlos en BD clientes.
 *
 * php tests/seed_cierre_bd_clientes.php
 */
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Jobs\ValidateCotizacionesWithLoadedProveedoresJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

$carga = 'QA-CIERRE-BD';
$orgId = 2;

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

if ((int) DB::table('organizacion')->where('ID_Organizacion', $orgId)->value('ID_Organizacion') !== $orgId) {
    fwrite(STDERR, "No existe la organización {$orgId}\n");
    exit(1);
}

$idPais = (int) (DB::table('pais')->where('No_Pais', 'ECUADOR')->value('ID_Pais')
    ?: DB::table('pais')->orderBy('ID_Pais')->value('ID_Pais'));
$idTipo = (int) (DB::table('contenedor_consolidado_tipo_cliente')->whereRaw('UPPER(TRIM(name)) = ?', ['NUEVO'])->value('id')
    ?: DB::table('contenedor_consolidado_tipo_cliente')->orderBy('id')->value('id'));

if ($idPais <= 0 || $idTipo <= 0) {
    fwrite(STDERR, "Faltan país o tipo de cliente\n");
    exit(1);
}

$phoneCode = Schema::hasTable('pais_flags') && Schema::hasColumn('pais_flags', 'phone_code')
    ? (string) DB::table('pais_flags')->where('id_pais', $idPais)->value('phone_code')
    : '';

$marker = date('YmdHis');
$telefonoNuevo = ($phoneCode !== '' ? preg_replace('/\D/', '', $phoneCode) : '593') . '99' . substr($marker, -7);
$telefonoExistente = ($phoneCode !== '' ? preg_replace('/\D/', '', $phoneCode) : '593') . '98' . substr($marker, -7);

$viejos = DB::table('carga_consolidada_contenedor')
    ->where('carga', $carga)
    ->where('organizacion_id', $orgId)
    ->pluck('id');
if ($viejos->isNotEmpty()) {
    DB::table('contenedor_consolidado_cotizacion_proveedores')->whereIn('id_contenedor', $viejos)->delete();
    DB::table('contenedor_consolidado_cotizacion')->whereIn('id_contenedor', $viejos)->delete();
    DB::table('carga_consolidada_contenedor')->whereIn('id', $viejos)->delete();
}

DB::table('clientes')->where('organizacion_id', $orgId)->where('nombre', 'like', 'QA Cierre BD%')->delete();

$clientePrevioId = DB::table('clientes')->insertGetId([
    'nombre' => 'QA Cierre BD Existente ' . $marker,
    'documento' => '09' . substr($marker, -8),
    'correo' => 'qa.existente.' . $marker . '@example.com',
    'telefono' => $telefonoExistente,
    'fecha' => now()->toDateString(),
    'organizacion_id' => $orgId,
    'created_at' => now(),
    'updated_at' => now(),
]);

$contenedorId = DB::table('carga_consolidada_contenedor')->insertGetId([
    'mes' => 'SEPTIEMBRE',
    'id_pais' => $idPais,
    'organizacion_id' => $orgId,
    'carga' => $carga,
    'empresa' => 'QA Cierre BD',
    'estado' => 'PENDIENTE',
    'estado_china' => 'COMPLETADO',
    'tipo_carga' => 'CARGA CONSOLIDADA',
    'f_inicio' => now()->toDateString(),
]);

$cotizaciones = [
    [
        'nombre' => 'QA Cierre BD Nuevo ' . $marker,
        'documento' => '08' . substr($marker, -8),
        'correo' => 'qa.nuevo.' . $marker . '@example.com',
        'telefono' => $telefonoNuevo,
    ],
    [
        'nombre' => 'QA Cierre BD Reuso ' . $marker,
        'documento' => '07' . substr($marker, -8),
        'correo' => 'qa.reuso.' . $marker . '@example.com',
        'telefono' => $telefonoExistente,
    ],
];

$cotizacionIds = [];
foreach ($cotizaciones as $row) {
    $cotizacionId = DB::table('contenedor_consolidado_cotizacion')->insertGetId([
        'organizacion_id' => $orgId,
        'uuid' => (string) Str::uuid(),
        'id_contenedor' => $contenedorId,
        'id_tipo_cliente' => $idTipo,
        'fecha' => now(),
        'nombre' => $row['nombre'],
        'documento' => $row['documento'],
        'correo' => $row['correo'],
        'telefono' => $row['telefono'],
        'estado' => 'CONFIRMADO',
        'estado_cotizador' => 'CONFIRMADO',
        'estado_resumen' => 'CONFIRMADO',
        'estado_cliente' => 'RESERVADO',
        'id_cliente' => null,
    ]);
    $cotizacionIds[] = $cotizacionId;

    DB::table('contenedor_consolidado_cotizacion_proveedores')->insert([
        'organizacion_id' => $orgId,
        'id_cotizacion' => $cotizacionId,
        'id_contenedor' => $contenedorId,
        'products' => 'Item ' . $carga . ' ' . $row['nombre'],
        'estados_proveedor' => 'LOADED',
        'cbm_total' => 1.25,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

echo "Contenedor {$carga} id={$contenedorId} org={$orgId} pais={$idPais} phone_code=" . ($phoneCode ?: '-') . "\n";
echo "Corriendo job de cierre…\n";

(new ValidateCotizacionesWithLoadedProveedoresJob($contenedorId))->handle();

$resultado = DB::table('contenedor_consolidado_cotizacion as c')
    ->leftJoin('clientes as cl', 'cl.id', '=', 'c.id_cliente')
    ->whereIn('c.id', $cotizacionIds)
    ->select(
        'c.id as cotizacion_id',
        'c.nombre',
        'c.telefono as tel_cotizacion',
        'c.id_cliente',
        'cl.nombre as cliente_nombre',
        'cl.telefono as tel_cliente',
        'cl.organizacion_id'
    )
    ->get();

$ok = true;
foreach ($resultado as $row) {
    $ligado = $row->id_cliente ? 'SI' : 'NO';
    echo sprintf(
        "  cotizacion=%d | %s | tel=%s | id_cliente=%s | org=%s | %s\n",
        $row->cotizacion_id,
        $row->nombre,
        $row->tel_cotizacion,
        $row->id_cliente ?: '-',
        $row->organizacion_id ?: '-',
        $ligado === 'SI' ? 'BD actualizada' : 'NO se ligó cliente'
    );
    if (!$row->id_cliente || (int) $row->organizacion_id !== $orgId) {
        $ok = false;
    }
}

$reuso = $resultado->firstWhere('tel_cotizacion', $telefonoExistente);
if ($reuso && (int) $reuso->id_cliente === (int) $clientePrevioId) {
    echo "  Reutilizó el cliente existente id={$clientePrevioId}\n";
} else {
    echo "  FAIL no reutilizó el cliente existente id={$clientePrevioId}\n";
    $ok = false;
}

$nuevo = $resultado->firstWhere('tel_cotizacion', $telefonoNuevo);
if ($nuevo && $nuevo->id_cliente && (int) $nuevo->id_cliente !== (int) $clientePrevioId) {
    echo "  Creó cliente nuevo id={$nuevo->id_cliente}\n";
} else {
    echo "  FAIL no creó cliente nuevo\n";
    $ok = false;
}

echo $ok
    ? "OK: la BD de clientes se actualizó (busca 'QA Cierre BD' en /basedatos/clientes de org 2).\n"
    : "FAIL: el job no actualizó la BD como se esperaba.\n";

exit($ok ? 0 : 1);
