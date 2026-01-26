<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class UpdateDocCotizacionStepsToClientes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Actualiza filas cuyo name (case-insensitive) sea 'cotizacion' y tipo = 'DOCUMENTACION'
        $rows = DB::table('contenedor_consolidado_order_steps')
            ->whereRaw('LOWER(name) = ?', ['cotizacion'])
            ->where('tipo', 'DOCUMENTACION')
            ->get(['id']);

        if ($rows->count() === 0) {
            return;
        }

        DB::table('contenedor_consolidado_order_steps')
            ->whereRaw('LOWER(name) = ?', ['cotizacion'])
            ->where('tipo', 'DOCUMENTACION')
            ->update([
                'name' => 'CLIENTES',
                'iconURL' => 'https://intranetback.probusiness.pe/assets/icons/clientes.png',
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Revertir sólo las filas que fueron cambiadas a CLIENTES y que tengan el icono de clientes.png
        DB::table('contenedor_consolidado_order_steps')
            ->where('name', 'CLIENTES')
            ->where('tipo', 'CLIENTES')
            ->where('iconURL', 'https://intranetback.probusiness.pe/assets/icons/clientes.png')
            ->update([
                'tipo' => 'DOCUMENTACION',
                'name' => 'Cotizacion',
                'iconURL' => 'https://intranetback.probusiness.pe/assets/icons/cotizacion.png',
            ]);
    }
}
