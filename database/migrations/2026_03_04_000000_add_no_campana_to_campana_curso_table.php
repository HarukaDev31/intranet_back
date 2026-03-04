<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campana_curso', function (Blueprint $table) {
            $table->string('No_Campana', 100)->nullable()->after('Fe_Creacion');
        });

        // Fill existing rows with "Mes Año" format based on Fe_Inicio
        $meses_es = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];

        $campanas = DB::table('campana_curso')->get(['ID_Campana', 'Fe_Inicio']);
        foreach ($campanas as $campana) {
            $fecha = $campana->Fe_Inicio;
            if ($fecha) {
                $mes = (int) date('n', strtotime($fecha));
                $anio = date('Y', strtotime($fecha));
                $nombre = ($meses_es[$mes] ?? 'Mes') . ' ' . $anio;
            } else {
                $nombre = 'Campaña ' . $campana->ID_Campana;
            }
            DB::table('campana_curso')
                ->where('ID_Campana', $campana->ID_Campana)
                ->update(['No_Campana' => $nombre]);
        }
    }

    public function down(): void
    {
        Schema::table('campana_curso', function (Blueprint $table) {
            $table->dropColumn('No_Campana');
        });
    }
};
