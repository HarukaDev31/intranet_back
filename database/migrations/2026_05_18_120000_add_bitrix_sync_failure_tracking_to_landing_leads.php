<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBitrixSyncFailureTrackingToLandingLeads extends Migration
{
    public function up(): void
    {
        foreach (['landing_consolidado_leads', 'landing_curso_leads'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'bitrix_sync_errors')) {
                    $table->unsignedTinyInteger('bitrix_sync_errors')->default(0)->after('bitrix_synced_at');
                }
                if (!Schema::hasColumn($tableName, 'bitrix_sync_failed_at')) {
                    $table->timestamp('bitrix_sync_failed_at')->nullable()->after('bitrix_sync_errors');
                }
                if (!Schema::hasColumn($tableName, 'bitrix_sync_last_error')) {
                    $table->string('bitrix_sync_last_error', 500)->nullable()->after('bitrix_sync_failed_at');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['landing_consolidado_leads', 'landing_curso_leads'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (['bitrix_sync_last_error', 'bitrix_sync_failed_at', 'bitrix_sync_errors'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
}
