<?php

use Illuminate\Database\Migrations\Migration;

class AttachManualUsuarioCapturas extends Migration
{
    public function up()
    {
        // Sin efectos de red/storage durante migrate.
        // Ejecutar manual:attach-capturas --dry-run --strict y luego
        // manual:attach-capturas --strict como operación explícita.
    }

    public function down()
    {
        // Las capturas quedan; no se deshacen los media_id.
    }
}
