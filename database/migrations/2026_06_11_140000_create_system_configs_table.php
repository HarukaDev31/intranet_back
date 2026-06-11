<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateSystemConfigsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('system_configs')) {
            return;
        }

        Schema::create('system_configs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('key', 128)->unique();
            $table->text('value')->nullable();
            $table->string('description', 512)->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('system_configs')->insert([
            [
                'key' => 'excel_seguimiento_hora_corte',
                'value' => '20:00',
                'description' => 'Hora diaria (HH:MM) para congelar bloque histórico CARGA POR CONTACTAR en Excel seguimiento Drive',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'excel_seguimiento_timezone',
                'value' => 'America/Lima',
                'description' => 'Zona horaria del corte de Excel seguimiento Drive',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('system_configs');
    }
}
