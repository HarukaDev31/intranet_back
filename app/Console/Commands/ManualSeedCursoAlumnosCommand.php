<?php

namespace App\Console\Commands;

use App\Services\ManualUsuario\ManualUsuarioCursoAlumnosSeeder;
use Illuminate\Console\Command;

class ManualSeedCursoAlumnosCommand extends Command
{
    protected $signature = 'manual:seed-curso-alumnos';

    protected $description = 'Siembra (o regenera) el artículo CMS Pedidos de Curso → Alumnos para el rol Comercial';

    public function handle()
    {
        $result = (new ManualUsuarioCursoAlumnosSeeder())->seed();
        $this->info(($result['created'] ? 'Creada' : 'Actualizada') . ' página id=' . $result['page_id'] . ' (comercial / curso/alumnos).');

        return 0;
    }
}
