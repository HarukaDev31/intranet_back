<?php

/**
 * Borra clientes y consolidados de org != 1 y siembra 2 abiertos (Ecuador)
 * con 10 cotizaciones, 7 clientes distintos y algunos proveedores LOADED.
 *
 * php tests/reset_socios_dos_consolidados.php
 */
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\CargaConsolidada\ContenedorController;
use App\Models\CargaConsolidada\Contenedor;
use App\Services\CalculadoraImportacion\CodeSupplierHelper;
use App\Support\CargaConsolidada\CargaLabel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

$orgId = 2;

if ((int) DB::table('organizacion')->where('ID_Organizacion', $orgId)->value('ID_Organizacion') !== $orgId) {
    fwrite(STDERR, "No existe la organización {$orgId}\n");
    exit(1);
}

$idPais = (int) (DB::table('pais')->where('No_Pais', 'ECUADOR')->value('ID_Pais')
    ?: DB::table('pais')->orderBy('ID_Pais')->value('ID_Pais'));
$idTipo = (int) (DB::table('contenedor_consolidado_tipo_cliente')->whereRaw('UPPER(TRIM(name)) = ?', ['NUEVO'])->value('id')
    ?: DB::table('contenedor_consolidado_tipo_cliente')->orderBy('id')->value('id'));
$vendedores = DB::table('usuario')
    ->where('ID_Organizacion', $orgId)
    ->where('Nu_Estado', 1)
    ->orderBy('ID_Usuario')
    ->pluck('ID_Usuario')
    ->map(function ($id) {
        return (int) $id;
    })
    ->values()
    ->all();

if ($idPais <= 0 || $idTipo <= 0 || $vendedores === []) {
    fwrite(STDERR, "Faltan país, tipo de cliente o vendedores de org {$orgId}\n");
    exit(1);
}

$phoneCode = Schema::hasTable('pais_flags') && Schema::hasColumn('pais_flags', 'phone_code')
    ? preg_replace('/\D/', '', (string) DB::table('pais_flags')->where('id_pais', $idPais)->value('phone_code'))
    : '593';
if ($phoneCode === '') {
    $phoneCode = '593';
}

$nombreOrg = (string) DB::table('organizacion')->where('ID_Organizacion', $orgId)->value('No_Organizacion');

$contenedorIds = DB::table('carga_consolidada_contenedor')
    ->where('organizacion_id', '!=', 1)
    ->pluck('id')
    ->map(function ($id) {
        return (int) $id;
    })
    ->all();

$cotizacionIds = DB::table('contenedor_consolidado_cotizacion')
    ->where(function ($q) use ($contenedorIds) {
        $q->where('organizacion_id', '!=', 1);
        if ($contenedorIds !== []) {
            $q->orWhereIn('id_contenedor', $contenedorIds);
        }
    })
    ->pluck('id')
    ->map(function ($id) {
        return (int) $id;
    })
    ->all();

$proveedorIds = $cotizacionIds === []
    ? []
    : DB::table('contenedor_consolidado_cotizacion_proveedores')
        ->whereIn('id_cotizacion', $cotizacionIds)
        ->pluck('id')
        ->map(function ($id) {
            return (int) $id;
        })
        ->all();

$database = DB::getDatabaseName();
$esTablaConsolidado = function ($tabla) {
    return (bool) preg_match('/^(carga_consolidada|contenedor_consolidado|contenedor_seguimiento)/', (string) $tabla);
};
$tablasContenedor = array_values(array_filter(
    DB::table('information_schema.COLUMNS')
        ->where('TABLE_SCHEMA', $database)
        ->where('COLUMN_NAME', 'id_contenedor')
        ->pluck('TABLE_NAME')
        ->all(),
    $esTablaConsolidado
));
$tablasCotizacion = array_values(array_filter(
    DB::table('information_schema.COLUMNS')
        ->where('TABLE_SCHEMA', $database)
        ->where('COLUMN_NAME', 'id_cotizacion')
        ->pluck('TABLE_NAME')
        ->all(),
    $esTablaConsolidado
));
$tablasProveedor = array_values(array_filter(
    DB::table('information_schema.COLUMNS')
        ->where('TABLE_SCHEMA', $database)
        ->where('COLUMN_NAME', 'id_proveedor')
        ->pluck('TABLE_NAME')
        ->all(),
    $esTablaConsolidado
));

