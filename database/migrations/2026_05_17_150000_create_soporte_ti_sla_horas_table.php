<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateSoporteTiSlaHorasTable extends Migration
{
    public function up()
    {
        Schema::create('soporte_ti_sla_horas', function (Blueprint $table) {
            $table->increments('id');
            $table->char('tipo_solicitud', 1);
            $table->string('criticidad', 20);
            $table->unsignedSmallInteger('horas');
            $table->timestamps();

            $table->unique(['tipo_solicitud', 'criticidad'], 'soporte_ti_sla_horas_tipo_crit_unique');
        });

        $now = now();
        $rows = array(
            array('tipo_solicitud' => 'A', 'criticidad' => 'Baja', 'horas' => 48),
            array('tipo_solicitud' => 'A', 'criticidad' => 'Media', 'horas' => 72),
            array('tipo_solicitud' => 'A', 'criticidad' => 'Alta', 'horas' => 96),
            array('tipo_solicitud' => 'A', 'criticidad' => 'Máxima', 'horas' => 120),
            array('tipo_solicitud' => 'B', 'criticidad' => 'Baja', 'horas' => 4),
            array('tipo_solicitud' => 'B', 'criticidad' => 'Media', 'horas' => 8),
            array('tipo_solicitud' => 'B', 'criticidad' => 'Alta', 'horas' => 16),
            array('tipo_solicitud' => 'B', 'criticidad' => 'Máxima', 'horas' => 24),
        );

        foreach ($rows as $row) {
            DB::table('soporte_ti_sla_horas')->insert(array(
                'tipo_solicitud' => $row['tipo_solicitud'],
                'criticidad' => $row['criticidad'],
                'horas' => $row['horas'],
                'created_at' => $now,
                'updated_at' => $now,
            ));
        }
    }

    public function down()
    {
        Schema::dropIfExists('soporte_ti_sla_horas');
    }
}
