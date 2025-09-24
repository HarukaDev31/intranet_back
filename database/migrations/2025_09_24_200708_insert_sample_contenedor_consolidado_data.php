<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class InsertSampleContenedorConsolidadoData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Insertar datos en contenedor_consolidado_cotizacion
        DB::table('contenedor_consolidado_cotizacion')->insert([
            'id' => 929,
            'id_contenedor_pago' => null,
            'id_cliente' => 166,
            'id_contenedor' => 62,
            'id_tipo_cliente' => 2,
            'fecha' => '2025-07-24 00:00:00',
            'nombre' => 'ANTHONY ROCCA CAQUI2',
            'documento' => '',
            'correo' => null,
            'telefono' => null,
            'volumen' => 2.88,
            'cotizacion_file_url' => 'https://intranet.probusiness.pe/assets/images/agentecompra/1758146989_1753371656_COTIZACI%C3%93N_ANTHONY%20ROCCA%20CAQUI%202%20%281%29.xlsm',
            'cotizacion_final_file_url' => null,
            'estado' => 'PENDIENTE',
            'volumen_doc' => '2.88',
            'valor_doc' => 2300.00,
            'valor_cot' => 2300.00,
            'volumen_china' => '2.88',
            'factura_comercial' => null,
            'id_usuario' => 28912,
            'monto' => 936.00,
            'fob' => 2300.00,
            'impuestos' => 644.34,
            'tarifa' => 325.00,
            'excel_comercial' => null,
            'excel_confirmacion' => null,
            'vol_selected' => 'volumen_china',
            'estado_cliente' => 'DOCUMENTACION',
            'peso' => 33.40,
            'tarifa_final' => 325.00,
            'monto_final' => 936.00,
            'volumen_final' => 2.88,
            'guia_remision_url' => null,
            'factura_general_url' => null,
            'cotizacion_final_url' => 'https://intranet.probusiness.pe/assets/cargaconsolidada/cotizacionesFinales/1758252186_29.CotizacionANTHONY%20ROCCA%20CAQUI2.xlsx',
            'estado_cotizador' => 'CONFIRMADO',
            'fecha_confirmacion' => '2025-07-24 10:37:33',
            'estado_pagos_coordinacion' => 'PENDIENTE',
            'estado_cotizacion_final' => 'COTIZADO',
            'impuestos_final' => 644.34,
            'fob_final' => 2300.00,
            'note_administracion' => null,
            'id_cliente_importacion' => null,
            'status_cliente_doc' => 'Completado',
            'logistica_final' => 936.00,
            'qty_item' => 1,
            'updated_at' => '2025-09-17 17:09:49',
            'created_at' => now()
        ]);

        // Insertar datos en contenedor_consolidado_cotizacion_proveedores
        DB::table('contenedor_consolidado_cotizacion_proveedores')->insert([
            'id' => 1330,
            'id_contenedor_pago' => null,
            'id_cotizacion' => 929,
            'products' => ' BACKPACKS WITH USB CABLE',
            'qty_box' => 10,
            'cbm_total' => 2.88,
            'peso' => 33.40,
            'supplier' => null,
            'code_supplier' => 'ANRO12-1',
            'supplier_phone' => null,
            'qty_box_china' => 10,
            'cbm_total_china' => 2.88,
            'arrive_date_china' => '1900-01-01', // Cambiado de '0000-00-00' por compatibilidad
            'estado_almacen' => null,
            'estado_china' => 'PENDIENTE',
            'id_contenedor' => 62,
            'nota' => '',
            'estados' => 'RESERVADO',
            'volumen_doc' => 2.88,
            'valor_doc' => 2300.00,
            'factura_comercial' => 'https://intranet.probusiness.pe/assets/images/agentecompra/1756418071_1754717135_comercial__invoice_mochila.pdf',
            'excel_confirmacion' => 'https://intranet.probusiness.pe/assets/images/agentecompra/1756917963_EXCEL%20ANTHONY.xlsx',
            'send_rotulado_status' => 'SENDED',
            'packing_list' => 'https://intranet.probusiness.pe/assets/images/agentecompra/1756417998_1754717142_packing_list_mochila.pdf',
            'estados_proveedor' => 'LOADED',
            'updated_at' => now(),
            'created_at' => now()
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Eliminar los registros insertados
        DB::table('contenedor_consolidado_cotizacion_proveedores')->where('id', 1330)->delete();
        DB::table('contenedor_consolidado_cotizacion')->where('id', 929)->delete();
    }
}
