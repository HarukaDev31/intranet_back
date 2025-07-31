<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FixAutoIncrementInRegulacionesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Corregir AUTO_INCREMENT en bd_entidades_reguladoras
        DB::statement('ALTER TABLE bd_entidades_reguladoras MODIFY id int NOT NULL AUTO_INCREMENT');
        
        // Corregir AUTO_INCREMENT en bd_productos
        DB::statement('ALTER TABLE bd_productos MODIFY id int NOT NULL AUTO_INCREMENT');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Revertir AUTO_INCREMENT en bd_entidades_reguladoras
        DB::statement('ALTER TABLE bd_entidades_reguladoras MODIFY id int NOT NULL');
        
        // Revertir AUTO_INCREMENT en bd_productos
        DB::statement('ALTER TABLE bd_productos MODIFY id int NOT NULL');
    }
}
