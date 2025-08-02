<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class RemoveAllPedidoCursoTriggers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Eliminar TODOS los triggers relacionados con pedido_curso
        DB::unprepared('DROP TRIGGER IF EXISTS after_pedido_curso_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS before_pedido_curso_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS pedido_curso_insert_trigger');
        
        // Eliminar eventos relacionados
        DB::unprepared('DROP EVENT IF EXISTS process_pedido_curso_updates');
        
        // Eliminar tablas temporales si existen
        DB::unprepared('DROP TABLE IF EXISTS temp_pedido_curso_updates');
        
        // Crear un procedimiento almacenado que maneje la lógica
        DB::unprepared('
            DROP PROCEDURE IF EXISTS process_pedido_curso_cliente;
            
            CREATE PROCEDURE process_pedido_curso_cliente(IN pedido_id INT)
            BEGIN
                DECLARE cliente_id INT;
                DECLARE telefono_normalizado VARCHAR(20);
                DECLARE nombre_entidad VARCHAR(255);
                DECLARE documento_entidad VARCHAR(255);
                DECLARE correo_entidad VARCHAR(255);
                DECLARE fecha_entidad DATE;
                DECLARE entidad_id INT;
                
                -- Obtener el ID_Entidad del pedido
                SELECT ID_Entidad INTO entidad_id FROM pedido_curso WHERE ID_Pedido_Curso = pedido_id;
                
                -- Obtener datos de la entidad
                SELECT No_Entidad, Nu_Documento_Identidad, Txt_Email_Entidad, Nu_Celular_Entidad, Fe_Registro
                INTO nombre_entidad, documento_entidad, correo_entidad, telefono_normalizado, fecha_entidad
                FROM entidad WHERE ID_Entidad = entidad_id;

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

                    -- Actualizar el pedido_curso con el cliente_id
                    UPDATE pedido_curso SET id_cliente = cliente_id WHERE ID_Pedido_Curso = pedido_id;
                END IF;
            END
        ');
        
        // Crear un trigger que llame al procedimiento después de la inserción
        DB::unprepared('
            CREATE TRIGGER after_pedido_curso_insert_safe
            AFTER INSERT ON pedido_curso
            FOR EACH ROW
            BEGIN
                -- Llamar al procedimiento almacenado para procesar el cliente
                CALL process_pedido_curso_cliente(NEW.ID_Pedido_Curso);
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
        DB::unprepared('DROP TRIGGER IF EXISTS after_pedido_curso_insert_safe');
        
        // Eliminar el procedimiento
        DB::unprepared('DROP PROCEDURE IF EXISTS process_pedido_curso_cliente');
    }
}
