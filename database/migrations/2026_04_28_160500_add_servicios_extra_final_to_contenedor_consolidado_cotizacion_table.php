<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            $table->decimal('servicios_extra_final', 15, 2)
                ->default(0)
                ->after('logistica_final');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            $table->dropColumn('servicios_extra_final');
        });
    }
};

