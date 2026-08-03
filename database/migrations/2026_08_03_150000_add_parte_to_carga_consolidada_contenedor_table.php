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
        Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
            // Parte del consolidado partido: A / B. Null = no partido.
            $table->string('parte', 1)->nullable()->after('carga');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
            $table->dropColumn('parte');
        });
    }
};
