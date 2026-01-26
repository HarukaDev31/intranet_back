<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddIdContenedorDestinoToContenedorConsolidadoCotizacion extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Desactivar foreign key checks temporalmente
      
        
        // Agregar la columna como nullable con foreign key
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            $table->unsignedInteger('id_contenedor_destino')->nullable()->after('id_contenedor_pago');
            
            $table->foreign('id_contenedor_destino')
                ->references('id')
                ->on('carga_consolidada_contenedor')
                ->onDelete('set null');
        });
        
        // Reactivar foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Desactivar foreign key checks temporalmente
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        
        // Obtener el nombre real de la foreign key
        $foreignKeyName = DB::selectOne("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'contenedor_consolidado_cotizacion'
            AND COLUMN_NAME = 'id_contenedor_destino'
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        
        // Eliminar la foreign key si existe
        if ($foreignKeyName) {
            DB::statement("ALTER TABLE contenedor_consolidado_cotizacion DROP FOREIGN KEY `{$foreignKeyName->CONSTRAINT_NAME}`");
        }
        
        // Eliminar la columna
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            $table->dropColumn('id_contenedor_destino');
        });
        
        // Reactivar foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }
}
