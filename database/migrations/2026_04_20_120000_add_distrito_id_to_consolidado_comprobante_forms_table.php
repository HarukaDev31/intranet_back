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

        if (Schema::hasColumn('consolidado_comprobante_forms', 'distrito_id')) {
            return;
        }

        Schema::table('consolidado_comprobante_forms', function (Blueprint $table) {
            $table->unsignedInteger('distrito_id')->nullable()->after('domicilio_fiscal');
        });

        if (Schema::hasTable('distrito')) {
            Schema::table('consolidado_comprobante_forms', function (Blueprint $table) {
                $table->foreign('distrito_id')
                    ->references('ID_Distrito')
                    ->on('distrito')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('consolidado_comprobante_forms')) {
            return;
        }

        if (! Schema::hasColumn('consolidado_comprobante_forms', 'distrito_id')) {
            return;
        }

        try {
            Schema::table('consolidado_comprobante_forms', function (Blueprint $table) {
                $table->dropForeign(['distrito_id']);
            });
        } catch (\Throwable $e) {
            // Sin FK o nombre distinto según motor / entorno
        }

        Schema::table('consolidado_comprobante_forms', function (Blueprint $table) {
            $table->dropColumn('distrito_id');
        });
    }
};
