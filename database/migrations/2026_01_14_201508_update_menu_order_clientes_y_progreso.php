<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateMenuOrderClientesYProgreso extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Obtener el orden de Aduanas
        $aduanas = DB::table('menu')->where('No_Menu', 'Aduanas')->first();
        
        if ($aduanas) {
            $ordenAduanas = $aduanas->Nu_Orden;
            
            // Actualizar Clientes para que esté después de Aduanas
            DB::table('menu')
                ->where('No_Menu', 'Clientes')
                ->update(['Nu_Orden' => $ordenAduanas + 1]);
            
            // Actualizar Mi Progreso para que esté después de Clientes
            DB::table('menu')
                ->where('No_Menu', 'Mi Progreso')
                ->update(['Nu_Orden' => $ordenAduanas + 2]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Restaurar valores originales si es necesario
        // Puedes ajustar estos valores según el orden anterior
    }
}
