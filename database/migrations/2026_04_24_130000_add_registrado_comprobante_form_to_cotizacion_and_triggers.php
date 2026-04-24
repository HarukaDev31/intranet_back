<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddRegistradoComprobanteFormToCotizacionAndTriggers extends Migration
{
    public function up()
    {
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            $table->boolean('registrado_comprobante_form')->default(false)->after('delivery_form_registered_at');
        });

        // Backfill: toda cotización con formulario asociado queda en SI.
        DB::statement("
            UPDATE contenedor_consolidado_cotizacion c
            SET c.registrado_comprobante_form = 1
            WHERE EXISTS (
                SELECT 1
                FROM consolidado_comprobante_forms f
                WHERE f.id_cotizacion = c.id
            )
        ");

        DB::unprepared('DROP TRIGGER IF EXISTS after_insert_set_registrado_comprobante_form');
        DB::unprepared('DROP TRIGGER IF EXISTS after_delete_set_registrado_comprobante_form');
        DB::unprepared('DROP TRIGGER IF EXISTS after_update_set_registrado_comprobante_form');

        DB::unprepared("
            CREATE TRIGGER after_insert_set_registrado_comprobante_form
            AFTER INSERT ON consolidado_comprobante_forms
            FOR EACH ROW
            BEGIN
                UPDATE contenedor_consolidado_cotizacion
                SET registrado_comprobante_form = 1
                WHERE id = NEW.id_cotizacion;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER after_delete_set_registrado_comprobante_form
            AFTER DELETE ON consolidado_comprobante_forms
            FOR EACH ROW
            BEGIN
                UPDATE contenedor_consolidado_cotizacion
                SET registrado_comprobante_form = (
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM consolidado_comprobante_forms
                            WHERE id_cotizacion = OLD.id_cotizacion
                        ) THEN 1
                        ELSE 0
                    END
                )
                WHERE id = OLD.id_cotizacion;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER after_update_set_registrado_comprobante_form
            AFTER UPDATE ON consolidado_comprobante_forms
            FOR EACH ROW
            BEGIN
                IF NOT (OLD.id_cotizacion <=> NEW.id_cotizacion) THEN
                    UPDATE contenedor_consolidado_cotizacion
                    SET registrado_comprobante_form = (
                        CASE
                            WHEN EXISTS (
                                SELECT 1
                                FROM consolidado_comprobante_forms
                                WHERE id_cotizacion = OLD.id_cotizacion
                            ) THEN 1
                            ELSE 0
                        END
                    )
                    WHERE id = OLD.id_cotizacion;

                    UPDATE contenedor_consolidado_cotizacion
                    SET registrado_comprobante_form = 1
                    WHERE id = NEW.id_cotizacion;
                END IF;
            END
        ");
    }

    public function down()
    {
        DB::unprepared('DROP TRIGGER IF EXISTS after_insert_set_registrado_comprobante_form');
        DB::unprepared('DROP TRIGGER IF EXISTS after_delete_set_registrado_comprobante_form');
        DB::unprepared('DROP TRIGGER IF EXISTS after_update_set_registrado_comprobante_form');

        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            $table->dropColumn('registrado_comprobante_form');
        });
    }
}
