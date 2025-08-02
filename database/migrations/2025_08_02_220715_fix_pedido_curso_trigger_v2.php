<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FixPedidoCursoTriggerV2 extends Migration
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

        // Crear una tabla temporal para manejar las actualizaciones pendientes
        DB::unprepared('
            CREATE TABLE IF NOT EXISTS temp_pedido_curso_updates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pedido_id INT NOT NULL,
                cliente_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ');

        // Crear el trigger que solo inserta en la tabla temporal
        DB::unprepared('
            CREATE TRIGGER after_pedido_curso_insert
            AFTER INSERT ON pedido_curso
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

                    -- Insertar en la tabla temporal para procesamiento posterior
                    INSERT INTO temp_pedido_curso_updates (pedido_id, cliente_id)
                    VALUES (NEW.ID_Pedido_Curso, cliente_id);
                END IF;
            END
        ');

        // Crear un evento que procese las actualizaciones pendientes cada segundo
        DB::unprepared('
            CREATE EVENT IF NOT EXISTS process_pedido_curso_updates
            ON SCHEDULE EVERY 1 SECOND
            DO
            BEGIN
                DECLARE done INT DEFAULT FALSE;
                DECLARE pedido_id INT;
                DECLARE cliente_id INT;
                DECLARE update_id INT;
                
                DECLARE cur CURSOR FOR 
                    SELECT id, pedido_id, cliente_id 
                    FROM temp_pedido_curso_updates 
                    WHERE created_at > DATE_SUB(NOW(), INTERVAL 5 SECOND);
                DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
                
                OPEN cur;
                
                read_loop: LOOP
                    FETCH cur INTO update_id, pedido_id, cliente_id;
                    IF done THEN
                        LEAVE read_loop;
                    END IF;
                    
                    -- Actualizar el pedido_curso
                    UPDATE pedido_curso SET id_cliente = cliente_id WHERE ID_Pedido_Curso = pedido_id;
                    
                    -- Eliminar el registro procesado
                    DELETE FROM temp_pedido_curso_updates WHERE id = update_id;
                END LOOP;
                
                CLOSE cur;
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
        // Eliminar el evento
        DB::unprepared('DROP EVENT IF EXISTS process_pedido_curso_updates');
        
        // Eliminar el trigger
        DB::unprepared('DROP TRIGGER IF EXISTS after_pedido_curso_insert');
        
        // Eliminar la tabla temporal
        DB::unprepared('DROP TABLE IF EXISTS temp_pedido_curso_updates');
    }
}
