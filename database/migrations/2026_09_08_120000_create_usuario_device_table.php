<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsuarioDeviceTable extends Migration
{
    /**
     * Dispositivos móviles (Android/iOS) registrados por usuario para envío de push (FCM).
     *
     * @return void
     */
    public function up()
    {
        Schema::create('usuario_device', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_usuario');
            $table->string('fcm_token', 512);
            $table->string('platform', 20);
            $table->string('device_id', 191)->nullable();
            $table->string('app_version', 30)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique('fcm_token');
            $table->index('id_usuario');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('usuario_device');
    }
}
