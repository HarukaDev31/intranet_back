<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreatePedidoCursoQueueSystem extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Eliminar todo lo anterior
        DB::unprepared('DROP TRIGGER IF EXISTS after_pedido_curso_insert_safe');
        DB::unprepared('DROP PROCEDURE IF EXISTS process_pedido_curso_cliente');
        DB::unprepared('DROP TRIGGER IF EXISTS after_pedido_curso_insert');
        DB::unprepared('DROP EVENT IF EXISTS process_pedido_curso_updates');
        DB::unprepared('DROP TABLE IF EXISTS temp_pedido_curso_updates');
        
        // Crear tabla de cola para procesamiento
        Schema::create('pedido_curso_queue', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pedido_id');
            $table->unsignedBigInteger('entidad_id');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'created_at']);
            $table->index('pedido_id');
        });
        
        // Crear trigger que solo inserta en la cola
        DB::unprepared('
            CREATE TRIGGER pedido_curso_queue_trigger
            AFTER INSERT ON pedido_curso
            FOR EACH ROW
            BEGIN
                INSERT INTO pedido_curso_queue (pedido_id, entidad_id, created_at, updated_at)
                VALUES (NEW.ID_Pedido_Curso, NEW.ID_Entidad, NOW(), NOW());
            END
        ');
        
        // Crear procedimiento para procesar la cola
        DB::unprepared('
            DROP PROCEDURE IF EXISTS process_pedido_curso_queue;
            
            CREATE PROCEDURE process_pedido_curso_queue()
            BEGIN
                DECLARE done INT DEFAULT FALSE;
                DECLARE queue_id INT;
                DECLARE pedido_id INT;
                DECLARE entidad_id INT;
                DECLARE cliente_id INT;
                DECLARE telefono_normalizado VARCHAR(20);
                DECLARE nombre_entidad VARCHAR(255);
                DECLARE documento_entidad VARCHAR(255);
                DECLARE correo_entidad VARCHAR(255);
                DECLARE fecha_entidad DATE;
                
                DECLARE cur CURSOR FOR 
                    SELECT id, pedido_id, entidad_id 
                    FROM pedido_curso_queue 
                    WHERE status = \'pending\'
                    ORDER BY created_at ASC
                    LIMIT 10;
                DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
                
                OPEN cur;
                
                read_loop: LOOP
                    FETCH cur INTO queue_id, pedido_id, entidad_id;
                    IF done THEN
                        LEAVE read_loop;
                    END IF;
                    
                    -- Marcar como procesando
                    UPDATE pedido_curso_queue SET status = \'processing\' WHERE id = queue_id;
                    
                    BEGIN
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
                            
                            -- Marcar como completado
                            UPDATE pedido_curso_queue SET status = \'completed\' WHERE id = queue_id;
                        ELSE
                            -- Marcar como completado (sin cliente válido)
                            UPDATE pedido_curso_queue SET status = \'completed\' WHERE id = queue_id;
                        END IF;
                    END;
                END LOOP;
                
                CLOSE cur;
            END
        ');
        
        // Crear evento para procesar la cola automáticamente
        DB::unprepared('
            CREATE EVENT process_pedido_curso_queue_event
            ON SCHEDULE EVERY 2 SECOND
            DO
            BEGIN
                CALL process_pedido_curso_queue();
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
        // Eliminar evento
        DB::unprepared('DROP EVENT IF EXISTS process_pedido_curso_queue_event');
        
        // Eliminar procedimiento
        DB::unprepared('DROP PROCEDURE IF EXISTS process_pedido_curso_queue');
        
        // Eliminar trigger
        DB::unprepared('DROP TRIGGER IF EXISTS pedido_curso_queue_trigger');
        
        // Eliminar tabla de cola
        Schema::dropIfExists('pedido_curso_queue');
    }
}
