<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
class AddAgencyRucToConsolidadoDeliveryFormProvinceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Verificar si la tabla existe
        if (!Schema::hasTable('consolidado_delivery_form_province')) {
            // Si no existe, crear la tabla completa
            Schema::create('consolidado_delivery_form_province', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('id_contenedor');
                $table->unsignedBigInteger('id_user');
                $table->integer('id_cotizacion');
                $table->string('importer_nmae');
                $table->string('voucher_doc');
                $table->enum('voucher_doc_type', ['BOLETA', 'FACTURA']);
                $table->string('voucher_name');
                $table->string('voucher_email');
                $table->unsignedBigInteger('id_agency');
                $table->string('agency_ruc')->nullable();
                $table->string('agency_name')->nullable();
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

                // Claves foráneas
                $table->foreign('id_contenedor')->references('id')->on('carga_consolidada_contenedor')->onDelete('cascade');
                $table->foreign('id_user')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('id_cotizacion')->references('id')->on('contenedor_consolidado_cotizacion')->onDelete('cascade');
                $table->foreign('id_agency')->references('id')->on('delivery_agencies')->onDelete('cascade');
                $table->foreign('id_department')->references('ID_Departamento')->on('departamento')->onDelete('cascade');
                $table->foreign('id_province')->references('ID_Provincia')->on('provincia')->onDelete('cascade');
                $table->foreign('id_district')->references('ID_Distrito')->on('distrito')->onDelete('cascade');
                
                $table->timestamps();
            });
        } else {
            // Si la tabla existe, solo agregar el campo agency_ruc si no lo tiene
            if (!Schema::hasColumn('consolidado_delivery_form_province', 'agency_ruc')) {
                Schema::table('consolidado_delivery_form_province', function (Blueprint $table) {
                    $table->string('agency_ruc')->nullable()->after('id_agency');
                    $table->index('agency_ruc');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('consolidado_delivery_form_province')) {
            if (Schema::hasColumn('consolidado_delivery_form_province', 'agency_ruc')) {
                Schema::table('consolidado_delivery_form_province', function (Blueprint $table) {
                    $table->dropIndex(['agency_ruc']);
                    $table->dropColumn('agency_ruc');
                });
            }
        }
    }
}
