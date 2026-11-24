<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapas del consolidado, Estado de contenedores y Flujo de caja del Encargado China.
 * Los datos de reserva viven aparte de carga_consolidada_contenedor: ahí naviera, fechas y valor_flete
 * son los de Documentación (Perú) y no deben pisarse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('china_container_stages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('container_id')->unique()->comment('carga_consolidada_contenedor.id');
            $table->decimal('freight_usd', 12, 2)->nullable()->comment('Precio del flete');
            $table->date('filling_on')->nullable()->comment('Fecha de llenado');
            $table->date('sailing_on')->nullable()->comment('Fecha de zarpe');
            $table->date('arrival_on')->nullable()->comment('Fecha de llegada');
            $table->string('shipping_line', 100)->nullable();
            $table->string('destination_port', 100)->nullable();
            $table->string('free_days', 60)->nullable()->comment('Sobrestadía');
            $table->string('container_type', 20)->nullable();
            $table->string('shipper', 100)->nullable();
            $table->boolean('list_updated')->default(false)->comment('Lista de embarque actualizada');
            $table->string('bl_consignee', 200)->nullable();
            $table->string('bl_description', 255)->nullable();
            $table->string('bl_number', 60)->nullable();
            $table->string('bl_draft_path')->nullable();
            $table->string('bl_draft_name')->nullable();
            $table->string('bl_official_path')->nullable();
            $table->string('bl_official_name')->nullable();
            $table->boolean('telex')->default(false);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('china_shipping_options', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20)->comment('line o shipper');
            $table->string('name', 100);
            $table->timestamps();
            $table->unique(['kind', 'name'], 'china_ship_opt_unique');
        });

        Schema::create('china_freight_payments', function (Blueprint $table) {
            $table->id();
            $table->date('paid_on');
            $table->boolean('manual')->default(false)->comment('Confirmado sin voucher');
            $table->decimal('exchange_rate', 12, 4)->comment('TC del mes al registrar el pago');
            $table->decimal('usd_amount', 12, 2);
            $table->decimal('yuan_amount', 14, 2);
            $table->string('voucher_path')->nullable();
            $table->string('voucher_name')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('china_freight_payment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('china_freight_payments')->cascadeOnDelete();
            $table->unsignedBigInteger('container_id')->unique()->comment('Un flete se paga una sola vez');
            $table->decimal('usd_amount', 12, 2);
        });

        Schema::create('china_cash_months', function (Blueprint $table) {
            $table->id();
            $table->char('month', 7)->unique()->comment('YYYY-MM');
            $table->decimal('exchange_rate', 12, 4)->nullable()->comment('TC general del mes');
            $table->decimal('opening_balance', 14, 2)->nullable()->comment('Solo el primer mes; luego es el cierre anterior');
            $table->decimal('closing_balance', 14, 2)->nullable()->comment('Saldo escrito a mano al cerrar');
            $table->decimal('income_total', 14, 2)->nullable()->comment('Ingresos reales al cerrar');
            $table->decimal('expense_total', 14, 2)->nullable()->comment('Egresos reales al cerrar');
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamps();
        });

        Schema::create('china_cash_weeks', function (Blueprint $table) {
            $table->id();
            $table->char('month', 7);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('balance', 14, 2);
            $table->decimal('usd_pending', 12, 2)->default(0)->comment('USD por cambiar al cerrar');
            $table->string('screenshot_path');
            $table->string('screenshot_name');
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamps();
            $table->unique(['month', 'starts_on'], 'china_cash_week_unique');
        });

        Schema::create('china_fixed_costs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->decimal('amount', 14, 2)->comment('RMB por mes');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('china_fixed_cost_charges', function (Blueprint $table) {
            $table->id();
            $table->char('month', 7);
            $table->unsignedBigInteger('fixed_cost_id');
            $table->string('name', 150);
            $table->decimal('amount', 14, 2);
            $table->date('charged_on')->comment('Último día del mes');
            $table->timestamps();
            $table->unique(['month', 'fixed_cost_id'], 'china_fixed_charge_unique');
        });

        Schema::create('china_investments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->decimal('amount', 14, 2)->comment('RMB');
            $table->date('due_on');
            $table->date('paid_on')->nullable();
            $table->unsignedBigInteger('previous_id')->nullable()->comment('Inversión que se volvió a hacer');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['china_investments', 'china_fixed_cost_charges', 'china_fixed_costs', 'china_cash_weeks', 'china_cash_months',
            'china_freight_payment_items', 'china_freight_payments', 'china_shipping_options', 'china_container_stages'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
