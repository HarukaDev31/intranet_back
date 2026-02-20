<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Código correlativo al confirmar: VI2026001, VI2026002, ... (VI + año + índice)
     */
    public function up(): void
    {
        Schema::table('viaticos', function (Blueprint $table) {
            $table->string('codigo_confirmado', 20)->nullable()->after('status');
            $table->index('codigo_confirmado');
        });
    }

    public function down(): void
    {
        Schema::table('viaticos', function (Blueprint $table) {
            $table->dropIndex(['codigo_confirmado']);
            $table->dropColumn('codigo_confirmado');
        });
    }
};
