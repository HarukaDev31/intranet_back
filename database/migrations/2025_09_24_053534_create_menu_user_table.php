<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMenuUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('menu_user', function (Blueprint $table) {
            $table->id('ID_Menu');
            $table->unsignedTinyInteger('ID_Padre')->default(0);
            $table->unsignedTinyInteger('Nu_Orden');
            $table->string('No_Menu', 50);
            $table->string('No_Menu_Url', 100);
            $table->string('No_Class_Controller', 50);
            $table->string('Txt_Css_Icons', 30);
            $table->unsignedTinyInteger('Nu_Separador')->default(0);
            $table->tinyInteger('Nu_Seguridad')->default(0);
            $table->tinyInteger('Nu_Activo')->default(0);
            $table->tinyInteger('Nu_Tipo_Sistema')->default(0);
            $table->text('Txt_Url_Video')->nullable();
            $table->text('No_Menu_China')->nullable();
            $table->text('url_intranet_v2')->nullable();
            $table->tinyInteger('show_father')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('menu_user');
    }
}
