<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyLinkFieldToTextInProductosImportadosExcelTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('productos_importados_excel', function (Blueprint $table) {
            $table->text('link')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('productos_importados_excel', function (Blueprint $table) {
            $table->string('link', 255)->nullable()->change();
        });
    }
}
