<?php

use App\Services\ManualUsuario\ManualUsuarioCursoAlumnosSeeder;
use Illuminate\Database\Migrations\Migration;

class InsertManualPedidosCursoAlumnos extends Migration
{
    public function up()
    {
        (new ManualUsuarioCursoAlumnosSeeder())->seed();
    }

    public function down()
    {
        \Illuminate\Support\Facades\DB::table('manual_paginas')
            ->where('role_slug', 'comercial')
            ->where('modulo_key', 'curso/alumnos')
            ->delete();
    }
}
