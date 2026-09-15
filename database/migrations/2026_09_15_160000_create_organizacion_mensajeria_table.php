<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateOrganizacionMensajeriaTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('organizacion_mensajeria')) {
            Schema::create('organizacion_mensajeria', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('organizacion_id')->unique();
                $table->unsignedTinyInteger('envios_habilitados')->default(0);
                $table->unsignedTinyInteger('rotulado_habilitado')->default(0);
                $table->string('img_rotulado_paso1', 255)->nullable();
                $table->string('img_rotulado_paso2', 255)->nullable();
                $table->string('img_rotulado_direccion', 255)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('organizacion')) {
            return;
        }

        $orgs = DB::table('organizacion')->select('ID_Organizacion')->get();
        foreach ($orgs as $org) {
            $id = (int) $org->ID_Organizacion;
            $exists = DB::table('organizacion_mensajeria')->where('organizacion_id', $id)->exists();
            if ($exists) {
                continue;
            }
            $habilitado = $id === 1 ? 1 : 0;
            DB::table('organizacion_mensajeria')->insert([
                'organizacion_id' => $id,
                'envios_habilitados' => $habilitado,
                'rotulado_habilitado' => $habilitado,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('organizacion_mensajeria');
    }
}
