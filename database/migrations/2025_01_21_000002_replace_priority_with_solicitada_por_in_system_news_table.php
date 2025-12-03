<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ReplacePriorityWithSolicitadaPorInSystemNewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('system_news', function (Blueprint $table) {
            // Eliminar el campo priority
            $table->dropColumn('priority');
            
            // Agregar el nuevo campo solicitada_por
            $table->enum('solicitada_por', [
                'CEO',
                'EQUIPO_DE_COORDINACION',
                'EQUIPO_DE_VENTAS',
                'EQUIPO_DE_CURSO',
                'EQUIPO_DE_DOCUMENTACION',
                'ADMINISTRACION',
                'EQUIPO_DE_TI',
                'EQUIPO_DE_MARKETING'
            ])->nullable()->after('type');
            
            $table->index('solicitada_por');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('system_news', function (Blueprint $table) {
            $table->dropIndex(['solicitada_por']);
            $table->dropColumn('solicitada_por');
            
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium')->after('type');
        });
    }
}

