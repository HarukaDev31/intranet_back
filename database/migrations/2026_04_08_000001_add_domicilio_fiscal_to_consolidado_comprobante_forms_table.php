<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consolidado_comprobante_forms', function (Blueprint $table) {
            $table->text('domicilio_fiscal')->nullable()->after('ruc');
        });
    }

    public function down(): void
    {
        Schema::table('consolidado_comprobante_forms', function (Blueprint $table) {
            $table->dropColumn('domicilio_fiscal');
        });
    }
};
