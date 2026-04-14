<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateContenedorConsolidadoCotizacionDeliveryServicioTable extends Migration
{
    private $table = 'contenedor_consolidado_cotizacion_delivery_servicio';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable($this->table)) {
            Schema::create($this->table, function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('id_cotizacion');
                $table->string('tipo_servicio', 32);
                $table->decimal('importe', 12, 2)->default(0);
                $table->index('id_cotizacion', 'idx_cccds_id_cotizacion');
            });
        }

        // Migrar datos existentes (una fila por cotización que tenga tipo o importe delivery)
        if (Schema::hasTable($this->table) && Schema::hasTable('contenedor_consolidado_cotizacion')) {
            $exists = DB::table($this->table)->exists();
            if (!$exists) {
                $rows = DB::table('contenedor_consolidado_cotizacion')
                    ->whereNull('deleted_at')
                    ->get(['id', 'tipo_servicio', 'total_pago_delivery']);

                foreach ($rows as $r) {
                    $tipo = $r->tipo_servicio ? strtoupper(trim($r->tipo_servicio)) : 'DELIVERY';
                    if (!in_array($tipo, ['DELIVERY', 'MONTACARGA'], true)) {
                        $tipo = 'DELIVERY';
                    }
                    $imp = $r->total_pago_delivery !== null ? (float) $r->total_pago_delivery : 0;
                    if ($imp > 0 || $r->tipo_servicio) {
                        DB::table($this->table)->insert([
                            'id_cotizacion' => (int) $r->id,
                            'tipo_servicio' => $tipo,
                            'importe' => $imp,
                        ]);
                    }
                }
            }

            // Alinear total_pago_delivery con la suma de líneas de servicio
            DB::statement(
                'UPDATE contenedor_consolidado_cotizacion CC
                INNER JOIN (
                    SELECT id_cotizacion, SUM(importe) AS s
                    FROM ' . $this->table . '
                    GROUP BY id_cotizacion
                ) t ON t.id_cotizacion = CC.id
                SET CC.total_pago_delivery = t.s'
            );

            $firstLines = DB::table($this->table . ' as s')
                ->select('s.id_cotizacion', DB::raw('MIN(s.id) as min_id'))
                ->groupBy('s.id_cotizacion')
                ->get();

            foreach ($firstLines as $fl) {
                $first = DB::table($this->table)->where('id', $fl->min_id)->first();
                if ($first && in_array($first->tipo_servicio, ['DELIVERY', 'MONTACARGA'], true)) {
                    DB::table('contenedor_consolidado_cotizacion')
                        ->where('id', $fl->id_cotizacion)
                        ->update(['tipo_servicio' => $first->tipo_servicio]);
                }
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
        Schema::dropIfExists($this->table);
    }
}
