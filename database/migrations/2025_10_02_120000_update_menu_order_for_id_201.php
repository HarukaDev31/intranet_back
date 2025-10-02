<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class UpdateMenuOrderForId201 extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('menu')
            ->where('ID_Menu', 201)
            ->update(['Nu_Orden' => 1]);
    }

    /**
     * Reverse the migrations.
     *
     * Nota: No conocemos el valor anterior de Nu_Orden de forma confiable.
     * Dejamos este método sin cambios para evitar sobrescribir datos.
     */
    public function down(): void
    {
        // Restaurar el valor anterior conocido
        DB::table('menu')
            ->where('ID_Menu', 201)
            ->update(['Nu_Orden' => 10]);
    }
}
