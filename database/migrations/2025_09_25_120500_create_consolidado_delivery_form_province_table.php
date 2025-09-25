<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConsolidadoDeliveryFormProvinceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('consolidado_delivery_form_province');
        Schema::create('consolidado_delivery_form_province', function (Blueprint $table) {
            $table->id();
           //id_contenedor id_user id_cotizacion importer_nmae voucher_doc voucher_doc_type voucher_name voucher_email id_agency r_type r_doc r_name r_phone id_department id_province id_district agency_address_initial_delivery agency_address_final_delivery home_adress_delivery
           $table->unsignedInteger('id_contenedor');
           $table->unsignedBigInteger('id_user');
           $table->integer('id_cotizacion');
           $table->string('importer_nmae');
           $table->string('voucher_doc');
           $table->enum('voucher_doc_type', ['BOLETA', 'FACTURA']);
           $table->string('voucher_name');
           $table->string('voucher_email');
           $table->unsignedBigInteger('id_agency');
           $table->enum('r_type', ['PERSONA NATURAL', 'EMPRESA']);
           $table->string('r_doc');
           $table->string('r_name');
           $table->string('r_phone');
           $table->unsignedInteger('id_department');
           $table->unsignedInteger('id_province');
           $table->unsignedInteger('id_district');
           $table->string('agency_address_initial_delivery');
           $table->string('agency_address_final_delivery');
           $table->string('home_adress_delivery');
           $table->foreign('id_contenedor')->references('id')->on('carga_consolidada_contenedor')->onDelete('cascade');
           $table->foreign('id_user')->references('id')->on('users')->onDelete('cascade');
           $table->foreign('id_cotizacion')->references('id')->on('contenedor_consolidado_cotizacion')->onDelete('cascade');
           $table->foreign('id_agency')->references('id')->on('delivery_agencies')->onDelete('cascade');
           $table->foreign('id_department')->references('ID_Departamento')->on('departamento')->onDelete('cascade');
           $table->foreign('id_province')->references('ID_Provincia')->on('provincia')->onDelete('cascade');
           $table->foreign('id_district')->references('ID_Distrito')->on('distrito')->onDelete('cascade');
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
        Schema::dropIfExists('consolidado_delivery_form_province');
    }
}
