<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Correlativo propio de cada proveedor, único en todo Pago a Proveedor (#1686): una orden con dos proveedores
 * tiene PE¥70 y PE¥71 en vez de compartir el N.° de orden. Los existentes se numeran por N.° de orden, posición e id,
 * y los códigos ya asignados se rearman con su correlativo (socio + ¥/$ según moneda + número).
 */
return new class extends Migration
{
    private const TABLA = 'pago_proveedor_solicitud_proveedores';

    private const SOCIOS = ['Perú' => 'PE', 'Ecuador' => 'EC', 'Bolivia' => 'BO'];

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLA) || Schema::hasColumn(self::TABLA, 'request_number')) {
            return;
        }
        Schema::table(self::TABLA, function (Blueprint $table) {
            $table->unsignedInteger('request_number')->nullable()->after('request_code')->comment('Correlativo global del proveedor para su código de solicitud');
        });

        $filas = DB::table(self::TABLA.' as p')
            ->leftJoin('pago_proveedor_solicitudes as s', 's.id', '=', 'p.request_id')
            ->orderByRaw('s.order_number IS NULL, s.order_number')
            ->orderBy('p.position')
            ->orderBy('p.id')
            ->get(['p.id', 'p.request_code', 'p.currency', 's.country']);
        $numero = 0;
        foreach ($filas as $fila) {
            $numero++;
            $cambios = ['request_number' => $numero];
            if ($fila->request_code !== null && $fila->request_code !== '') {
                $cambios['request_code'] = (self::SOCIOS[(string) $fila->country] ?? 'PE')
                    .($fila->currency === 'USD' ? '$' : '¥')
                    .str_pad((string) $numero, 2, '0', STR_PAD_LEFT);
            }
            DB::table(self::TABLA)->where('id', $fila->id)->update($cambios);
        }

        Schema::table(self::TABLA, function (Blueprint $table) {
            $table->unique('request_number', 'pp_solicitud_proveedores_request_number_unique');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLA) || !Schema::hasColumn(self::TABLA, 'request_number')) {
            return;
        }
        Schema::table(self::TABLA, function (Blueprint $table) {
            $table->dropUnique('pp_solicitud_proveedores_request_number_unique');
            $table->dropColumn('request_number');
        });
    }
};
