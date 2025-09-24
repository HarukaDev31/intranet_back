<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMenuUserAccessTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('menu_user_access', function (Blueprint $table) {
            $table->id('ID_Menu_User_Access');
            $table->unsignedBigInteger('ID_Menu');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            // Foreign keys
            $table->foreign('ID_Menu')->references('ID_Menu')->on('menu_user')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Índice único para evitar duplicados
            $table->unique(['ID_Menu', 'user_id'], 'unique_menu_user_access');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('menu_user_access');
    }
}
