<?php

use App\Services\ManualUsuario\ManualUsuarioRoleManualSeeder;
use Illuminate\Database\Migrations\Migration;

class SeedManualUsuarioCargaCoordinacionCopy extends Migration
{
    public function up()
    {
        (new ManualUsuarioRoleManualSeeder())->seed(null, 'cargaconsolidada*');
    }

    public function down()
    {
        // Idempotente: el seeder reescribe las páginas de carga consolidada.
    }
}
