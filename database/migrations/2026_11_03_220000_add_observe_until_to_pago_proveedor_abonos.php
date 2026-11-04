<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pago_proveedor_abonos', function (Blueprint $table) {
            $table->timestamp('observe_until')->nullable()->after('observation_detail');
            $table->timestamp('observation_escalated_at')->nullable()->after('observe_until');
        });
    }

    public function down(): void
    {
        Schema::table('pago_proveedor_abonos', function (Blueprint $table) {
            $table->dropColumn(['observe_until', 'observation_escalated_at']);
        });
    }
};