DB::statement('SET FOREIGN_KEY_CHECKS=0');

try {
    if ($proveedorIds !== []) {
        foreach ($tablasProveedor as $tabla) {
            if (!Schema::hasTable($tabla)) {
                continue;
            }
            DB::table($tabla)->whereIn('id_proveedor', $proveedorIds)->delete();
        }
    }
    if ($cotizacionIds !== []) {
        foreach ($tablasCotizacion as $tabla) {
            if (!Schema::hasTable($tabla) || $tabla === 'contenedor_consolidado_cotizacion') {
                continue;
            }
            DB::table($tabla)->whereIn('id_cotizacion', $cotizacionIds)->delete();
        }
        DB::table('contenedor_consolidado_cotizacion')->whereIn('id', $cotizacionIds)->delete();
    }
    if ($contenedorIds !== []) {
        foreach ($tablasContenedor as $tabla) {
            if (!Schema::hasTable($tabla) || $tabla === 'carga_consolidada_contenedor') {
                continue;
            }
            DB::table($tabla)->whereIn('id_contenedor', $contenedorIds)->delete();
        }
        if (Schema::hasTable('contenedor_consolidado_order_steps')) {
            DB::table('contenedor_consolidado_order_steps')->whereIn('id_pedido', $contenedorIds)->delete();
        }
        DB::table('carga_consolidada_contenedor')->whereIn('id', $contenedorIds)->delete();
    }

    $clientesBorrados = DB::table('clientes')->where('organizacion_id', '!=', 1)->delete();
} finally {
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
}

echo 'Borrado org ≠ 1: contenedores=' . count($contenedorIds)
    . ' cotizaciones=' . count($cotizacionIds)
    . ' clientes=' . $clientesBorrados . "\n";

$nombres = [
    'Ana Perez', 'Carlos Mendoza', 'Diego Salazar', 'Elena Rios',
    'Fernando Vega', 'Gabriela Leon', 'Hugo Castro', 'Isabel Morales', 'Javier Guerrero',
];
$productos = [
    'Lamparas LED',
    'Baterias y cargadores',
    'Ropa deportiva',
    'Cosmeticos',
    'Herramientas',
    'Muebles plegables',
    'Juguetes',
    'Accesorios celular',
];

$now = now();
$created = [];

