<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateContenedorProveedorArriveDateHistoryTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('contenedor_proveedor_arrive_date_history')) {
            Schema::create('contenedor_proveedor_arrive_date_history', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('id_proveedor');
                $table->unsignedInteger('id_contenedor')->nullable();
                $table->string('field', 32);
                $table->date('value')->nullable();
                $table->string('source', 64)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['id_proveedor', 'created_at'], 'idx_cpadh_proveedor_created');
                $table->index(['id_contenedor', 'id_proveedor'], 'idx_cpadh_contenedor_proveedor');
            });
        }

        $this->backfillCurrentDates();
    }

    public function down()
    {
        Schema::dropIfExists('contenedor_proveedor_arrive_date_history');
    }

    private function backfillCurrentDates(): void
    {
        if (!Schema::hasTable('contenedor_proveedor_arrive_date_history')) {
            return;
        }

        $now = now();

        DB::table('contenedor_consolidado_cotizacion_proveedores')
            ->select('id', 'id_contenedor', 'arrive_date', 'arrive_date_china')
            ->orderBy('id')
            ->chunkById(500, function ($proveedores) use ($now) {
                $rows = [];

                foreach ($proveedores as $proveedor) {
                    $arriveDate = $this->normalizeDate($proveedor->arrive_date);
                    if ($arriveDate !== null) {
                        $rows[] = [
                            'id_proveedor' => (int) $proveedor->id,
                            'id_contenedor' => $proveedor->id_contenedor ? (int) $proveedor->id_contenedor : null,
                            'field' => 'arrive_date',
                            'value' => $arriveDate,
                            'source' => 'backfill',
                            'created_at' => $now,
                        ];
                    }

                    $arriveDateChina = $this->normalizeDate($proveedor->arrive_date_china);
                    if ($arriveDateChina !== null) {
                        $rows[] = [
                            'id_proveedor' => (int) $proveedor->id,
                            'id_contenedor' => $proveedor->id_contenedor ? (int) $proveedor->id_contenedor : null,
                            'field' => 'arrive_date_china',
                            'value' => $arriveDateChina,
                            'source' => 'backfill',
                            'created_at' => $now,
                        ];
                    }
                }

                if ($rows !== []) {
                    DB::table('contenedor_proveedor_arrive_date_history')->insert($rows);
                }
            });
    }

    /**
     * @param mixed $value
     */
    private function normalizeDate($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '' || in_array($text, ['0000-00-00', '0000-00-00 00:00:00'], true)) {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
            return $text;
        }

        try {
            return \Carbon\Carbon::parse($text)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}
