<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddIdcityAndPhoneToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Agregar idcity si no existe (usamos distrito_id que ya existe, pero agregamos alias si es necesario)
            // Verificamos si existe phone, si no lo agregamos
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('whatsapp');
            }
            // Si no existe idcity pero existe distrito_id, podemos usar distrito_id como idcity
            // Agregamos idcity como alias de distrito_id si no existe
            if (!Schema::hasColumn('users', 'idcity')) {
                $table->unsignedInteger('idcity')->nullable()->after('distrito_id');
            }
        });
        
        // Si idcity no tiene valores pero distrito_id sí, copiamos los valores
        DB::statement('UPDATE users SET idcity = distrito_id WHERE idcity IS NULL AND distrito_id IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'phone')) {
                $table->dropColumn('phone');
            }
            if (Schema::hasColumn('users', 'idcity')) {
                $table->dropColumn('idcity');
            }
        });
    }
}

