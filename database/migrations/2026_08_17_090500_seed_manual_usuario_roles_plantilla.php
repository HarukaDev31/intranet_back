<?php

use App\Services\ManualUsuario\ManualUsuarioCursoAlumnosSeeder;
use App\Services\ManualUsuario\ManualUsuarioRoleManualSeeder;
use Illuminate\Database\Migrations\Migration;

class SeedManualUsuarioRolesPlantilla extends Migration
{
    public function up()
    {
        (new ManualUsuarioRoleManualSeeder())->seed();
        (new ManualUsuarioCursoAlumnosSeeder())->seed();
    }

    public function down()
    {
        // No borra el artículo comercial/curso/alumnos (seeder dedicado).
        $keys = [
            'curso/pagos', 'curso/campanas', 'curso/planes-web', 'basedatos/clientes',
            'cargaconsolidada/abiertos', 'cargaconsolidada/completados',
            'cargaconsolidada/coordinacion/abiertos', 'cargaconsolidada/coordinacion/completados',
            'cargaconsolidada/documentacion/abiertos', 'cargaconsolidada/documentacion/completados',
            'basedatos/productos', 'basedatos/regulaciones', 'basedatos/permisos', 'basedatos/boletin-quimico',
            'cotizaciones', 'soporte-ti', 'news', 'viaticos', 'viaticos/pendientes', 'viaticos/completados',
            'calendar', 'mi-progreso', 'copiloto', 'verificacion', 'inspeccionados', 'datos-facturacion',
            'landing/leads', 'coordinacion/whatsapp-inbox',
            'panel-acceso/cargos', 'panel-acceso/usuarios', 'panel-acceso/permisos',
            'agente-compra', 'agente-compra-trading',
        ];

        \Illuminate\Support\Facades\DB::table('manual_paginas')
            ->whereIn('modulo_key', $keys)
            ->delete();
    }
}
