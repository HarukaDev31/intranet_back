<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Insertar país
        DB::table('pais')->insert([
            'ID_Pais' => 1,
            'No_Pais' => 'México',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insertar empresa
        DB::table('empresa')->insert([
            'ID_Empresa' => 1,
            'ID_Pais' => 1,
            'Nu_Estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insertar organización
        DB::table('organizacion')->insert([
            'ID_Organizacion' => 1,
            'ID_Empresa' => 1,
            'Nu_Estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insertar usuario de prueba
        DB::table('usuario')->insert([
            'ID_Usuario' => 1,
            'No_Usuario' => 'admin',
            'No_Password' => Hash::make('password123'),
            'Nu_Estado' => 1,
            'ID_Empresa' => 1,
            'ID_Organizacion' => 1,
            'Fe_Creacion' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insertar moneda
        DB::table('moneda')->insert([
            'ID_Moneda' => 1,
            'ID_Empresa' => 1,
            'No_Moneda' => 'Peso Mexicano',
            'Símbolo_Moneda' => 'MXN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insertar grupo
        DB::table('grupo')->insert([
            'ID_Grupo' => 1,
            'ID_Empresa' => 1,
            'ID_Organizacion' => 1,
            'No_Grupo' => 'Administradores',
            'No_Grupo_Descripcion' => 'Grupo de administradores del sistema',
            'Nu_Tipo_Privilegio_Acceso' => 1,
            'Nu_Notificacion' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insertar relación grupo_usuario
        DB::table('grupo_usuario')->insert([
            'ID_Grupo_Usuario' => 1,
            'ID_Usuario' => 1,
            'ID_Grupo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insertar almacén
        DB::table('almacen')->insert([
            'ID_Almacen' => 1,
            'ID_Organizacion' => 1,
            'No_Almacen' => 'Almacén Principal',
            'Nu_Estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insertar subdominio tienda virtual
        DB::table('subdominio_tienda_virtual')->insert([
            'ID_Subdominio_Tienda_Virtual' => 1,
            'ID_Empresa' => 1,
            'No_Dominio_Tienda_Virtual' => 'probusiness.com',
            'No_Subdominio_Tienda_Virtual' => 'tienda',
            'Nu_Estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
} 