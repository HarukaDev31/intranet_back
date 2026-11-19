<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Datos de cliente de Almacén China en la organización (PRY-26-0003): tipo (socio o importador),
 * país de origen, tarifas de consolidado y contacto. Reemplaza a china_clients.
 * Todas las organizaciones existentes quedan como socio; su país de origen es el primero de sus
 * países para consolidado o, si no tiene, el país que ya tenía la organización.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizacion', function (Blueprint $table) {
            if (!Schema::hasColumn('organizacion', 'tipo_cliente')) {
                $table->string('tipo_cliente', 20)->default('socio')->comment('Tipo de cliente de Almacén China: socio o importador');
            }
            if (!Schema::hasColumn('organizacion', 'pais_origen_id')) {
                $table->unsignedInteger('pais_origen_id')->nullable()->comment('País de origen del cliente (pais.ID_Pais)');
            }
            if (!Schema::hasColumn('organizacion', 'tarifa_1')) {
                $table->decimal('tarifa_1', 12, 2)->nullable()->comment('Tarifa 1 del socio en USD por CBM (consolidados de menos de 1 CBM)');
            }
            if (!Schema::hasColumn('organizacion', 'tarifa_2')) {
                $table->decimal('tarifa_2', 12, 2)->nullable()->comment('Tarifa 2 del socio en USD por CBM (consolidados de 1 CBM o más)');
            }
            if (!Schema::hasColumn('organizacion', 'whatsapp')) {
                $table->string('whatsapp', 40)->nullable()->comment('WhatsApp de contacto del cliente');
            }
            if (!Schema::hasColumn('organizacion', 'contacto')) {
                $table->string('contacto', 150)->nullable()->comment('Persona de contacto del cliente');
            }
            if (!Schema::hasColumn('organizacion', 'correo')) {
                $table->string('correo', 150)->nullable()->comment('Correo del cliente; si existe es también su usuario de la plataforma');
            }
            if (!Schema::hasColumn('organizacion', 'fecha_registro')) {
                $table->dateTime('fecha_registro')->nullable()->comment('Fecha en que se registró como cliente de Almacén China');
            }
        });

        Schema::table('organizacion', function (Blueprint $table) {
            $table->index('pais_origen_id', 'organizacion_pais_origen_idx');
            $table->index('tipo_cliente', 'organizacion_tipo_cliente_idx');
        });

        DB::table('organizacion')->whereNull('tipo_cliente')->update(['tipo_cliente' => 'socio']);

        $firstEnabled = Schema::hasTable('organizacion_paises_habilitados')
            ? DB::table('organizacion_paises_habilitados')->orderBy('id')->get(['organizacion_id', 'id_pais'])
                ->groupBy('organizacion_id')->map(fn ($rows) => (int) $rows->first()->id_pais)
            : collect();

        foreach (DB::table('organizacion')->whereNull('pais_origen_id')->get(['ID_Organizacion', 'id_pais']) as $org) {
            $countryId = $firstEnabled->get($org->ID_Organizacion) ?: ((int) ($org->id_pais ?? 0) ?: null);
            if ($countryId) {
                DB::table('organizacion')->where('ID_Organizacion', $org->ID_Organizacion)->update(['pais_origen_id' => $countryId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('organizacion', function (Blueprint $table) {
            $table->dropIndex('organizacion_pais_origen_idx');
            $table->dropIndex('organizacion_tipo_cliente_idx');
        });
        Schema::table('organizacion', function (Blueprint $table) {
            foreach (['tipo_cliente', 'pais_origen_id', 'tarifa_1', 'tarifa_2', 'whatsapp', 'contacto', 'correo', 'fecha_registro'] as $column) {
                if (Schema::hasColumn('organizacion', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
