<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateSoporteTiHorarioAtencionTable extends Migration
{
    /**
     * Run the migrations.
     * dia_semana: 1=Lunes … 7=Domingo (ISO-8601).
     *
     * @return void
     */
    public function up()
    {
        Schema::create('soporte_ti_horario_atencion', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedTinyInteger('dia_semana');
            $table->boolean('activo')->default(true);
            $table->time('hora_inicio')->default('09:00:00');
            $table->time('hora_fin')->default('18:00:00');
            $table->string('timezone', 64)->default('America/Lima');
            $table->timestamps();

            $table->unique('dia_semana');
        });

        $now = now();
        $rows = array();
        for ($dia = 1; $dia <= 7; $dia++) {
            $esLaboral = $dia >= 1 && $dia <= 5;
            $rows[] = array(
                'dia_semana' => $dia,
                'activo' => $esLaboral ? 1 : 0,
                'hora_inicio' => '09:00:00',
                'hora_fin' => '18:00:00',
                'timezone' => 'America/Lima',
                'created_at' => $now,
                'updated_at' => $now,
            );
        }
        DB::table('soporte_ti_horario_atencion')->insert($rows);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('soporte_ti_horario_atencion');
    }
}
