<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddFlujosToOrganizacionMensajeria extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('organizacion_mensajeria')) {
            return;
        }
        if (!Schema::hasColumn('organizacion_mensajeria', 'flujos')) {
            Schema::table('organizacion_mensajeria', function (Blueprint $table) {
                $table->text('flujos')->nullable()->after('rotulado_habilitado');
            });
        }

        $keys = array(
            'rotulado', 'documentos', 'datos_proveedor', 'inspeccion',
            'entrega', 'cobranza', 'reminder_pago', 'factura_guia',
            'contabilidad', 'calculadora', 'cotizacion_pdf', 'cbm_alerta',
            'arrive_date', 'cambio_consolidado', 'comprobante_form',
        );

        $rows = DB::table('organizacion_mensajeria')->get();
        foreach ($rows as $row) {
            $on = (int) $row->envios_habilitados === 1;
            $flujos = array();
            foreach ($keys as $key) {
                $flujos[$key] = $on;
            }
            if ((int) $row->rotulado_habilitado === 1) {
                $flujos['rotulado'] = true;
            } elseif ((int) $row->rotulado_habilitado === 0) {
                $flujos['rotulado'] = $on ? $flujos['rotulado'] : false;
            }
            DB::table('organizacion_mensajeria')
                ->where('id', $row->id)
                ->update(['flujos' => json_encode($flujos)]);
        }
    }

    public function down()
    {
        if (!Schema::hasTable('organizacion_mensajeria') || !Schema::hasColumn('organizacion_mensajeria', 'flujos')) {
            return;
        }
        Schema::table('organizacion_mensajeria', function (Blueprint $table) {
            $table->dropColumn('flujos');
        });
    }
}
