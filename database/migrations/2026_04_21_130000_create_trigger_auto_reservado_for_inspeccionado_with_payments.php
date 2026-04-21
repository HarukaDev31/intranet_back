<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateTriggerAutoReservadoForInspeccionadoWithPayments extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::unprepared('DROP TRIGGER IF EXISTS before_update_auto_reservado_when_paid');

        DB::unprepared("
            CREATE TRIGGER before_update_auto_reservado_when_paid
            BEFORE UPDATE ON contenedor_consolidado_cotizacion_proveedores
            FOR EACH ROW
            BEGIN
                IF NEW.id_cotizacion IS NOT NULL
                   AND NOT (OLD.estados <=> NEW.estados)
                   AND NEW.estados = 'INSPECCIONADO'
                   AND EXISTS (
                       SELECT 1
                       FROM contenedor_consolidado_cotizacion cc
                       WHERE cc.id = NEW.id_cotizacion
                         AND cc.estado_cliente IS NULL
                   )
                   AND EXISTS (
                       SELECT 1
                       FROM contenedor_consolidado_cotizacion_coordinacion_pagos p
                       INNER JOIN cotizacion_coordinacion_pagos_concept c ON c.id = p.id_concept
                       WHERE p.id_cotizacion = NEW.id_cotizacion
                         AND c.name IN ('LOGISTICA', 'IMPUESTOS')
                   ) THEN
                    SET NEW.estados = 'RESERVADO';
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
        DB::unprepared('DROP TRIGGER IF EXISTS before_update_auto_reservado_when_paid');
    }
}
