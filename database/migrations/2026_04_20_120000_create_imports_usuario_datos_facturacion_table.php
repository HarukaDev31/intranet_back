<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateImportsUsuarioDatosFacturacionTable extends Migration
{
    protected $table = 'imports_usuario_datos_facturacion';

    public function up()
    {
        if (!Schema::hasTable($this->table)) {
            Schema::create($this->table, function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('nombre_archivo');
                $table->string('ruta_archivo');
                $table->unsignedInteger('cantidad_rows')->default(0);
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->json('estadisticas')->nullable();
                $table->string('estado', 30)->default('COMPLETADO');
                $table->timestamp('rollback_at')->nullable();
                $table->timestamps();

                $table->index('usuario_id', 'idx_iudf_usuario_id');
                $table->index('estado', 'idx_iudf_estado');

                $table->foreign('usuario_id', 'fk_iudf_usuario_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable($this->table)) {
            Schema::drop($this->table);
        }
    }
}

