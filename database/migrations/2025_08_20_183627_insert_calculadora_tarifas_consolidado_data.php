<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class InsertCalculadoraTarifasConsolidadoData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Obtener los IDs de los tipos de cliente
        $nuevoId = DB::table('calculadora_tipo_cliente')->where('nombre', 'NUEVO')->value('id');
        $inactivoId = DB::table('calculadora_tipo_cliente')->where('nombre', 'INACTIVO')->value('id');
        $antiguoId = DB::table('calculadora_tipo_cliente')->where('nombre', 'ANTIGUO')->value('id');
        $recurrenteId = DB::table('calculadora_tipo_cliente')->where('nombre', 'RECURRENTE')->value('id');
        $premiumId = DB::table('calculadora_tipo_cliente')->where('nombre', 'PREMIUM')->value('id');
        $socioId = DB::table('calculadora_tipo_cliente')->where('nombre', 'SOCIO')->value('id');

        // Tarifas para Cliente NUEVO/INACTIVO
        $tarifasNuevoInactivo = [
            ['limit_inf' => 0.1, 'limit_sup' => 0.59, 'value' => 280, 'type' => 'PLAIN', 'calculadora_tipo_cliente_id' => $nuevoId],
            ['limit_inf' => 0.6, 'limit_sup' => 1.0, 'value' => 375, 'type' => 'PLAIN', 'calculadora_tipo_cliente_id' => $nuevoId],
            ['limit_inf' => 1.0, 'limit_sup' => 2.0, 'value' => 375, 'type' => 'STANDARD', 'calculadora_tipo_cliente_id' => $nuevoId],
            ['limit_inf' => 2.1, 'limit_sup' => 3.0, 'value' => 350, 'type' => 'STANDARD', 'calculadora_tipo_cliente_id' => $nuevoId],
            ['limit_inf' => 3.1, 'limit_sup' => 4.0, 'value' => 325, 'type' => 'STANDARD', 'calculadora_tipo_cliente_id' => $nuevoId],
            ['limit_inf' => 4.1, 'limit_sup' => 999999.99, 'value' => 300, 'type' => 'STANDARD', 'calculadora_tipo_cliente_id' => $nuevoId],
        ];

        // Tarifas para Cliente INACTIVO (mismas que NUEVO)
        $tarifasInactivo = [
            ['limit_inf' => 0.1, 'limit_sup' => 0.59, 'value' => 280, 'type' => 'PLAIN', 'calculadora_tipo_cliente_id' => $inactivoId],
            ['limit_inf' => 0.6, 'limit_sup' => 1.0, 'value' => 375, 'type' => 'PLAIN', 'calculadora_tipo_cliente_id' => $inactivoId],
            ['limit_inf' => 1.0, 'limit_sup' => 2.0, 'value' => 375, 'type' => 'STANDARD', 'calculadora_tipo_cliente_id' => $inactivoId],
            ['limit_inf' => 2.1, 'limit_sup' => 3.0, 'value' => 350, 'type' => 'STANDARD', 'calculadora_tipo_cliente_id' => $inactivoId],
            ['limit_inf' => 3.1, 'limit_sup' => 4.0, 'value' => 325, 'type' => 'STANDARD', 'calculadora_tipo_cliente_id' => $inactivoId],
            ['limit_inf' => 4.1, 'limit_sup' => 999999.99, 'value' => 300, 'type' => 'STANDARD', 'calculadora_tipo_cliente_id' => $inactivoId],
        ];

        // Tarifas para Cliente ANTIGUO/RECURRENTE
        $tarifasAntiguo = [
            ['limit_inf' => 0.1, 'limit_sup' => 0.59, 'value' => 260, 'type' => 'PLAIN', 'calculadora_tipo_cliente_id' => $antiguoId],
            ['limit_inf' => 0.6, 'limit_sup' => 1.0, 'value' => 350, 'type' => 'PLAIN', 'calculadora_tipo_cliente_id' => $antiguoId],
            ['limit_inf' => 1.0, 'limit_sup' => 2.0, 'value' => 350, 'type' => 'STANDARD', 'calculadora_tipo_cliente_id' => $antiguoId],
            ['limit_inf' => 2.1, 'limit_sup' => 3.0, 'value' => 325, 'type' => 'STANDARD', 'calculadora_tipo_cliente_id' => $antiguoId],
            ['limit_inf' => 3.1, 'limit_sup' => 4.0, 'value' => 300, 'type' => 'STANDARD', 'calculadora_tipo_cliente_id' => $antiguoId],
            ['limit_inf' => 4.1, 'limit_sup' => 999999.99, 'value' => 280, 'type' => 'STANDARD', 'calculadora_tipo_cliente_id' => $antiguoId],
        ];

        // Tarifas para Cliente RECURRENTE (mismas que ANTIGUO)
        $tarifasRecurrente = [
            ['limit_inf' => 0.1, 'limit_sup' => 0.59, 'value' => 260, 'type' => 'PLAIN', 'calculadora_tipo_cliente_id' => $recurrenteId],
            ['limit_inf' => 0.6, 'limit_sup' => 1.0, 'value' => 350, 'type' => 'PLAIN', 'calculadora_tipo_cliente_id' => $recurrenteId],
            ['limit_inf' => 1.0, 'limit_sup' => 2.0, 'value' => 350, 'type' => 'STANDARD', 'calculadora_tipo_cliente_id' => $recurrenteId],
            ['limit_inf' => 2.1, 'limit_sup' => 3.0, 'value' => 325, 'type' => 'STANDARD', 'calculadora_tipo_cliente_id' => $recurrenteId],
            ['limit_inf' => 3.1, 'limit_sup' => 4.0, 'value' => 300, 'type' => 'STANDARD', 'calculadora_tipo_cliente_id' => $recurrenteId],
            ['limit_inf' => 4.1, 'limit_sup' => 999999.99, 'value' => 280, 'type' => 'STANDARD', 'calculadora_tipo_cliente_id' => $recurrenteId],
        ];

        // Tarifas para Cliente PREMIUM/SOCIO
        $tarifasPremium = [
            ['limit_inf' => 0.1, 'limit_sup' => 0.59, 'value' => 260, 'type' => 'PLAIN', 'calculadora_tipo_cliente_id' => $premiumId],
            ['limit_inf' => 0.6, 'limit_sup' => 1.0, 'value' => 350, 'type' => 'PLAIN', 'calculadora_tipo_cliente_id' => $premiumId],
            ['limit_inf' => 1.0, 'limit_sup' => 2.0, 'value' => 325, 'type' => 'STANDARD', 'calculadora_tipo_cliente_id' => $premiumId],
            ['limit_inf' => 2.1, 'limit_sup' => 3.0, 'value' => 300, 'type' => 'STANDARD', 'calculadora_tipo_cliente_id' => $premiumId],
            ['limit_inf' => 3.1, 'limit_sup' => 4.0, 'value' => 290, 'type' => 'STANDARD', 'calculadora_tipo_cliente_id' => $premiumId],
            ['limit_inf' => 4.1, 'limit_sup' => 999999.99, 'value' => 280, 'type' => 'STANDARD', 'calculadora_tipo_cliente_id' => $premiumId],
        ];

        // Tarifas para Cliente SOCIO (mismas que PREMIUM)
        $tarifasSocio = [
            ['limit_inf' => 0.1, 'limit_sup' => 0.59, 'value' => 280, 'type' => 'PLAIN', 'calculadora_tipo_cliente_id' => $socioId],
            ['limit_inf' => 0.6, 'limit_sup' => 1.0, 'value' => 280, 'type' => 'PLAIN', 'calculadora_tipo_cliente_id' => $socioId],
            ['limit_inf' => 1.0, 'limit_sup' => 2.0, 'value' => 280, 'type' => 'STANDARD', 'calculadora_tipo_cliente_id' => $socioId],
            ['limit_inf' => 2.1, 'limit_sup' => 3.0, 'value' => 280, 'type' => 'STANDARD', 'calculadora_tipo_cliente_id' => $socioId],
            ['limit_inf' => 3.1, 'limit_sup' => 4.0, 'value' => 280, 'type' => 'STANDARD', 'calculadora_tipo_cliente_id' => $socioId],
            ['limit_inf' => 4.1, 'limit_sup' => 999999.99, 'value' => 280, 'type' => 'STANDARD', 'calculadora_tipo_cliente_id' => $socioId],
        ];

        // Combinar todas las tarifas
        $todasTarifas = array_merge(
            $tarifasNuevoInactivo,
            $tarifasInactivo,
            $tarifasAntiguo,
            $tarifasRecurrente,
            $tarifasPremium,
            $tarifasSocio
        );

        // Agregar timestamps a cada tarifa
        foreach ($todasTarifas as &$tarifa) {
            $tarifa['created_at'] = now();
            $tarifa['updated_at'] = now();
        }

        // Insertar todas las tarifas
        DB::table('calculadora_tarifas_consolidado')->insert($todasTarifas);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Eliminar todas las tarifas insertadas
        DB::table('calculadora_tarifas_consolidado')->truncate();
    }
}
