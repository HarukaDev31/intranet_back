<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operaciones y cobros de China (Operaciones del Almacén China / Pagos del Socio).
 * El estado de la operación y del cobro NO se guarda: se deriva en ChinaBillingCalculator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('china_operations', function (Blueprint $table) {
            $table->id()->comment('Identificador de la operación');
            $table->string('code', 20)->unique()->comment('Número de orden OP-0001');
            $table->unsignedInteger('organization_id')->comment('Organización socia a la que se factura');
            $table->unsignedBigInteger('client_id')->nullable()->comment('Cliente importador (cuando no se factura al socio)');
            $table->string('service_name', 150)->comment('Nombre del servicio cobrado');
            $table->string('billing_type', 20)->default('fixed')->comment('fixed, cbm');
            $table->string('variant_name', 150)->nullable()->comment('Variante o modalidad elegida');
            $table->unsignedBigInteger('container_id')->nullable()->unique()->comment('Consolidado cerrado de origen (un borrador aprobado por consolidado)');
            $table->date('issued_on')->comment('Fecha de la operación');
            $table->string('description', 255)->comment('Descripción o consolidado');
            $table->decimal('cbm', 12, 3)->nullable()->comment('CBM cobrados');
            $table->decimal('rate', 12, 2)->nullable()->comment('Tarifa por CBM');
            $table->decimal('amount', 12, 2)->default(0)->comment('Monto fijo del servicio');
            $table->decimal('fob', 12, 2)->default(0)->comment('Gasto FOB');
            $table->decimal('freight', 12, 2)->default(0)->comment('Flete marítimo');
            $table->string('packing_list_path')->nullable()->comment('Packing list en Excel');
            $table->string('packing_list_name')->nullable()->comment('Nombre original del packing list');
            $table->unsignedBigInteger('transferred_to_operation_id')->nullable()->comment('Operación a la que se trasladó la diferencia');
            $table->unsignedBigInteger('created_by')->nullable()->comment('Usuario que creó la operación');
            $table->timestamps();

            $table->index(['organization_id', 'issued_on'], 'china_op_org_date_idx');
            $table->foreign('organization_id', 'china_op_org_fk')
                ->references('ID_Organizacion')->on('organizacion')->cascadeOnDelete();
            $table->foreign('transferred_to_operation_id', 'china_op_transfer_fk')
                ->references('id')->on('china_operations')->nullOnDelete();
        });

        Schema::create('china_draft_dismissals', function (Blueprint $table) {
            $table->id()->comment('Identificador del borrador descartado');
            $table->unsignedBigInteger('container_id')->unique()->comment('Consolidado cerrado cuyo borrador "Por aprobar" se eliminó');
            $table->unsignedBigInteger('dismissed_by')->nullable()->comment('Usuario que lo eliminó');
            $table->timestamps();
        });

        Schema::create('china_bank_accounts', function (Blueprint $table) {
            $table->id()->comment('Identificador de la cuenta bancaria de China');
            $table->string('company', 150)->comment('Empresa que emite la factura');
            $table->string('beneficiary', 150)->nullable()->comment('Beneficiario de la cuenta');
            $table->string('intermediary_bank', 150)->nullable()->comment('Banco intermediario');
            $table->string('payer_bank', 150)->nullable()->comment('Banco pagador');
            $table->string('account_number', 100)->nullable()->comment('Número de cuenta');
            $table->boolean('is_active')->default(true)->comment('Cuenta vigente para facturas y balances');
            $table->timestamps();
        });

        Schema::create('china_documents', function (Blueprint $table) {
            $table->id()->comment('Identificador del documento emitido');
            $table->string('type', 20)->comment('invoice o balance');
            $table->unsignedInteger('organization_id')->comment('Cliente al que se emitió');
            $table->unsignedBigInteger('operation_id')->nullable()->comment('Operación de la factura (vacío en el balance)');
            $table->date('issued_on')->comment('Fecha de emisión');
            $table->json('snapshot')->comment('Datos con los que se calculó y se dibujó el PDF en esa fecha');
            $table->string('path')->comment('PDF guardado');
            $table->string('file_name')->comment('Nombre del archivo');
            $table->unsignedBigInteger('generated_by')->nullable()->comment('Usuario que lo emitió');
            $table->string('generated_by_name')->nullable()->comment('Nombre de quien lo emitió');
            $table->timestamps();

            $table->index(['type', 'organization_id', 'operation_id'], 'china_doc_scope_idx');
            $table->foreign('organization_id', 'china_doc_org_fk')
                ->references('ID_Organizacion')->on('organizacion')->cascadeOnDelete();
        });

        Schema::create('china_operation_costs', function (Blueprint $table) {
            $table->id()->comment('Identificador del costo adicional');
            $table->foreignId('operation_id')->constrained('china_operations')->cascadeOnDelete();
            $table->string('name', 150)->comment('Nombre del costo adicional');
            $table->decimal('amount', 12, 2)->comment('Monto del costo');
            $table->unsignedBigInteger('source_operation_id')->nullable()->comment('Operación de la que viene una diferencia trasladada');
            $table->timestamps();
        });

        Schema::create('china_payments', function (Blueprint $table) {
            $table->id()->comment('Identificador del cobro');
            $table->string('code', 20)->unique()->comment('Número de cobro PG-0001');
            $table->unsignedInteger('organization_id')->comment('Organización socia que paga');
            $table->date('paid_on')->comment('Fecha del pago');
            $table->decimal('amount_sent', 12, 2)->comment('Monto enviado por el cliente');
            $table->string('receipt_path')->nullable()->comment('Comprobante del cliente');
            $table->string('receipt_name')->nullable();
            $table->string('registered_by', 10)->default('china')->comment('partner o china');
            $table->unsignedBigInteger('registered_by_user_id')->nullable();
            $table->decimal('amount_received', 12, 2)->nullable()->comment('Monto realmente recibido por China');
            $table->timestamp('confirmed_at')->nullable()->comment('Cuándo China confirmó el ingreso');
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->unsignedBigInteger('shortfall_operation_id')->nullable()->comment('Operación que carga la diferencia por faltante');
            $table->decimal('exchange_rate', 12, 4)->nullable()->comment('Tipo de cambio al pasar a RMB');
            $table->decimal('yuan_amount', 14, 2)->nullable()->comment('Monto en RMB');
            $table->string('cash_voucher_path')->nullable()->comment('Voucher del cambio a RMB');
            $table->string('cash_voucher_name')->nullable();
            $table->timestamp('cashed_at')->nullable()->comment('Cuándo pasó a caja');
            $table->unsignedBigInteger('cashed_by')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'paid_on'], 'china_pay_org_date_idx');
            $table->foreign('organization_id', 'china_pay_org_fk')
                ->references('ID_Organizacion')->on('organizacion')->cascadeOnDelete();
            $table->foreign('shortfall_operation_id', 'china_pay_shortfall_fk')
                ->references('id')->on('china_operations')->nullOnDelete();
        });

        Schema::create('china_payment_allocations', function (Blueprint $table) {
            $table->id()->comment('Identificador de la distribución');
            $table->foreignId('payment_id')->constrained('china_payments')->cascadeOnDelete();
            $table->foreignId('operation_id')->constrained('china_operations')->cascadeOnDelete();
            $table->decimal('amount', 12, 2)->comment('Monto del pago aplicado a la operación');
            $table->timestamps();

            $table->unique(['payment_id', 'operation_id'], 'china_alloc_unique');
        });

        Schema::create('china_credit_applications', function (Blueprint $table) {
            $table->id()->comment('Identificador de la aplicación de saldo a favor');
            $table->foreignId('payment_id')->constrained('china_payments')->cascadeOnDelete();
            $table->foreignId('operation_id')->constrained('china_operations')->cascadeOnDelete();
            $table->decimal('amount', 12, 2)->comment('Saldo a favor aplicado');
            $table->date('applied_on')->comment('Fecha de aplicación');
            $table->unsignedBigInteger('from_operation_id')->nullable()->comment('Operación desde la que se trasladó el saldo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('china_credit_applications');
        Schema::dropIfExists('china_payment_allocations');
        Schema::dropIfExists('china_payments');
        Schema::dropIfExists('china_operation_costs');
        Schema::dropIfExists('china_documents');
        Schema::dropIfExists('china_bank_accounts');
        Schema::dropIfExists('china_draft_dismissals');
        Schema::dropIfExists('china_operations');
    }
};
