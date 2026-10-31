<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago_proveedor_reembolsos', function (Blueprint $table) {
            $table->id()->comment('Identificador del reembolso');
            $table->unsignedBigInteger('request_id')->comment('Solicitud a la que pertenece el reembolso');
            $table->unsignedBigInteger('request_provider_id')->nullable()->comment('Proveedor del reembolso, cuando corresponde a uno');
            $table->unsignedBigInteger('deposit_id')->nullable()->comment('Abono con excedente, cuando el origen es un depósito de más');
            $table->string('origin', 30)->comment('Origen: surplus, exchange o payment_2');
            $table->string('status', 20)->default('por_consultar')->comment('por_consultar, solicitado, aprobado o rechazado');
            $table->decimal('amount', 12, 2)->comment('Monto a devolver según el sistema');
            $table->json('breakdown')->nullable()->comment('Componentes del monto: carga, administrativa o diferencia de cambio');
            $table->text('reason')->nullable()->comment('Motivo indicado por Ventas o el rechazo de Administración');
            $table->timestamp('requested_at')->nullable()->comment('Momento en que Ventas solicitó el reembolso');
            $table->date('paid_on')->nullable()->comment('Fecha del voucher con el que Administración devolvió');
            $table->string('bank', 20)->nullable()->comment('Banco del voucher de la devolución');
            $table->string('operation_number', 80)->nullable()->comment('Número de operación del voucher. Puede ir vacío');
            $table->decimal('approved_amount', 12, 2)->nullable()->comment('Importe que Administración registró en el voucher');
            $table->string('voucher_path')->nullable()->comment('Ruta de la foto del comprobante de devolución');
            $table->string('voucher_name')->nullable()->comment('Nombre original de la foto del comprobante');
            $table->timestamp('decided_at')->nullable()->comment('Momento en que Administración aprobó o rechazó');
            $table->unsignedBigInteger('decided_by_user_id')->nullable()->comment('Usuario de Administración que decidió. Sin clave foránea');
            $table->timestamps();

            $table->foreign('request_id', 'pp_reembolso_solicitud_fk')->references('id')->on('pago_proveedor_solicitudes');
            $table->index(['request_provider_id', 'origin'], 'pp_reembolso_proveedor_idx');
            $table->index('status', 'pp_reembolso_estado_idx');
        });

        Schema::table('pago_proveedor_solicitudes', function (Blueprint $table) {
            $table->string('partner', 80)->default('Pro Business')->after('consolidation')->comment('Socio del pago. Pro Business o el socio que registra China');
            $table->string('origin', 30)->default('sales')->after('partner')->comment('sales si nace de Ventas; china_manual si la crea Operaciones China');
        });

        Schema::table('pago_proveedor_abono_aplicaciones', function (Blueprint $table) {
            $table->boolean('continues')->nullable()->after('amount')->comment('En un voucher compartido con faltante: si este proveedor sigue o queda pendiente');
        });
    }

    public function down(): void
    {
        Schema::table('pago_proveedor_abono_aplicaciones', function (Blueprint $table) {
            $table->dropColumn('continues');
        });
        Schema::table('pago_proveedor_solicitudes', function (Blueprint $table) {
            $table->dropColumn(['partner', 'origin']);
        });
        Schema::dropIfExists('pago_proveedor_reembolsos');
    }
};
