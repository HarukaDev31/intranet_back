<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddBitrixSyncedAtToLandingLeadsTables extends Migration
{
    public function up()
    {
        Schema::table('landing_consolidado_leads', function (Blueprint $table) {
            if (!Schema::hasColumn('landing_consolidado_leads', 'bitrix_synced_at')) {
                $table->timestamp('bitrix_synced_at')->nullable()->after('updated_at');
            }
        });

        Schema::table('landing_curso_leads', function (Blueprint $table) {
            if (!Schema::hasColumn('landing_curso_leads', 'bitrix_synced_at')) {
                $table->timestamp('bitrix_synced_at')->nullable()->after('updated_at');
            }
        });

        // Registros previos a esta columna: evita que el cron los trate como pendientes
        // y cree negocios duplicados en Bitrix. Para forzar reintento, poner bitrix_synced_at en NULL.
        if (Schema::hasColumn('landing_consolidado_leads', 'bitrix_synced_at')) {
            DB::table('landing_consolidado_leads')
                ->whereNull('bitrix_synced_at')
                ->update(['bitrix_synced_at' => DB::raw('COALESCE(updated_at, created_at)')]);
        }
        if (Schema::hasColumn('landing_curso_leads', 'bitrix_synced_at')) {
            DB::table('landing_curso_leads')
                ->whereNull('bitrix_synced_at')
                ->update(['bitrix_synced_at' => DB::raw('COALESCE(updated_at, created_at)')]);
        }
    }

    public function down()
    {
        Schema::table('landing_consolidado_leads', function (Blueprint $table) {
            if (Schema::hasColumn('landing_consolidado_leads', 'bitrix_synced_at')) {
                $table->dropColumn('bitrix_synced_at');
            }
        });

        Schema::table('landing_curso_leads', function (Blueprint $table) {
            if (Schema::hasColumn('landing_curso_leads', 'bitrix_synced_at')) {
                $table->dropColumn('bitrix_synced_at');
            }
        });
    }
}
