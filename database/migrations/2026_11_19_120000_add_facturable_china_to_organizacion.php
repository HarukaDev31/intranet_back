<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRY-26-0003: China puede «eliminar» un socio o importador sin borrar la organización
 * (tiene usuarios, rol, pagos y operaciones). Solo deja de ser facturable: no sale en Clientes
 * ni se le pueden crear operaciones nuevas; lo ya facturado y sus pagos se mantienen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('organizacion', 'facturable_china')) {
            Schema::table('organizacion', function (Blueprint $table) {
                $table->boolean('facturable_china')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('organizacion', 'facturable_china')) {
            Schema::table('organizacion', function (Blueprint $table) {
                $table->dropColumn('facturable_china');
            });
        }
    }
};
