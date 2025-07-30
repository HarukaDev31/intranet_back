<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuthTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Tabla de países
       /* Schema::create('pais', function (Blueprint $table) {
            $table->id('ID_Pais');
            $table->string('No_Pais');
            $table->timestamps();
        });

        // Tabla de empresas
        Schema::create('empresa', function (Blueprint $table) {
            $table->id('ID_Empresa');
            $table->unsignedBigInteger('ID_Pais');
            $table->integer('Nu_Estado')->default(1); // 1 = Activo, 0 = Inactivo
            $table->timestamps();

            $table->foreign('ID_Pais')->references('ID_Pais')->on('pais');
        });

        // Tabla de organizaciones
        Schema::create('organizacion', function (Blueprint $table) {
            $table->id('ID_Organizacion');
            $table->unsignedBigInteger('ID_Empresa');
            $table->integer('Nu_Estado')->default(1); // 1 = Activo, 0 = Inactivo
            $table->timestamps();

            $table->foreign('ID_Empresa')->references('ID_Empresa')->on('empresa');
        });

        // Tabla de usuarios
        Schema::create('usuario', function (Blueprint $table) {
            $table->id('ID_Usuario');
            $table->string('No_Usuario')->unique();
            $table->string('No_Password');
            $table->integer('Nu_Estado')->default(1); // 1 = Activo, 0 = Inactivo
            $table->unsignedBigInteger('ID_Empresa');
            $table->unsignedBigInteger('ID_Organizacion');
            $table->timestamp('Fe_Creacion')->useCurrent();
            $table->timestamps();

            $table->foreign('ID_Empresa')->references('ID_Empresa')->on('empresa');
            $table->foreign('ID_Organizacion')->references('ID_Organizacion')->on('organizacion');
        });

        // Tabla de monedas
        Schema::create('moneda', function (Blueprint $table) {
            $table->id('ID_Moneda');
            $table->unsignedBigInteger('ID_Empresa');
            $table->string('No_Moneda')->nullable();
            $table->string('Símbolo_Moneda')->nullable();
            $table->timestamps();

            $table->foreign('ID_Empresa')->references('ID_Empresa')->on('empresa');
        });

        // Tabla de grupos
        Schema::create('grupo', function (Blueprint $table) {
            $table->id('ID_Grupo');
            $table->unsignedBigInteger('ID_Empresa');
            $table->unsignedBigInteger('ID_Organizacion');
            $table->string('No_Grupo');
            $table->string('No_Grupo_Descripcion')->nullable();
            $table->integer('Nu_Tipo_Privilegio_Acceso')->default(1);
            $table->integer('Nu_Notificacion')->default(1);
            $table->timestamps();

            $table->foreign('ID_Empresa')->references('ID_Empresa')->on('empresa');
            $table->foreign('ID_Organizacion')->references('ID_Organizacion')->on('organizacion');
        });

        // Tabla de grupo_usuario (relación muchos a muchos)
        Schema::create('grupo_usuario', function (Blueprint $table) {
            $table->id('ID_Grupo_Usuario');
            $table->unsignedBigInteger('ID_Usuario');
            $table->unsignedBigInteger('ID_Grupo');
            $table->timestamps();

            $table->foreign('ID_Usuario')->references('ID_Usuario')->on('usuario');
            $table->foreign('ID_Grupo')->references('ID_Grupo')->on('grupo');
        });

        // Tabla de almacenes
        Schema::create('almacen', function (Blueprint $table) {
            $table->id('ID_Almacen');
            $table->unsignedBigInteger('ID_Organizacion');
            $table->string('No_Almacen');
            $table->integer('Nu_Estado')->default(1); // 1 = Activo, 0 = Inactivo
            $table->timestamps();

            $table->foreign('ID_Organizacion')->references('ID_Organizacion')->on('organizacion');
        });

        // Tabla de subdominio_tienda_virtual
        Schema::create('subdominio_tienda_virtual', function (Blueprint $table) {
            $table->id('ID_Subdominio_Tienda_Virtual');
            $table->unsignedBigInteger('ID_Empresa');
            $table->string('No_Dominio_Tienda_Virtual')->nullable();
            $table->string('No_Subdominio_Tienda_Virtual')->nullable();
            $table->integer('Nu_Estado')->default(1); // 1 = Activo, 0 = Inactivo
            $table->timestamps();

            $table->foreign('ID_Empresa')->references('ID_Empresa')->on('empresa');
        });*/
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('subdominio_tienda_virtual');
        Schema::dropIfExists('almacen');
        Schema::dropIfExists('grupo_usuario');
        Schema::dropIfExists('grupo');
        Schema::dropIfExists('moneda');
        Schema::dropIfExists('usuario');
        Schema::dropIfExists('organizacion');
        Schema::dropIfExists('empresa');
        Schema::dropIfExists('pais');
    }
} 