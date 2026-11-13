<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago_proveedor_ejecuciones', function (Blueprint $table) {
            $table->id()->comment('Identificador del pago ejecutado en dólares');
            $table->unsignedBigInteger('request_provider_id')->comment('Proveedor de la solicitud al que se le transfirió');
            $table->unsignedTinyInteger('installment')->default(1)->comment('Cuota del cliente que se está pagando');
            $table->unsignedTinyInteger('supplier_payment')->default(1)->comment('Parte del pago al proveedor, cuando se divide en dos');
            $table->decimal('cargo_amount', 12, 2)->comment('Importe transferido al proveedor, sin comisiones');
            $table->decimal('charged_bank_fee', 12, 2)->default(0)->comment('Comisión bancaria cobrada al cliente en este pago');
            $table->decimal('real_bank_fee', 12, 2)->default(0)->comment('Comisión que el banco cobró de verdad al transferir');
            $table->decimal('charged_admin_fee', 12, 2)->default(0)->comment('Comisión administrativa cobrada al cliente en este pago');
            $table->decimal('bank_difference', 12, 2)->default(0)->comment('Comisión cobrada menos la comisión real. Positiva es ganancia');
            $table->decimal('profit', 12, 2)->default(0)->comment('Ganancia del pago: diferencia bancaria más comisión administrativa');
            $table->string('receipt_path')->comment('Ruta de la constancia del pago al proveedor');
            $table->string('receipt_name')->comment('Nombre original del archivo de la constancia');
            $table->date('executed_on')->comment('Fecha de negocio en que Administración registró la transferencia');
            $table->unsignedBigInteger('user_id')->nullable()->comment('Usuario de Administración que registró el pago. Sin clave foránea');
            $table->timestamps();

            $table->foreign('request_provider_id', 'pp_ejecucion_proveedor_fk')
                ->references('id')->on('pago_proveedor_solicitud_proveedores');
            $table->unique(['request_provider_id', 'installment', 'supplier_payment'], 'pp_ejecucion_unica');
            $table->index('executed_on', 'pp_ejecucion_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pago_proveedor_ejecuciones');
    }
};
