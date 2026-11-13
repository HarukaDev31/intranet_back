<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pago_proveedor_ejecuciones', function (Blueprint $table) {
            $table->decimal('real_exchange_rate', 12, 4)->nullable()->after('profit')->comment('Tipo de cambio real con el que China compró los yuanes');
            $table->decimal('yuan_obtained', 12, 2)->nullable()->after('real_exchange_rate')->comment('Yuanes obtenidos al multiplicar los dólares recibidos por el tipo de cambio real');
            $table->decimal('yuan_paid', 12, 2)->nullable()->after('yuan_obtained')->comment('Yuanes que China pagó al proveedor');
            $table->decimal('exchange_difference_usd', 12, 2)->nullable()->after('yuan_paid')->comment('Diferencia de cambio en dólares. Negativa se cobra al cliente y positiva queda a su favor');
            $table->string('exchange_result', 30)->nullable()->after('exchange_difference_usd')->comment('Resultado de la diferencia: within_tolerance, to_collect o to_refund');
        });

        Schema::table('pago_proveedor_solicitud_proveedores', function (Blueprint $table) {
            $table->string('exchange_result', 30)->nullable()->after('exchange_rate_pending')->comment('Última diferencia de cambio del proveedor');
            $table->string('second_payment_choice', 30)->nullable()->after('exchange_result')->comment('Decisión del pago 2 al proveedor: se_realizara, anulado o reembolso_solicitado');
            $table->string('china_observation_reason', 80)->nullable()->after('second_payment_choice')->comment('Motivo por el que Operaciones China observó el pago');
            $table->text('china_observation_detail')->nullable()->after('china_observation_reason')->comment('Detalle de la observación de China');
            $table->timestamp('china_observed_at')->nullable()->after('china_observation_detail')->comment('Momento en que China observó el pago');
            $table->timestamp('china_observe_until')->nullable()->after('china_observed_at')->comment('Plazo de 48 h para levantar la observación. Al vencer solo alerta');
        });

        Schema::table('pago_proveedor_abonos', function (Blueprint $table) {
            $table->string('purpose', 30)->default('client_deposit')->after('request_id')->comment('client_deposit es el abono del cliente; exchange_difference es el voucher de la diferencia de cambio');
            $table->unsignedBigInteger('exchange_provider_id')->nullable()->after('purpose')->comment('Proveedor al que corresponde el voucher de la diferencia de cambio');
            $table->index('exchange_provider_id', 'pp_abono_diferencia_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pago_proveedor_abonos', function (Blueprint $table) {
            $table->dropIndex('pp_abono_diferencia_idx');
            $table->dropColumn(['purpose', 'exchange_provider_id']);
        });
        Schema::table('pago_proveedor_solicitud_proveedores', function (Blueprint $table) {
            $table->dropColumn([
                'exchange_result',
                'second_payment_choice',
                'china_observation_reason',
                'china_observation_detail',
                'china_observed_at',
                'china_observe_until',
            ]);
        });
        Schema::table('pago_proveedor_ejecuciones', function (Blueprint $table) {
            $table->dropColumn([
                'real_exchange_rate',
                'yuan_obtained',
                'yuan_paid',
                'exchange_difference_usd',
                'exchange_result',
            ]);
        });
    }
};
