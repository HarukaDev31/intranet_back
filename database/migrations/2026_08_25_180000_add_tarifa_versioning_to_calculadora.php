<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calculadora_tarifas_consolidado', function (Blueprint $table) {
            if (! Schema::hasColumn('calculadora_tarifas_consolidado', 'vigente_hasta')) {
                $table->timestamp('vigente_hasta')->nullable()->after('calculadora_tipo_cliente_id');
                $table->index('vigente_hasta', 'idx_tarifas_vigente_hasta');
            }
        });

        Schema::table('calculadora_importacion', function (Blueprint $table) {
            if (! Schema::hasColumn('calculadora_importacion', 'calculadora_tarifa_consolidado_id')) {
                $table->unsignedBigInteger('calculadora_tarifa_consolidado_id')->nullable()->after('tarifa');
                $table->string('tarifa_type', 20)->nullable()->after('calculadora_tarifa_consolidado_id');

                $table->foreign('calculadora_tarifa_consolidado_id', 'fk_calc_imp_tarifa_consolidado')
                    ->references('id')
                    ->on('calculadora_tarifas_consolidado')
                    ->nullOnDelete();

                $table->index('calculadora_tarifa_consolidado_id', 'idx_calc_imp_tarifa_consolidado');
            }
        });
    }

    public function down(): void
    {
        Schema::table('calculadora_importacion', function (Blueprint $table) {
            if (Schema::hasColumn('calculadora_importacion', 'calculadora_tarifa_consolidado_id')) {
                $table->dropForeign('fk_calc_imp_tarifa_consolidado');
                $table->dropIndex('idx_calc_imp_tarifa_consolidado');
                $table->dropColumn(['calculadora_tarifa_consolidado_id', 'tarifa_type']);
            }
        });

        Schema::table('calculadora_tarifas_consolidado', function (Blueprint $table) {
            if (Schema::hasColumn('calculadora_tarifas_consolidado', 'vigente_hasta')) {
                $table->dropIndex('idx_tarifas_vigente_hasta');
                $table->dropColumn('vigente_hasta');
            }
        });
    }
};