foreach ([1, 2] as $nCarga) {
    $qtyClientes = 5 + (($nCarga + 1) % 5);
    if ($qtyClientes > 9) {
        $qtyClientes = 9;
    }
    $clientesPool = array_slice($nombres, 0, $qtyClientes);

    $contenedor = Contenedor::create([
        'mes' => 'SEPTIEMBRE',
        'id_pais' => $idPais,
        'organizacion_id' => $orgId,
        'carga' => (string) $nCarga,
        'empresa' => 'Probusiness Ecuador',
        'estado' => 'PENDIENTE',
        'estado_china' => Contenedor::CONTEDOR_PENDIENTE,
        'estado_documentacion' => 'PENDIENTE',
        'estado_finanzas' => 'PENDIENTE',
        'tipo_carga' => 'CARGA CONSOLIDADA',
        'f_inicio' => $now->toDateString(),
        'f_cierre' => $now->copy()->addDays(18)->toDateString(),
        'f_puerto' => $now->copy()->addDays(32)->toDateString(),
        'f_entrega' => $now->copy()->addDays(40)->toDateString(),
        'limite_cbm_imo' => 100,
        'lista_embarque_url' => null,
    ]);

    $contenedorId = (int) $contenedor->getKey();
    app(ContenedorController::class)->generateSteps($contenedorId);

    $loadedCount = 0;
    $codeIndex = 1;
    for ($i = 1; $i <= 10; $i++) {
        $cliente = $clientesPool[($i - 1) % $qtyClientes];
        $doc = '09' . sprintf('%02d', $nCarga) . sprintf('%06d', (($i - 1) % $qtyClientes) + 1);
        $tel = $phoneCode . '98' . sprintf('%02d', $nCarga) . sprintf('%05d', (($i - 1) % $qtyClientes) + 1);
        $correo = 'socio.ec' . $nCarga . '.c' . ((($i - 1) % $qtyClientes) + 1) . '@example.com';
        $loaded = in_array($i, [1, 2, 3, 6, 8], true);
        if ($loaded) {
            $loadedCount++;
        }

        $cbm = round(1.1 + ($i * 0.35), 2);
        $fob = 350 + ($i * 80);
        $logistica = 70 + ($i * 12);
        $impuesto = 50 + ($i * 8);
        $vendedorId = $vendedores[($i - 1) % count($vendedores)];

        $cotizacionId = DB::table('contenedor_consolidado_cotizacion')->insertGetId([
            'organizacion_id' => $orgId,
            'uuid' => (string) Str::uuid(),
            'id_contenedor' => $contenedorId,
            'id_usuario' => $vendedorId,
            'id_tipo_cliente' => $idTipo,
            'fecha' => $now,
            'nombre' => $cliente,
            'documento' => $doc,
            'correo' => $correo,
            'telefono' => $tel,
            'estado' => 'CONFIRMADO',
            'estado_cotizador' => 'CONFIRMADO',
            'estado_resumen' => 'CONFIRMADO',
            'estado_cliente' => $loaded ? 'RESERVADO' : null,
            'fecha_confirmacion' => $now,
            'from_calculator' => 0,
            'volumen' => $cbm,
            'fob' => $fob,
            'monto' => $logistica,
            'impuestos' => $impuesto,
            'tarifa' => 80 + $i,
            'peso' => 40 + ($i * 8),
            'id_cliente' => null,
            'updated_at' => $now,
        ]);

        $dosProveedores = $i % 4 === 0;
        $slots = $dosProveedores ? 2 : 1;
        for ($slot = 1; $slot <= $slots; $slot++) {
            $provLoaded = $loaded && $slot === 1;
            $code = CodeSupplierHelper::generateWithOrgPrefix($nombreOrg, $cliente, (string) $nCarga, $codeIndex);
            $codeIndex++;

            DB::table('contenedor_consolidado_cotizacion_proveedores')->insert([
                'organizacion_id' => $orgId,
                'id_cotizacion' => $cotizacionId,
                'id_contenedor' => $contenedorId,
                'modo_cotizacion' => 'resumen',
                'products' => $productos[($i + $slot) % count($productos)],
                'estados_proveedor' => $provLoaded ? 'LOADED' : 'WAIT',
                'cbm_total' => $slot === 1 ? $cbm : 0.8,
                'cbm_imo' => 0,
                'peso' => 40 + ($i * 6),
                'qty_box' => 8 + $i,
                'tipo_rotulado' => 'pendiente',
                'code_supplier' => $code,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    $label = CargaLabel::format((string) $nCarga, $now->toDateString());
    $created[] = [
        'id' => $contenedorId,
        'label' => $label,
        'clientes' => $qtyClientes,
        'loaded' => $loadedCount,
    ];
    echo "Creado {$label} id={$contenedorId} | 10 cotizaciones | {$qtyClientes} clientes | {$loadedCount} con proveedor LOADED\n";
}

echo "Listo. China: /cargaconsolidada/abiertos busca 1-2026 o 2-2026. Org 1 no se tocó.\n";
echo "BD clientes org 2 está vacía hasta que subas packing list y corra la cola importaciones.\n";
