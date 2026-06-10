<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateContenedorConsolidadoDocumentacionObservacionesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('contenedor_consolidado_documentacion_observaciones')) {
            Schema::dropIfExists('contenedor_consolidado_documentacion_observaciones');
        }

        $proveedorIdColumn = DB::selectOne("SHOW COLUMNS FROM contenedor_consolidado_cotizacion_proveedores LIKE 'id'");
        $proveedorIdType = strtolower($proveedorIdColumn->Type ?? 'int(10) unsigned');
        $proveedorIdIsBigInt = strpos($proveedorIdType, 'bigint') !== false;
        $proveedorIdIsUnsigned = strpos($proveedorIdType, 'unsigned') !== false;

        Schema::create('contenedor_consolidado_documentacion_observaciones', function (Blueprint $table) use (
            $proveedorIdIsBigInt,
            $proveedorIdIsUnsigned
        ) {
            $table->bigIncrements('id');

            $idProveedorColumn = $proveedorIdIsBigInt
                ? $table->bigInteger('id_proveedor')
                : $table->integer('id_proveedor');

            if ($proveedorIdIsUnsigned) {
                $idProveedorColumn->unsigned();
            }

            $idProveedorColumn->index('cc_doc_obs_id_proveedor_idx');

            $table->string('categoria', 40);
            $table->text('mensaje');
            $table->unsignedBigInteger('user_id')->index('cc_doc_obs_user_id_idx');
            $table->string('user_name', 120);
            $table->timestamps();

            $table->foreign('id_proveedor', 'cc_doc_obs_id_proveedor_fk')
                ->references('id')
                ->on('contenedor_consolidado_cotizacion_proveedores')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('contenedor_consolidado_documentacion_observaciones');
    }
}
