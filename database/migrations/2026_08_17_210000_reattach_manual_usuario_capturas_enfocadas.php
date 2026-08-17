<?php

use App\Services\ManualUsuario\ManualUsuarioCapturasAttacher;
use Illuminate\Database\Migrations\Migration;

class ReattachManualUsuarioCapturasEnfocadas extends Migration
{
    public function up()
    {
        (new ManualUsuarioCapturasAttacher())->attach();
    }

    public function down()
    {
        // Las capturas quedan; no se deshacen los media_id.
    }
}
