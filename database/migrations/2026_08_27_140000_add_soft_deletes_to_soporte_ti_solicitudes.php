<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSoftDeletesToSoporteTiSolicitudes extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('soporte_ti_solicitudes')) {
            return;
        }
        Schema::table('soporte_ti_solicitudes', function (Blueprint $table) {
            if (!Schema::hasColumn('soporte_ti_solicitudes', 'deleted_at')) {
                $table->softDeletes();
                $table->index('deleted_at', 'idx_st_solicitudes_deleted_at');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('soporte_ti_solicitudes')) {
            return;
        }
        Schema::table('soporte_ti_solicitudes', function (Blueprint $table) {
            if (Schema::hasColumn('soporte_ti_solicitudes', 'deleted_at')) {
                $table->dropIndex('idx_st_solicitudes_deleted_at');
                $table->dropSoftDeletes();
            }
        });
    }
}
