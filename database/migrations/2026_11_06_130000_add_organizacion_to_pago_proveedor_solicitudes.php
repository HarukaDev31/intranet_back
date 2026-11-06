<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las solicitudes manuales de China son de otras organizaciones (socios), nunca de la organización 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pago_proveedor_solicitudes', function (Blueprint $table) {
            $table->unsignedInteger('organizacion_id')->nullable()->after('partner')
                ->comment('Organización socia de la solicitud manual. Vacía en las solicitudes de Ventas');
            $table->foreign('organizacion_id', 'pp_solicitud_organizacion_fk')
                ->references('ID_Organizacion')->on('organizacion')->nullOnDelete();
        });

        $organizations = DB::table('organizacion')->where('ID_Organizacion', '!=', 1)->get(['ID_Organizacion', 'No_Organizacion']);
        foreach ($organizations as $organization) {
            DB::table('pago_proveedor_solicitudes')
                ->where('origin', 'china_manual')
                ->whereNull('organizacion_id')
                ->where('partner', 'like', '%'.$organization->No_Organizacion.'%')
                ->update(['organizacion_id' => $organization->ID_Organizacion]);
        }
    }

    public function down(): void
    {
        Schema::table('pago_proveedor_solicitudes', function (Blueprint $table) {
            $table->dropForeign('pp_solicitud_organizacion_fk');
            $table->dropColumn('organizacion_id');
        });
    }
};
