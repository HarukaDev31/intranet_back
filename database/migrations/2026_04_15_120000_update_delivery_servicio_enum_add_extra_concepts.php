<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdateDeliveryServicioEnumAddExtraConcepts extends Migration
{
    private $table = 'contenedor_consolidado_cotizacion_delivery_servicio';
    private $uniqueIndex = 'uq_cccds_cotizacion_tipo';

    public function up()
    {
        if (!Schema::hasTable($this->table)) {
            return;
        }

        // Evita conflicto al crear índice único (conserva la fila más reciente por tipo).
        DB::statement(
            "DELETE t1
            FROM {$this->table} t1
            INNER JOIN {$this->table} t2
                ON t1.id_cotizacion = t2.id_cotizacion
                AND UPPER(TRIM(t1.tipo_servicio)) = UPPER(TRIM(t2.tipo_servicio))
                AND t1.id < t2.id"
        );

        DB::statement(
            "ALTER TABLE {$this->table}
            ADD COLUMN tipo_servicio_2 ENUM('DELIVERY','MONTACARGA','SANCIONES','BQ') NULL AFTER tipo_servicio"
        );

        // Copiar datos normalizados a la nueva columna (sin perder registros).
        DB::statement(
            "UPDATE {$this->table}
            SET tipo_servicio_2 = CASE UPPER(TRIM(tipo_servicio))
                WHEN 'DELIVERY' THEN 'DELIVERY'
                WHEN 'MONTACARGA' THEN 'MONTACARGA'
                WHEN 'SANCIONES' THEN 'SANCIONES'
                WHEN 'BQ' THEN 'BQ'
                ELSE 'DELIVERY'
            END"
        );

        DB::statement(
            "ALTER TABLE {$this->table}
            DROP COLUMN tipo_servicio"
        );

        DB::statement(
            "ALTER TABLE {$this->table}
            CHANGE COLUMN tipo_servicio_2 tipo_servicio ENUM('DELIVERY','MONTACARGA','SANCIONES','BQ') NOT NULL"
        );

        try {
            Schema::table($this->table, function (Blueprint $table) {
                $table->unique(['id_cotizacion', 'tipo_servicio'], 'uq_cccds_cotizacion_tipo');
            });
        } catch (\Throwable $e) {
            // El índice puede existir en algunos entornos.
        }
    }

    public function down()
    {
        if (!Schema::hasTable($this->table)) {
            return;
        }

        try {
            Schema::table($this->table, function (Blueprint $table) {
                $table->dropUnique('uq_cccds_cotizacion_tipo');
            });
        } catch (\Throwable $e) {
            // Ignorar si no existe.
        }

        // Limpia conceptos no soportados por el enum anterior.
        DB::statement(
            "DELETE FROM {$this->table} WHERE UPPER(TRIM(tipo_servicio)) IN ('SANCIONES', 'BQ')"
        );

        DB::statement(
            "ALTER TABLE {$this->table}
            ADD COLUMN tipo_servicio_2 ENUM('DELIVERY','MONTACARGA') NULL AFTER tipo_servicio"
        );

        DB::statement(
            "UPDATE {$this->table}
            SET tipo_servicio_2 = CASE UPPER(TRIM(tipo_servicio))
                WHEN 'DELIVERY' THEN 'DELIVERY'
                WHEN 'MONTACARGA' THEN 'MONTACARGA'
                ELSE 'DELIVERY'
            END"
        );

        DB::statement(
            "ALTER TABLE {$this->table}
            DROP COLUMN tipo_servicio"
        );

        DB::statement(
            "ALTER TABLE {$this->table}
            CHANGE COLUMN tipo_servicio_2 tipo_servicio ENUM('DELIVERY','MONTACARGA') NOT NULL"
        );
    }
}
