<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddUnsignedToReferenceTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Agregar unsigned a id en bd_entidades_reguladoras
    
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Revertir unsigned de id en bd_entidades_reguladoras
        DB::statement('ALTER TABLE bd_entidades_reguladoras MODIFY id int NOT NULL AUTO_INCREMENT');
        
        // Revertir unsigned de id en bd_productos
        DB::statement('ALTER TABLE bd_productos MODIFY id int NOT NULL AUTO_INCREMENT');
    }
}
