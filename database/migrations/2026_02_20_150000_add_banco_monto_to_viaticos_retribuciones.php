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
        Schema::table('viaticos_retribuciones', function (Blueprint $table) {
            $table->string('banco', 100)->nullable()->after('file_original_name');
            $table->decimal('monto', 10, 2)->nullable()->after('banco');
            $table->date('fecha_cierre')->nullable()->after('monto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('viaticos_retribuciones', function (Blueprint $table) {
            $table->dropColumn(['banco', 'monto', 'fecha_cierre']);
        });
    }
};
