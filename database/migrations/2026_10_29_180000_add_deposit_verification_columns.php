<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pago_proveedor_abonos', function (Blueprint $table) {
            $table->decimal('bank_amount', 12, 2)->nullable()->after('amount')
                ->comment('Importe que ingresó al banco. Nulo hasta que Administración valida');
            $table->decimal('recorded_amount', 12, 2)->nullable()->after('bank_amount')
                ->comment('Importe original de Ventas cuando el banco lo ajustó');
            $table->string('observation_reason', 80)->nullable()->after('status')
                ->comment('Código del motivo por el que Administración observó el depósito');
            $table->text('observation_detail')->nullable()->after('observation_reason')
                ->comment('Detalle libre de la observación');
            $table->boolean('corrected_by_admin')->default(false)->after('observation_detail')
                ->comment('Si Administración corrigió banco, fecha o número de operación sin validar');
            $table->timestamp('verified_at')->nullable()->after('corrected_by_admin')
                ->comment('Momento en que Administración validó u observó el depósito');
            $table->unsignedBigInteger('verified_by_user_id')->nullable()->after('verified_at')
                ->comment('Usuario de Administración que verificó. Sin clave foránea');
        });
    }

    public function down(): void
    {
        Schema::table('pago_proveedor_abonos', function (Blueprint $table) {
            $table->dropColumn([
                'bank_amount',
                'recorded_amount',
                'observation_reason',
                'observation_detail',
                'corrected_by_admin',
                'verified_at',
                'verified_by_user_id',
            ]);
        });
    }
};
