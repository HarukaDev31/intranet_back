<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeUserBusinessFieldsNullable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('ALTER TABLE user_business MODIFY name VARCHAR(255) NULL');
        Schema::table('user_business', function (Blueprint $table) {
            // Hacer que todos los campos sean nullable
            $table->string('name')->nullable()->change();
            $table->string('ruc')->nullable()->change();
            $table->string('comercial_capacity')->nullable()->change();
            $table->string('rubric')->nullable()->change();
            
            // Quitar la restricción unique del RUC ya que puede ser null
            $table->dropUnique(['ruc']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_business', function (Blueprint $table) {
            // Revertir los campos a no nullable (requiere datos existentes)
            $table->string('name')->nullable(false)->change();
            $table->string('ruc')->nullable(false)->change();
            $table->string('comercial_capacity')->nullable(false)->change();
            $table->string('rubric')->nullable(false)->change();
            
            // Restaurar la restricción unique del RUC
            $table->unique('ruc');
        });
    }
}
