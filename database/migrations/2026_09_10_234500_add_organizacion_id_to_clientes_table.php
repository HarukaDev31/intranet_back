<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BD clientes deja de ser global: cada fila pertenece a una org.
 * El job de cierre copia organizacion_id de la cotización (padre).
 */
class AddOrganizacionIdToClientesTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('clientes') || Schema::hasColumn('clientes', 'organizacion_id')) {
            return;
        }

        Schema::table('clientes', function (Blueprint $table) {
            $table->unsignedInteger('organizacion_id')->nullable()->after('id');
            $table->index('organizacion_id', 'idx_clientes_organizacion_id');
        });

        if (Schema::hasTable('contenedor_consolidado_cotizacion')) {
            DB::statement('
                UPDATE clientes c
                INNER JOIN (
                    SELECT id_cliente, MIN(organizacion_id) as organizacion_id
                    FROM contenedor_consolidado_cotizacion
                    WHERE id_cliente IS NOT NULL
                      AND organizacion_id IS NOT NULL
                      AND deleted_at IS NULL
                    GROUP BY id_cliente
                ) x ON x.id_cliente = c.id
                SET c.organizacion_id = x.organizacion_id
                WHERE c.organizacion_id IS NULL
            ');
        }

        $orgDefault = DB::table('organizacion')->orderBy('ID_Organizacion')->value('ID_Organizacion');
        if ($orgDefault === null) {
            $orgDefault = 1;
        }

        DB::table('clientes')
            ->whereNull('organizacion_id')
            ->update(['organizacion_id' => $orgDefault]);

        if (Schema::hasTable('organizacion')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->foreign('organizacion_id', 'fk_clientes_organizacion_id')
                    ->references('ID_Organizacion')
                    ->on('organizacion')
                    ->onDelete('restrict');
            });
        }
    }

    public function down()
    {
        if (!Schema::hasColumn('clientes', 'organizacion_id')) {
            return;
        }

        Schema::table('clientes', function (Blueprint $table) {
            try {
                $table->dropForeign('fk_clientes_organizacion_id');
            } catch (\Exception $e) {
                // FK puede no haberse creado si organizacion no existía.
            }
            $table->dropIndex('idx_clientes_organizacion_id');
            $table->dropColumn('organizacion_id');
        });
    }
}
