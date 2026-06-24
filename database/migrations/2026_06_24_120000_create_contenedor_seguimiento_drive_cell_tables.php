<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContenedorSeguimientoDriveCellTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('contenedor_seguimiento_drive_snapshots')) {
            Schema::create('contenedor_seguimiento_drive_snapshots', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('id_contenedor');
                $table->string('drive_file_id', 128)->nullable();
                $table->string('file_name', 255)->nullable();
                $table->string('trigger', 32);
                $table->unsignedInteger('cells_upserted')->default(0);
                $table->unsignedInteger('cells_history')->default(0);
                $table->string('status', 16)->default('ok');
                $table->text('error')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['id_contenedor', 'created_at'], 'idx_cssds_contenedor_created');
            });
        }

        if (!Schema::hasTable('contenedor_seguimiento_drive_cells')) {
            Schema::create('contenedor_seguimiento_drive_cells', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('id_contenedor');
                $table->string('sheet_name', 64);
                $table->string('row_key', 128);
                $table->string('column_key', 64);
                $table->unsignedInteger('id_cotizacion')->nullable();
                $table->unsignedInteger('id_proveedor')->nullable();
                $table->string('cell_ref', 16)->nullable();
                $table->unsignedSmallInteger('row_number')->nullable();
                $table->string('column_letter', 4)->nullable();
                $table->text('cell_value')->nullable();
                $table->boolean('is_manual')->default(false);
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                $table->unique(
                    ['id_contenedor', 'sheet_name', 'row_key', 'column_key'],
                    'uniq_cssdc_cell'
                );
                $table->index(['id_contenedor', 'sheet_name', 'is_manual'], 'idx_cssdc_manual');
                $table->index(['id_contenedor', 'id_proveedor'], 'idx_cssdc_proveedor');
            });
        }

        if (!Schema::hasTable('contenedor_seguimiento_drive_cell_history')) {
            Schema::create('contenedor_seguimiento_drive_cell_history', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('cell_id')->nullable();
                $table->unsignedBigInteger('snapshot_id')->nullable();
                $table->unsignedInteger('id_contenedor');
                $table->string('sheet_name', 64);
                $table->string('row_key', 128);
                $table->string('column_key', 64);
                $table->string('cell_ref', 16)->nullable();
                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();
                $table->string('change_source', 32);
                $table->timestamp('created_at')->nullable();

                $table->index(['id_contenedor', 'created_at'], 'idx_cssdch_contenedor_created');
                $table->index(['cell_id'], 'idx_cssdch_cell');
                $table->index(['snapshot_id'], 'idx_cssdch_snapshot');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('contenedor_seguimiento_drive_cell_history');
        Schema::dropIfExists('contenedor_seguimiento_drive_cells');
        Schema::dropIfExists('contenedor_seguimiento_drive_snapshots');
    }
}
