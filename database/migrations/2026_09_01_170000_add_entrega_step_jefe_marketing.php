<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $host = rtrim((string) (config('app.url') ?? env('APP_URL')), '/');
        $stepsToEnsure = array(
            array('name' => 'ENTREGA', 'iconURL' => $host . '/assets/icons/entrega.png'),
        );

        $contenedorIds = DB::table('carga_consolidada_contenedor')->pluck('id');

        foreach ($contenedorIds as $idContenedor) {
            $tipo = 'JEFE MARKETING';

            $existingNames = DB::table('contenedor_consolidado_order_steps')
                ->where('id_pedido', (int) $idContenedor)
                ->where('tipo', $tipo)
                ->pluck('name')
                ->map(function ($name) {
                    return strtoupper(trim((string) $name));
                })
                ->all();

            $maxOrder = (int) DB::table('contenedor_consolidado_order_steps')
                ->where('id_pedido', (int) $idContenedor)
                ->where('tipo', $tipo)
                ->max('id_order');

            $nextOrder = $maxOrder > 0 ? $maxOrder + 1 : 1;

            $rowsToInsert = array();
            foreach ($stepsToEnsure as $step) {
                if (!in_array($step['name'], $existingNames, true)) {
                    $rowsToInsert[] = array(
                        'id_pedido' => (int) $idContenedor,
                        'id_order' => $nextOrder,
                        'name' => $step['name'],
                        'iconURL' => $step['iconURL'],
                        'tipo' => $tipo,
                        'status' => 'PENDING',
                    );
                    $nextOrder++;
                }
            }

            if (!empty($rowsToInsert)) {
                DB::table('contenedor_consolidado_order_steps')->insert($rowsToInsert);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('contenedor_consolidado_order_steps')
            ->where('tipo', 'JEFE MARKETING')
            ->where('name', 'ENTREGA')
            ->delete();
    }
};
