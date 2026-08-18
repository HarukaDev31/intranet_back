<?php

use Illuminate\Database\Migrations\Migration;

class ReattachManualUsuarioCapturasEnfocadas extends Migration
{
    public function up()
    {
        // Sin efectos de red/storage durante migrate. El enlace es una
        // operación explícita mediante manual:attach-capturas.
    }

    public function down()
    {
        // Las capturas quedan; no se deshacen los media_id.
    }
}
