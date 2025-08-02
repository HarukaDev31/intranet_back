<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FixPedidoCursoTrigger extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Eliminar el trigger existente si existe
        DB::unprepared('DROP TRIGGER IF EXISTS after_pedido_curso_insert');

        // Crear el trigger corregido
        DB::unprepared('
            CREATE TRIGGER after_pedido_curso_insert
            BEFORE INSERT ON pedido_curso
            FOR EACH ROW
            BEGIN
                DECLARE cliente_id INT;
                DECLARE telefono_normalizado VARCHAR(20);
                DECLARE nombre_entidad VARCHAR(255);
                DECLARE documento_entidad VARCHAR(255);
                DECLARE correo_entidad VARCHAR(255);
                DECLARE fecha_entidad DATE;

                -- Obtener datos de la entidad
                SELECT No_Entidad, Nu_Documento_Identidad, Txt_Email_Entidad, Nu_Celular_Entidad, Fe_Registro
                INTO nombre_entidad, documento_entidad, correo_entidad, telefono_normalizado, fecha_entidad
                FROM entidad WHERE ID_Entidad = NEW.ID_Entidad;

                -- Validar que tenga al menos un campo de contacto válido
                IF (nombre_entidad IS NOT NULL AND nombre_entidad != \'\') AND
                   (telefono_normalizado IS NOT NULL AND telefono_normalizado != \'\' OR
                    documento_entidad IS NOT NULL AND documento_entidad != \'\' OR
                    correo_entidad IS NOT NULL AND correo_entidad != \'\') THEN

                    -- Normalizar teléfono
                    SET telefono_normalizado = REGEXP_REPLACE(telefono_normalizado, \'[^0-9]\', \'\');

                    -- Buscar cliente existente por teléfono normalizado
                    SELECT id INTO cliente_id FROM clientes
                    WHERE telefono = telefono_normalizado
                    LIMIT 1;

                    -- Si no se encuentra por teléfono, buscar por documento
                    IF cliente_id IS NULL AND documento_entidad IS NOT NULL AND documento_entidad != \'\' THEN
                        SELECT id INTO cliente_id FROM clientes
                        WHERE documento = documento_entidad
                        LIMIT 1;
                    END IF;

                    -- Si no se encuentra por documento, buscar por correo
                    IF cliente_id IS NULL AND correo_entidad IS NOT NULL AND correo_entidad != \'\' THEN
                        SELECT id INTO cliente_id FROM clientes
                        WHERE correo = correo_entidad
                        LIMIT 1;
                    END IF;

                    -- Si no existe, crear nuevo cliente
                    IF cliente_id IS NULL THEN
                        INSERT INTO clientes (nombre, documento, correo, telefono, fecha, created_at, updated_at)
                        VALUES (nombre_entidad, documento_entidad, correo_entidad, telefono_normalizado, fecha_entidad, NOW(), NOW());

                        SET cliente_id = LAST_INSERT_ID();
                    END IF;

                    -- Asignar el cliente_id al NEW record (no UPDATE, sino asignación directa)
                    SET NEW.id_cliente = cliente_id;
                END IF;
            END
        ');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Eliminar el trigger
        DB::unprepared('DROP TRIGGER IF EXISTS after_pedido_curso_insert');
    }
}
