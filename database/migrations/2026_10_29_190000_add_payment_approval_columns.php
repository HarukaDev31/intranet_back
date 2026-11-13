<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pago_proveedor_solicitud_proveedores', function (Blueprint $table) {
            $table->string('approval_status', 20)->nullable()->after('current_payment')
                ->comment('Aprobación del pago: pendiente, por_cobrar o aprobado. Vacío si todavía no entra a aprobación');
            $table->string('approval_mode', 20)->nullable()->after('approval_status')
                ->comment('Cómo se aprobó: automatica o manual');
            $table->decimal('assumed_amount', 12, 2)->nullable()->after('approval_mode')
                ->comment('Diferencia en USD que asume Pro Business al aprobar');
            $table->string('assumed_reason', 255)->nullable()->after('assumed_amount')
                ->comment('Motivo obligatorio cuando Administración asume la diferencia');
            $table->decimal('collect_amount', 12, 2)->nullable()->after('assumed_reason')
                ->comment('Importe que Ventas debe cobrar al cliente cuando el pago queda por cobrar');
            $table->timestamp('collect_until')->nullable()->after('collect_amount')
                ->comment('Plazo para que Ventas cobre la diferencia. Al vencer vuelve a pendiente');
            $table->timestamp('approved_at')->nullable()->after('collect_until')
                ->comment('Momento en que el pago quedó aprobado');
            $table->unsignedBigInteger('approved_by_user_id')->nullable()->after('approved_at')
                ->comment('Usuario de Administración que aprobó. Nulo si fue automática. Sin clave foránea');
            $table->index('approval_status', 'pp_solicitud_proveedor_aprobacion_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pago_proveedor_solicitud_proveedores', function (Blueprint $table) {
            $table->dropIndex('pp_solicitud_proveedor_aprobacion_idx');
            $table->dropColumn([
                'approval_status',
                'approval_mode',
                'assumed_amount',
                'assumed_reason',
                'collect_amount',
                'collect_until',
                'approved_at',
                'approved_by_user_id',
            ]);
        });
    }
};
