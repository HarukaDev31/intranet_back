<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddProfileFieldsToUsuarioTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('usuario', function (Blueprint $table) {
            // Agregar ID_Pais si no existe
            if (!Schema::hasColumn('usuario', 'ID_Pais')) {
                $table->unsignedInteger('ID_Pais')->nullable()->after('ID_Organizacion');
                $table->foreign('ID_Pais')->references('ID_Pais')->on('pais')->onDelete('set null');
            }

            // Agregar ID_Departamento si no existe
            if (!Schema::hasColumn('usuario', 'ID_Departamento')) {
                $table->unsignedInteger('ID_Departamento')->nullable()->after('ID_Pais');
                $table->foreign('ID_Departamento')->references('ID_Departamento')->on('departamento')->onDelete('set null');
            }

            // Agregar ID_Provincia si no existe
            if (!Schema::hasColumn('usuario', 'ID_Provincia')) {
                $table->unsignedInteger('ID_Provincia')->nullable()->after('ID_Departamento');
                $table->foreign('ID_Provincia')->references('ID_Provincia')->on('provincia')->onDelete('set null');
            }

            // Agregar ID_Distrito si no existe
            if (!Schema::hasColumn('usuario', 'ID_Distrito')) {
                $table->unsignedInteger('ID_Distrito')->nullable()->after('ID_Provincia');
                $table->foreign('ID_Distrito')->references('ID_Distrito')->on('distrito')->onDelete('set null');
            }

            // Agregar Fe_Nacimiento si no existe
            if (!Schema::hasColumn('usuario', 'Fe_Nacimiento')) {
                $table->date('Fe_Nacimiento')->nullable()->after('No_Nombres_Apellidos');
            }

            // Agregar Nu_Documento si no existe
            if (!Schema::hasColumn('usuario', 'Nu_Documento')) {
                $table->string('Nu_Documento', 20)->nullable()->after('Fe_Nacimiento');
            }

            // Agregar Txt_Objetivos si no existe
            if (!Schema::hasColumn('usuario', 'Txt_Objetivos')) {
                $table->text('Txt_Objetivos')->nullable()->after('Nu_Celular');
            }

            // Agregar Txt_Foto si no existe
            if (!Schema::hasColumn('usuario', 'Txt_Foto')) {
                $table->string('Txt_Foto')->nullable()->after('Txt_Objetivos');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('usuario', function (Blueprint $table) {
            // Eliminar foreign keys primero
            if (Schema::hasColumn('usuario', 'ID_Distrito')) {
                $table->dropForeign(['ID_Distrito']);
                $table->dropColumn('ID_Distrito');
            }
            
            if (Schema::hasColumn('usuario', 'ID_Provincia')) {
                $table->dropForeign(['ID_Provincia']);
                $table->dropColumn('ID_Provincia');
            }
            
            if (Schema::hasColumn('usuario', 'ID_Departamento')) {
                $table->dropForeign(['ID_Departamento']);
                $table->dropColumn('ID_Departamento');
            }
            
            if (Schema::hasColumn('usuario', 'ID_Pais')) {
                $table->dropForeign(['ID_Pais']);
                $table->dropColumn('ID_Pais');
            }

            // Eliminar otras columnas
            if (Schema::hasColumn('usuario', 'Fe_Nacimiento')) {
                $table->dropColumn('Fe_Nacimiento');
            }
            
            if (Schema::hasColumn('usuario', 'Nu_Documento')) {
                $table->dropColumn('Nu_Documento');
            }
            
            if (Schema::hasColumn('usuario', 'Txt_Objetivos')) {
                $table->dropColumn('Txt_Objetivos');
            }
            
            if (Schema::hasColumn('usuario', 'Txt_Foto')) {
                $table->dropColumn('Txt_Foto');
            }
        });
    }
}

