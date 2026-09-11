<?php

/**
 * Crea un consolidado ABIERTO (org 2) con cotizaciones CONFIRMADO
 * y proveedores LOADED, listo para que Almacén China suba el packing list.
 *
 * php tests/seed_abierto_packing_list_china.php
 */
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\CargaConsolidada\ContenedorController;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\ContenedorPasos;
use App\Services\CalculadoraImportacion\CodeSupplierHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

$carga = '98';
$orgId = 2;

if ((int) DB::table('organizacion')->where('ID_Organizacion', $orgId)->value('ID_Organizacion') !== $orgId) {
    fwrite(STDERR, "No existe la organización {$orgId}\n");
    exit(1);
}

$idPais = (int) (DB::table('pais')->where('No_Pais', 'ECUADOR')->value('ID_Pais')
    ?: DB::table('pais')->orderBy('ID_Pais')->value('ID_Pais'));
$idTipo = (int) (DB::table('contenedor_consolidado_tipo_cliente')->whereRaw('UPPER(TRIM(name)) = ?', ['NUEVO'])->value('id')
    ?: DB::table('contenedor_consolidado_tipo_cliente')->orderBy('id')->value('id'));
$vendedorId = (int) (DB::table('usuario')
    ->where('ID_Organizacion', $orgId)
    ->where('Nu_Estado', 1)
    ->orderBy('ID_Usuario')
    ->value('ID_Usuario'));

if ($idPais <= 0 || $idTipo <= 0 || $vendedorId <= 0) {
    fwrite(STDERR, "Faltan país, tipo de cliente o vendedor de org {$orgId}\n");
    exit(1);
}

$phoneCode = Schema::hasTable('pais_flags') && Schema::hasColumn('pais_flags', 'phone_code')
    ? preg_replace('/\D/', '', (string) DB::table('pais_flags')->where('id_pais', $idPais)->value('phone_code'))
    : '593';
if ($phoneCode === '') {
    $phoneCode = '593';
}

$viejos = DB::table('carga_consolidada_contenedor')
    ->where('organizacion_id', $orgId)
    ->where(function ($q) use ($carga) {
        $q->where('carga', $carga)
            ->orWhere('carga', 'QA-PL-CHINA')
            ->orWhere('empresa', 'QA Packing China');
    })
    ->pluck('id');
if ($viejos->isNotEmpty()) {
    DB::table('contenedor_consolidado_cotizacion_proveedores')->whereIn('id_contenedor', $viejos)->delete();
    DB::table('contenedor_consolidado_cotizacion')->whereIn('id_contenedor', $viejos)->delete();
    ContenedorPasos::query()->whereIn('id_pedido', $viejos)->delete();
    DB::table('carga_consolidada_contenedor')->whereIn('id', $viejos)->delete();
}

$marker = date('YmdHis');
$telefonoNuevo = $phoneCode . '97' . substr($marker, -7);
$telefonoExistente = $phoneCode . '96' . substr($marker, -7);

$clientePrevioId = DB::table('clientes')->insertGetId([
    'nombre' => 'QA PL Existente ' . $marker,
    'documento' => '09' . substr($marker, -8),
    'correo' => 'qa.pl.existente.' . $marker . '@example.com',
    'telefono' => $telefonoExistente,
    'fecha' => now()->toDateString(),
    'organizacion_id' => $orgId,
    'created_at' => now(),
    'updated_at' => now(),
]);

$contenedor = Contenedor::create([
    'mes' => 'SEPTIEMBRE',
    'id_pais' => $idPais,
    'organizacion_id' => $orgId,
    'carga' => $carga,
    'empresa' => 'QA Packing China',
    'estado' => 'PENDIENTE',
    'estado_china' => Contenedor::CONTEDOR_PENDIENTE,
    'estado_documentacion' => 'PENDIENTE',
    'estado_finanzas' => 'PENDIENTE',
    'tipo_carga' => 'CARGA CONSOLIDADA',
    'f_inicio' => now()->toDateString(),
    'f_cierre' => now()->addDays(20)->toDateString(),
    'f_puerto' => now()->addDays(35)->toDateString(),
    'f_entrega' => now()->addDays(42)->toDateString(),
    'limite_cbm_imo' => 100,
    'lista_embarque_url' => null,
    'lista_embarque_uploaded_at' => null,
]);

$contenedorId = (int) $contenedor->getKey();
app(ContenedorController::class)->generateSteps($contenedorId);

$nombreOrg = (string) DB::table('organizacion')->where('ID_Organizacion', $orgId)->value('No_Organizacion');
$now = now()->toDateTimeString();

$cotizaciones = [
    [
        'nombre' => 'QA PL Nuevo ' . $marker,
        'documento' => '08' . substr($marker, -8),
        'correo' => 'qa.pl.nuevo.' . $marker . '@example.com',
        'telefono' => $telefonoNuevo,
        'producto' => 'Lamparas LED QA PL',
    ],
    [
        'nombre' => 'QA PL Reuso ' . $marker,
        'documento' => '07' . substr($marker, -8),
        'correo' => 'qa.pl.reuso.' . $marker . '@example.com',
        'telefono' => $telefonoExistente,
        'producto' => 'Cargadores QA PL',
    ],
];

$suffix = 1;
foreach ($cotizaciones as $row) {
    $cotizacionId = DB::table('contenedor_consolidado_cotizacion')->insertGetId([
        'organizacion_id' => $orgId,
        'uuid' => (string) Str::uuid(),
        'id_contenedor' => $contenedorId,
        'id_usuario' => $vendedorId,
        'id_tipo_cliente' => $idTipo,
        'fecha' => $now,
        'nombre' => $row['nombre'],
        'documento' => $row['documento'],
        'correo' => $row['correo'],
        'telefono' => $row['telefono'],
        'estado' => 'CONFIRMADO',
        'estado_cotizador' => 'CONFIRMADO',
        'estado_resumen' => 'CONFIRMADO',
        'estado_cliente' => 'RESERVADO',
        'fecha_confirmacion' => $now,
        'from_calculator' => 0,
        'volumen' => 1.25,
        'fob' => 400,
        'monto' => 80,
        'impuestos' => 60,
        'tarifa' => 85,
        'id_cliente' => null,
        'updated_at' => $now,
    ]);

    $code = CodeSupplierHelper::generateWithOrgPrefix($nombreOrg, $row['nombre'], $carga, $suffix);
    $suffix++;

    DB::table('contenedor_consolidado_cotizacion_proveedores')->insert([
        'organizacion_id' => $orgId,
        'id_cotizacion' => $cotizacionId,
        'id_contenedor' => $contenedorId,
        'modo_cotizacion' => 'resumen',
        'products' => $row['producto'],
        'estados_proveedor' => 'LOADED',
        'cbm_total' => 1.25,
        'cbm_imo' => 0,
        'peso' => 80,
        'qty_box' => 10,
        'tipo_rotulado' => 'pendiente',
        'code_supplier' => $code,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

echo "Contenedor ABIERTO listo para packing list de China\n";
echo "  carga={$carga} id={$contenedorId} org={$orgId} estado_china=PENDIENTE\n";
echo "  Cotización nueva: {$telefonoNuevo} (debe CREAR cliente)\n";
echo "  Cotización reuso: {$telefonoExistente} (debe reutilizar cliente {$clientePrevioId})\n";
echo "  China: /cargaconsolidada/abiertos → busca {$carga} → Packing List\n";
echo "  Tras subir: cola importaciones (php artisan queue:work --queue=importaciones)\n";
echo "  Luego /basedatos/clientes org 2 busca 'QA PL'\n";
