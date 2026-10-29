<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago_proveedor_datos_proveedor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_proveedor_id')->unique()->constrained('pago_proveedor_solicitud_proveedores')->cascadeOnDelete();
            $table->string('vendedor_nombre')->nullable();
            $table->string('wechat', 30)->nullable();
            $table->string('estado', 30)->default('pendiente');
            $table->string('origen', 20)->nullable();
            $table->string('token', 64)->unique();
            $table->boolean('correccion_solicitada')->default(false);
            $table->string('beneficiario_nombre')->nullable();
            $table->string('beneficiario_cuenta')->nullable();
            $table->string('beneficiario_direccion')->nullable();
            $table->string('beneficiario_pais', 80)->nullable();
            $table->string('beneficiario_ciudad', 80)->nullable();
            $table->string('banco_tipo_codigo', 20)->nullable();
            $table->string('banco_codigo', 40)->nullable();
            $table->string('banco_nombre')->nullable();
            $table->string('banco_direccion')->nullable();
            $table->string('banco_pais', 80)->nullable();
            $table->string('banco_ciudad', 80)->nullable();
            $table->boolean('usa_intermediario')->default(false);
            $table->string('inter_tipo_codigo', 20)->nullable();
            $table->string('inter_codigo', 40)->nullable();
            $table->string('inter_nombre')->nullable();
            $table->string('inter_direccion')->nullable();
            $table->string('inter_pais', 80)->nullable();
            $table->string('inter_ciudad', 80)->nullable();
            $table->timestamp('autorizado_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pago_proveedor_abonos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('pago_proveedor_solicitudes')->cascadeOnDelete();
            $table->decimal('importe', 12, 2);
            $table->date('fecha_pago');
            $table->string('numero_operacion', 80);
            $table->string('banco', 20);
            $table->boolean('interbancaria')->default(false);
            $table->string('voucher_path');
            $table->string('voucher_nombre');
            $table->string('estado', 30)->default('en_validacion')->index();
            $table->decimal('excedente', 12, 2)->default(0);
            $table->string('excedente_decision', 40)->nullable();
            $table->boolean('operacion_repetida')->default(false);
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();

            $table->index(['solicitud_id', 'estado']);
        });

        Schema::create('pago_proveedor_abono_aplicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('abono_id')->constrained('pago_proveedor_abonos')->cascadeOnDelete();
            $table->unsignedBigInteger('solicitud_proveedor_id');
            $table->foreign('solicitud_proveedor_id', 'pp_abono_aplicacion_proveedor_fk')
                ->references('id')
                ->on('pago_proveedor_solicitud_proveedores')
                ->cascadeOnDelete();
            $table->decimal('monto', 12, 2);
            $table->timestamps();

            $table->unique(['abono_id', 'solicitud_proveedor_id'], 'pp_abono_aplicacion_unica');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pago_proveedor_abono_aplicaciones');
        Schema::dropIfExists('pago_proveedor_abonos');
        Schema::dropIfExists('pago_proveedor_datos_proveedor');
    }
};
