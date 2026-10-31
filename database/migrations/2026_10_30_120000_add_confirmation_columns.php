<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pago_proveedor_solicitud_proveedores', function (Blueprint $table) {
            $table->string('second_installment_choice', 20)->nullable()->after('approved_by_user_id')
                ->comment('Decisión de la cuota 2: se_realizara o no_se_realizara');
            $table->timestamp('decision_due_at')->nullable()->after('second_installment_choice')
                ->comment('Plazo para que Ventas decida la cuota 2. Al vencer solo alerta');
            $table->boolean('exchange_rate_pending')->default(false)->after('decision_due_at')
                ->comment('Si la cuota 2 espera el tipo de cambio del día antes de cobrar');
        });
    }

    public function down(): void
    {
        Schema::table('pago_proveedor_solicitud_proveedores', function (Blueprint $table) {
            $table->dropColumn([
                'second_installment_choice',
                'decision_due_at',
                'exchange_rate_pending',
            ]);
        });
    }
};
