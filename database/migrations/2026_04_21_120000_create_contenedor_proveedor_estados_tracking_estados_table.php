<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateContenedorProveedorEstadosTrackingEstadosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $proveedorIdColumn = DB::selectOne("SHOW COLUMNS FROM contenedor_consolidado_cotizacion_proveedores LIKE 'id'");
        $cotizacionIdColumn = DB::selectOne("SHOW COLUMNS FROM contenedor_consolidado_cotizacion_proveedores LIKE 'id_cotizacion'");

        $proveedorIdType = strtolower($proveedorIdColumn->Type ?? '');
        $cotizacionIdType = strtolower($cotizacionIdColumn->Type ?? '');

        $proveedorIdIsBigInt = strpos($proveedorIdType, 'bigint') !== false;
        $proveedorIdIsUnsigned = strpos($proveedorIdType, 'unsigned') !== false;
        $cotizacionIdIsBigInt = strpos($cotizacionIdType, 'bigint') !== false;
        $cotizacionIdIsUnsigned = strpos($cotizacionIdType, 'unsigned') !== false;

        DB::statement('DROP TABLE IF EXISTS contenedor_proveedor_estados_tracking_estados');
        Schema::create('contenedor_proveedor_estados_tracking_estados', function (Blueprint $table) use (
            $proveedorIdIsBigInt,
            $proveedorIdIsUnsigned,
            $cotizacionIdIsBigInt,
            $cotizacionIdIsUnsigned
        ) {
            $table->bigIncrements('id');

            $idProveedorColumn = $proveedorIdIsBigInt
                ? $table->bigInteger('id_proveedor')
                : $table->integer('id_proveedor');

            if ($proveedorIdIsUnsigned) {
                $idProveedorColumn->unsigned();
            }

            $idCotizacionColumn = $cotizacionIdIsBigInt
                ? $table->bigInteger('id_cotizacion')
                : $table->integer('id_cotizacion');

            if ($cotizacionIdIsUnsigned) {
                $idCotizacionColumn->unsigned();
            }
            $idCotizacionColumn->nullable();

            $table->string('estado')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['id_proveedor', 'id_cotizacion'], 'idx_cpete_proveedor_cotizacion');
            $table->index(['id_proveedor', 'id_cotizacion', 'updated_at'], 'idx_cpete_abierto');

            $table->foreign('id_proveedor', 'fk_cpete_id_proveedor')
                ->references('id')
                ->on('contenedor_consolidado_cotizacion_proveedores')
                ->onDelete('cascade');
        });

        $now = now();

        // Backfill: insertar estado actual por cada proveedor/cotizacion.
        DB::table('contenedor_consolidado_cotizacion_proveedores')
            ->select('id', 'id_cotizacion', 'estados', 'created_at', 'updated_at')
            ->orderBy('id')
            ->chunkById(500, function ($proveedores) use ($now) {
                $rows = [];

                foreach ($proveedores as $proveedor) {
                    $createdAt = $proveedor->updated_at ?: ($proveedor->created_at ?: $now);

                    $rows[] = [
                        'id_proveedor' => $proveedor->id,
                        'id_cotizacion' => $proveedor->id_cotizacion,
                        'estado' => $proveedor->estados,
                        'created_at' => $createdAt,
                        'updated_at' => null,
                    ];
                }

                if (!empty($rows)) {
                    DB::table('contenedor_proveedor_estados_tracking_estados')->insert($rows);
                }
            }, 'id');

        // Normalizar historial: todos los rows menos el ultimo deben tener updated_at.
        $grupos = DB::table('contenedor_proveedor_estados_tracking_estados')
            ->select('id_proveedor', 'id_cotizacion')
            ->groupBy('id_proveedor', 'id_cotizacion')
            ->get();

        foreach ($grupos as $grupo) {
            $registros = DB::table('contenedor_proveedor_estados_tracking_estados')
                ->where('id_proveedor', $grupo->id_proveedor)
                ->where('id_cotizacion', $grupo->id_cotizacion)
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $count = $registros->count();
            if ($count <= 1) {
                continue;
            }

            for ($i = 0; $i < $count - 1; $i++) {
                $actual = $registros[$i];
                $siguiente = $registros[$i + 1];

                DB::table('contenedor_proveedor_estados_tracking_estados')
                    ->where('id', $actual->id)
                    ->update([
                        'updated_at' => $actual->updated_at ?: ($siguiente->created_at ?: $now),
                    ]);
            }

            // El ultimo row queda abierto (updated_at = null).
            DB::table('contenedor_proveedor_estados_tracking_estados')
                ->where('id', $registros[$count - 1]->id)
                ->update(['updated_at' => null]);
        }

        // Triggers para tracking automatico de cambios en la columna `estados`.
        DB::unprepared('DROP TRIGGER IF EXISTS after_insert_track_cccp_estados');
        DB::unprepared('DROP TRIGGER IF EXISTS after_update_track_cccp_estados');

        DB::unprepared("
            CREATE TRIGGER after_insert_track_cccp_estados
            AFTER INSERT ON contenedor_consolidado_cotizacion_proveedores
            FOR EACH ROW
            BEGIN
                INSERT INTO contenedor_proveedor_estados_tracking_estados
                    (id_proveedor, id_cotizacion, estado, created_at, updated_at)
                VALUES
                    (NEW.id, NEW.id_cotizacion, NEW.estados, NOW(), NULL);
            END
        ");

        DB::unprepared("
            CREATE TRIGGER after_update_track_cccp_estados
            AFTER UPDATE ON contenedor_consolidado_cotizacion_proveedores
            FOR EACH ROW
            BEGIN
                IF (NOT (OLD.estados <=> NEW.estados)) OR (OLD.id_cotizacion <> NEW.id_cotizacion) THEN
                    UPDATE contenedor_proveedor_estados_tracking_estados
                    SET updated_at = NOW()
                    WHERE id_proveedor = OLD.id
                      AND id_cotizacion = OLD.id_cotizacion
                      AND updated_at IS NULL;

                    INSERT INTO contenedor_proveedor_estados_tracking_estados
                        (id_proveedor, id_cotizacion, estado, created_at, updated_at)
                    VALUES
                        (NEW.id, NEW.id_cotizacion, NEW.estados, NOW(), NULL);
                END IF;
            END
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared('DROP TRIGGER IF EXISTS after_insert_track_cccp_estados');
        DB::unprepared('DROP TRIGGER IF EXISTS after_update_track_cccp_estados');

        Schema::dropIfExists('contenedor_proveedor_estados_tracking_estados');
    }
}
