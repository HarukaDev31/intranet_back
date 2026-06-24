<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateContenedorProveedorEstadosProveedorHistoryTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('contenedor_proveedor_estados_proveedor_history')) {
            Schema::create('contenedor_proveedor_estados_proveedor_history', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('id_proveedor');
                $table->unsignedInteger('id_contenedor')->nullable();
                $table->string('estado', 64)->nullable();
                $table->string('source', 64)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['id_proveedor', 'created_at'], 'idx_cpeph_proveedor_created');
                $table->index(['id_contenedor', 'id_proveedor'], 'idx_cpeph_contenedor_proveedor');
            });
        }

        $this->backfillCurrentEstados();
    }

    public function down()
    {
        Schema::dropIfExists('contenedor_proveedor_estados_proveedor_history');
    }

    private function backfillCurrentEstados(): void
    {
        if (!Schema::hasTable('contenedor_proveedor_estados_proveedor_history')) {
            return;
        }

        $now = now();

        DB::table('contenedor_consolidado_cotizacion_proveedores')
            ->select('id', 'id_contenedor', 'estados_proveedor')
            ->whereNotNull('estados_proveedor')
            ->where('estados_proveedor', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($proveedores) use ($now) {
                $rows = [];

                foreach ($proveedores as $proveedor) {
                    $rows[] = [
                        'id_proveedor' => (int) $proveedor->id,
                        'id_contenedor' => $proveedor->id_contenedor ? (int) $proveedor->id_contenedor : null,
                        'estado' => strtoupper(trim((string) $proveedor->estados_proveedor)),
                        'source' => 'backfill',
                        'created_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('contenedor_proveedor_estados_proveedor_history')->insert($rows);
                }
            });
    }
}
