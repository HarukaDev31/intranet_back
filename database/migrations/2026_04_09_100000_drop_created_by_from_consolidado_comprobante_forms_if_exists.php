<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('consolidado_comprobante_forms')) {
            return;
        }
        if (! Schema::hasColumn('consolidado_comprobante_forms', 'created_by')) {
            return;
        }

        Schema::table('consolidado_comprobante_forms', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('consolidado_comprobante_forms')) {
            return;
        }
        if (Schema::hasColumn('consolidado_comprobante_forms', 'created_by')) {
            return;
        }

        Schema::table('consolidado_comprobante_forms', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->after('id_user');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }
};
