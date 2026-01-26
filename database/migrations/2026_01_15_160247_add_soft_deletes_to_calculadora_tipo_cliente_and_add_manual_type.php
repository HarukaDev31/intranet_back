<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddSoftDeletesToCalculadoraTipoClienteAndAddManualType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Añadir campo deleted_at a calculadora_tipo_cliente
        Schema::table('calculadora_tipo_cliente', function (Blueprint $table) {
            $table->softDeletes();
        });

        // 2. Soft delete los IDs 2, 3 y 5
        DB::table('calculadora_tipo_cliente')
            ->whereIn('id', [2, 3, 5])
            ->update(['deleted_at' => now()]);

        // 3. Insertar nuevo tipo de cliente MANUAL
        $manualId = DB::table('calculadora_tipo_cliente')->insertGetId([
            'nombre' => 'MANUAL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. Insertar las dos tarifas para el tipo MANUAL
        DB::table('calculadora_tarifas_consolidado')->insert([
            [
                'limit_inf' => 0.1,
                'limit_sup' => 1,
                'value' => 0,
                'type' => 'PLAIN',
                'calculadora_tipo_cliente_id' => $manualId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'limit_inf' => 1,
                'limit_sup' => 999999,
                'value' => 0,
                'type' => 'STANDARD',
                'calculadora_tipo_cliente_id' => $manualId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Eliminar las tarifas del tipo MANUAL
        $manualId = DB::table('calculadora_tipo_cliente')
            ->where('nombre', 'MANUAL')
            ->value('id');

        if ($manualId) {
            DB::table('calculadora_tarifas_consolidado')
                ->where('calculadora_tipo_cliente_id', $manualId)
                ->delete();

            DB::table('calculadora_tipo_cliente')
                ->where('id', $manualId)
                ->delete();
        }

        // Restaurar los IDs 2, 3 y 5
        DB::table('calculadora_tipo_cliente')
            ->whereIn('id', [2, 3, 5])
            ->update(['deleted_at' => null]);

        // Eliminar columna deleted_at
        Schema::table('calculadora_tipo_cliente', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
}
