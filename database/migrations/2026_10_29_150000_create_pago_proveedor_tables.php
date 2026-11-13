<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago_proveedor_configuracion', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 80)->unique();
            $table->json('valor');
            $table->timestamps();
        });

        $ahora = now();
        $filas = [];
        foreach ($this->defaults() as $clave => $valor) {
            $filas[] = [
                'clave' => $clave,
                'valor' => json_encode($valor),
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }
        DB::table('pago_proveedor_configuracion')->insert($filas);

        Schema::create('pago_proveedor_cotizaciones', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->unsignedBigInteger('calculadora_importacion_id')->nullable()->index();
            $table->unsignedBigInteger('id_cotizacion_cc')->nullable()->index();
            $table->string('cod_cotizacion_cc', 20)->index();
            $table->string('estado', 20)->default('pendiente')->index();
            $table->dateTime('vigencia_fin')->index();
            $table->decimal('tipo_cambio', 12, 4)->nullable();
            $table->boolean('tipo_cambio_confirmado')->default(false);
            $table->string('cliente_nombre')->nullable();
            $table->string('cliente_documento', 30)->nullable();
            $table->string('cliente_whatsapp', 40)->nullable();
            $table->string('cliente_correo')->nullable();
            $table->string('consolidado', 40)->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('pago_proveedor_cotizacion_proveedores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('pago_proveedor_cotizaciones')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden')->default(1);
            $table->string('code_supplier', 50)->nullable();
            $table->unsignedBigInteger('id_proveedor_cc')->nullable();
            $table->string('nombre_proveedor')->nullable();
            $table->string('producto')->nullable();
            $table->string('moneda', 8)->default('RMB');
            $table->string('tipo_pago', 20)->default('transferencia');
            $table->decimal('valor_carga_usd', 12, 2)->default(0);
            $table->string('modalidad_abono', 20)->default('total');
            $table->string('modalidad_pago', 20)->default('total');
            $table->decimal('porcentaje_1', 5, 2)->default(100);
            $table->decimal('porcentaje_2', 5, 2)->nullable();
            $table->date('fecha_cuota_2')->nullable();
            $table->boolean('comision_bancaria')->default(true);
            $table->boolean('comision_administrativa')->default(false);
            $table->string('proforma_path')->nullable();
            $table->string('proforma_nombre')->nullable();
            $table->timestamps();

            $table->index(['cotizacion_id', 'orden']);
        });

        Schema::create('pago_proveedor_solicitudes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('numero_orden')->unique();
            $table->foreignId('cotizacion_id')->unique()->constrained('pago_proveedor_cotizaciones');
            $table->string('cod_cotizacion_cc', 20);
            $table->unsignedBigInteger('id_cotizacion_cc')->nullable();
            $table->decimal('tipo_cambio', 12, 4)->nullable();
            $table->boolean('tipo_cambio_confirmado')->default(false);
            $table->string('cliente_nombre')->nullable();
            $table->string('cliente_documento', 30)->nullable();
            $table->string('cliente_whatsapp', 40)->nullable();
            $table->string('cliente_correo')->nullable();
            $table->string('consolidado', 40)->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();
        });

        Schema::create('pago_proveedor_solicitud_proveedores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('pago_proveedor_solicitudes')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden')->default(1);
            $table->string('code_supplier', 50);
            $table->unsignedBigInteger('id_proveedor_cc')->nullable();
            $table->string('nombre_proveedor')->nullable();
            $table->string('producto');
            $table->string('moneda', 8);
            $table->string('tipo_pago', 20);
            $table->decimal('valor_carga_usd', 12, 2);
            $table->string('modalidad_abono', 20);
            $table->string('modalidad_pago', 20);
            $table->decimal('porcentaje_1', 5, 2)->default(100);
            $table->decimal('porcentaje_2', 5, 2)->nullable();
            $table->date('fecha_cuota_2')->nullable();
            $table->boolean('comision_bancaria')->default(true);
            $table->boolean('comision_administrativa')->default(false);
            $table->decimal('bancaria_monto', 12, 2)->default(0);
            $table->decimal('administrativa_monto', 12, 2)->default(0);
            $table->decimal('total_monto', 12, 2)->default(0);
            $table->unsignedSmallInteger('bancaria_incluida_en_orden')->nullable();
            $table->json('resumen_json')->nullable();
            $table->string('proforma_path')->nullable();
            $table->string('proforma_nombre')->nullable();
            $table->string('codigo_solicitud', 40)->nullable();
            $table->unsignedTinyInteger('etapa')->default(1);
            $table->string('estado', 20)->default('en_proceso');
            $table->unsignedTinyInteger('cuota_actual')->default(1);
            $table->unsignedTinyInteger('pago_actual')->default(1);
            $table->timestamps();

            $table->index(['solicitud_id', 'orden']);
        });

        Schema::create('pago_proveedor_historial', function (Blueprint $table) {
            $table->id();
            $table->string('entidad_tipo', 40);
            $table->unsignedBigInteger('entidad_id');
            $table->string('accion', 80);
            $table->text('detalle')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('usuario_nombre')->nullable();
            $table->string('area', 80)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entidad_tipo', 'entidad_id']);
        });

        Schema::create('pago_proveedor_mensajes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cotizacion_id')->nullable()->index();
            $table->unsignedBigInteger('solicitud_id')->nullable()->index();
            $table->string('tipo', 40);
            $table->string('destinatario')->nullable();
            $table->text('cuerpo');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pago_proveedor_mensajes');
        Schema::dropIfExists('pago_proveedor_historial');
        Schema::dropIfExists('pago_proveedor_solicitud_proveedores');
        Schema::dropIfExists('pago_proveedor_solicitudes');
        Schema::dropIfExists('pago_proveedor_cotizacion_proveedores');
        Schema::dropIfExists('pago_proveedor_cotizaciones');
        Schema::dropIfExists('pago_proveedor_configuracion');
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'tarifa_bancaria_usd' => [100, 120, 150],
            'tarifa_bancaria_rmb' => [50, 90, 120],
            'tramo_medio' => 3000,
            'tramo_alto' => 9000,
            'comision_administrativa' => 30,
            'tolerancia' => 1,
            'limite_excepcional' => 2,
            'plazo_verificacion_horas' => 2,
            'plazo_subsanar_dias' => 7,
            'deshacer_sin_aviso_minutos' => 5,
            'deshacer_con_aviso_horas' => 24,
            'plazo_decision_horas' => 72,
            'aprobacion_automatica' => true,
            'vigencia_cotizacion_horas' => 48,
            'plazo_observado_china_horas' => 48,
            'aviso_cuota_2_dias' => 2,
        ];
    }
};
