<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Inserta la actividad "Noticia" en calendar_activities.
     * Disponible para el área de Importaciones y el perfil de Contabilidad.
     */
    public function up(): void
    {
        $maxOrden = DB::table('calendar_activities')->max('orden') ?? 0;

        DB::table('calendar_activities')->insert([
            'name'             => 'Noticia',
            'orden'            => $maxOrden + 1,
            'color_code'       => '#F59E0B',
            'allow_saturday'   => false,
            'allow_sunday'     => false,
            'default_priority' => 0,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('calendar_activities')->where('name', 'Noticia')->delete();
    }
};
