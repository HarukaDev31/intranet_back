<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MarkExistingLandingLeadsAsBitrixSyncedNow extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('landing_consolidado_leads', 'bitrix_synced_at')) {
            DB::table('landing_consolidado_leads')
                ->whereNull('bitrix_synced_at')
                ->update(['bitrix_synced_at' => now()]);
        }

        if (Schema::hasColumn('landing_curso_leads', 'bitrix_synced_at')) {
            DB::table('landing_curso_leads')
                ->whereNull('bitrix_synced_at')
                ->update(['bitrix_synced_at' => now()]);
        }
    }

    public function down()
    {
        // No-op: esta migración solo marca estado histórico.
    }
}

