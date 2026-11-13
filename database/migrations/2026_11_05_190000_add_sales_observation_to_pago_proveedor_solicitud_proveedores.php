<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pago_proveedor_solicitud_proveedores', function (Blueprint $table) {
            $table->text('sales_observation')->nullable();
            $table->timestamp('sales_observed_at')->nullable();
            $table->text('cancellation_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pago_proveedor_solicitud_proveedores', function (Blueprint $table) {
            $table->dropColumn(['sales_observation', 'sales_observed_at', 'cancellation_reason']);
        });
    }
};
