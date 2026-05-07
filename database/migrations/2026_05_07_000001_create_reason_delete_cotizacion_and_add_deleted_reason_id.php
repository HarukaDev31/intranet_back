<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateReasonDeleteCotizacionAndAddDeletedReasonId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('reason_delete_cotizacion')) {
            Schema::create('reason_delete_cotizacion', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 120);
                $table->timestamps();
            });
        }

        $exists = DB::table('reason_delete_cotizacion')->where('name', 'OTROS')->exists();
        if (!$exists) {
            DB::table('reason_delete_cotizacion')->insert([
                'name' => 'OTROS',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('contenedor_consolidado_cotizacion') && !Schema::hasColumn('contenedor_consolidado_cotizacion', 'deleted_reason_id')) {
            Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
                $table->unsignedInteger('deleted_reason_id')->nullable()->after('deleted_at');
                $table->foreign('deleted_reason_id', 'fk_ccc_deleted_reason_id')
                    ->references('id')
                    ->on('reason_delete_cotizacion')
                    ->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('contenedor_consolidado_cotizacion') && Schema::hasColumn('contenedor_consolidado_cotizacion', 'deleted_reason_id')) {
            Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
                $table->dropForeign('fk_ccc_deleted_reason_id');
                $table->dropColumn('deleted_reason_id');
            });
        }

        Schema::dropIfExists('reason_delete_cotizacion');
    }
}

