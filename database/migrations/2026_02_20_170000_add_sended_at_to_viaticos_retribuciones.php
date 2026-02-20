<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * sended_at: fecha en que se envió el WhatsApp por este comprobante.
     * Se actualiza en backend al enviar; si no es null no se reenvía.
     */
    public function up(): void
    {
        Schema::table('viaticos_retribuciones', function (Blueprint $table) {
            $table->datetime('sended_at')->nullable()->after('fecha_cierre');
        });
    }

    public function down(): void
    {
        Schema::table('viaticos_retribuciones', function (Blueprint $table) {
            $table->dropColumn('sended_at');
        });
    }
};
