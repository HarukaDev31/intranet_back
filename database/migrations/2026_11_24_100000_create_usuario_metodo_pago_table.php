<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CFG-26-0056: método de pago preferido de cada usuario interno (billetera digital + número),
 * usado por Administración para pagarle reintegros de viáticos.
 * Si el usuario no lo configura, se asume Yape con el celular de su cuenta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('usuario_metodo_pago')) {
            Schema::create('usuario_metodo_pago', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('ID_Usuario')->unique();
                $table->string('metodo', 30);
                $table->string('numero', 30);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_metodo_pago');
    }
};
