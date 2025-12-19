<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\SystemNews;

class InsertSystemNewsAvancesVistaMovilVerificacion extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Obtener el primer usuario disponible para created_by
        $firstUserId = DB::table('usuario')->value('ID_Usuario');
        if (!$firstUserId) {
            $firstUserId = 1;
        }

        $now = now();

        DB::table('system_news')->insert([
            'title' => 'Avances: filtro Cotizador, vista móvil y verificación en formularios',
            'content' => '<p>Se añadió un filtro por <strong>estado</strong> para el rol <em>Cotizador</em>, se completó la vista móvil para los roles de Cotizador, Administración y Documentación, y se incorporó un <strong>estado de verificación</strong> en los formularios de entrega de clientes para el perfil de Administración. Estas mejoras facilitan la gestión desde dispositivos móviles y permiten que el equipo de Administración valide y marque formularios como verificados.</p>',
            'summary' => 'Filtro por estado para Cotizador; vista móvil para Cotizador, Administración y Documentación; estado de verificación en formularios de entregas para Administración.',
            'type' => SystemNews::TYPE_UPDATE,
            'is_published' => true,
            'published_at' => $now->toDateString(),
            'created_by' => $firstUserId,
            'created_by_name' => 'Sistema',
            'solicitada_por' => SystemNews::SOLICITADA_POR_ADMINISTRACION,
            'redirect' => null,
            'created_at' => $now,
            'updated_at' => $now
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('system_news')
            ->where('title', 'Avances: filtro Cotizador, vista móvil y verificación en formularios')
            ->delete();
    }
}
