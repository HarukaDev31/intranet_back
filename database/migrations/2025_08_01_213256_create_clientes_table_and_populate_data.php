<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class CreateClientesTableAndPopulateData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Log::info('🚀 INICIANDO MIGRACIÓN: CreateClientesTableAndPopulateData');
        
        // Limpiar datos existentes para permitir re-ejecución
        $this->cleanupExistingData();

        // 1. Crear tabla clientes
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('documento')->nullable();
            $table->string('correo')->nullable();
            $table->string('telefono')->nullable();
            $table->date('fecha')->nullable();
            $table->timestamps();

            // Índices para optimizar búsquedas
            $table->index('telefono');
            $table->index('documento');
            $table->index('correo');
        });
        Log::info("✅ Tabla clientes creada exitosamente");

        // 2. Insertar datos de contenedor_consolidado_cotizacion
        $this->insertFromContenedorCotizacion();

        // 3. Insertar datos de entidad
        $this->insertFromEntidad();

        // 4. Actualizar referencias en pedido_curso
        $this->updatePedidoCurso();

        // 5. Actualizar referencias en contenedor_consolidado_cotizacion
        $this->updateContenedorCotizacion();

        // 6. Crear triggers
        $this->createTriggers();
        
        Log::info('🎉 MIGRACIÓN COMPLETADA: CreateClientesTableAndPopulateData');
    }

    /**
     * Limpiar datos existentes para permitir re-ejecución
     */
    private function cleanupExistingData()
    {
        // Eliminar triggers si existe
       
        DB::unprepared('DROP TRIGGER IF EXISTS after_pedido_curso_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS after_contenedor_cotizacion_insert');

        // Eliminar columnas id_cliente si existen
        if (Schema::hasColumn('pedido_curso', 'id_cliente')) {
            Schema::table('pedido_curso', function (Blueprint $table) {
                $table->dropForeign(['id_cliente']);
                $table->dropColumn('id_cliente');
            });
        }

        if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'id_cliente')) {
            Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
                $table->dropForeign(['id_cliente']);
                $table->dropColumn('id_cliente');
            });
        }

        // Eliminar tabla clientes si existe
        Schema::dropIfExists('clientes');
    }

    /**
     * Validar que el cliente tenga al menos un campo de contacto válido
     */
    private function validateClienteData($data)
    {
        $telefono = trim($data->telefono ?? '');
        $documento = trim($data->documento ?? '');
        $correo = trim($data->correo ?? '');
        $nombre = trim($data->nombre ?? '');

        // Validar que tenga nombre válido (no vacío y no solo espacios)
        if (empty($nombre) || strlen($nombre) < 2) {
            return false;
        }

        // Validar que tenga al menos uno de los tres campos de contacto válidos
        $hasValidPhone = !empty($telefono) && strlen($telefono) >= 7;
        $hasValidDocument = !empty($documento) && strlen($documento) >= 5;
        $hasValidEmail = !empty($correo) && filter_var($correo, FILTER_VALIDATE_EMAIL);

        if (!$hasValidPhone && !$hasValidDocument && !$hasValidEmail) {
            return false;
        }

        return true;
    }

    /**
     * Insertar datos desde contenedor_consolidado_cotizacion
     */
    private function insertFromContenedorCotizacion()
    {
        Log::info('=== INICIANDO INSERCIÓN DESDE contenedor_consolidado_cotizacion ===');
        
        $cotizaciones = DB::table('contenedor_consolidado_cotizacion')
            ->whereNotNull('estado_cliente')
            ->where('estado_cotizador', 'CONFIRMADO')
            ->whereNotNull('nombre')
            ->where('nombre', '!=', '')
            ->whereRaw('LENGTH(TRIM(nombre)) >= 2')
            ->where(function ($query) {
                $query->whereNotNull('telefono')
                    ->where('telefono', '!=', '')
                    ->whereRaw('LENGTH(TRIM(telefono)) >= 7')
                    ->orWhereNotNull('documento')
                    ->where('documento', '!=', '')
                    ->whereRaw('LENGTH(TRIM(documento)) >= 5')
                    ->orWhereNotNull('correo')
                    ->where('correo', '!=', '')
                    ->whereRaw('correo REGEXP "^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$"');
            })
            ->select('telefono', 'fecha', 'nombre', 'documento', 'correo')
            ->get();

        Log::info("Total de cotizaciones encontradas: " . $cotizaciones->count());
        $insertados = 0;
        $validados = 0;

        foreach ($cotizaciones as $cotizacion) {
            // Convertir el objeto stdClass a array para evitar problemas de acceso
            $clienteData = [
                'fecha' => $cotizacion->fecha,
                'nombre' => $cotizacion->nombre,
                'documento' => $cotizacion->documento,
                'correo' => $cotizacion->correo,
                'telefono' => $cotizacion->telefono
            ];

            $clienteObj = (object)$clienteData;

            Log::info("Procesando cotización - Fuente: contenedor_consolidado_cotizacion", [
                'nombre' => $clienteObj->nombre,
                'telefono' => $clienteObj->telefono,
                'documento' => $clienteObj->documento,
                'correo' => $clienteObj->correo
            ]);

            // Validar datos antes de insertar
            if ($this->validateClienteData($clienteObj)) {
                $validados++;
                $clienteId = $this->insertOrGetCliente($clienteObj, 'contenedor_consolidado_cotizacion');
                if ($clienteId) {
                    $insertados++;
                    Log::info("✅ Cliente insertado/obtenido desde contenedor_consolidado_cotizacion", [
                        'cliente_id' => $clienteId,
                        'nombre' => $clienteObj->nombre
                    ]);
                } else {
                    Log::warning("❌ Error al insertar cliente desde contenedor_consolidado_cotizacion", [
                        'nombre' => $clienteObj->nombre
                    ]);
                }
            } else {
                Log::warning("❌ Cliente no válido desde contenedor_consolidado_cotizacion", [
                    'nombre' => $clienteObj->nombre,
                    'telefono' => $clienteObj->telefono,
                    'documento' => $clienteObj->documento,
                    'correo' => $clienteObj->correo
                ]);
            }
        }

        Log::info("=== RESUMEN contenedor_consolidado_cotizacion ===", [
            'total_procesados' => $cotizaciones->count(),
            'validados' => $validados,
            'insertados' => $insertados
        ]);
    }

    /**
     * Insertar datos desde entidad
     */
    private function insertFromEntidad()
    {
        Log::info('=== INICIANDO INSERCIÓN DESDE entidad ===');
        
        $entidades = DB::table('entidad as e')
            ->join('pedido_curso as pc', 'e.ID_Entidad', '=', 'pc.ID_Entidad')
            ->where('pc.Nu_Estado', 2)
            ->whereNotNull('e.No_Entidad')
            ->where('e.No_Entidad', '!=', '')
            ->where(function ($query) {
                $query->whereNotNull('e.Nu_Celular_Entidad')
                    ->where('e.Nu_Celular_Entidad', '!=', '')
                    ->whereRaw('LENGTH(TRIM(e.Nu_Celular_Entidad)) >= 7')
                    ->orWhereNotNull('e.Nu_Documento_Identidad')
                    ->where('e.Nu_Documento_Identidad', '!=', '')
                    ->whereRaw('LENGTH(TRIM(e.Nu_Documento_Identidad)) >= 5')
                    ->orWhereNotNull('e.Txt_Email_Entidad')
                    ->where('e.Txt_Email_Entidad', '!=', '');
            })
            ->select(
                'e.Fe_Registro as fecha',
                'e.No_Entidad as nombre',
                'e.Nu_Documento_Identidad as documento',
                'e.Txt_Email_Entidad as correo',
                'e.Nu_Celular_Entidad as telefono'
            )
            ->distinct() // Evitar duplicados si hay múltiples pedidos para la misma entidad
            ->get();

        Log::info("Total de entidades encontradas: " . $entidades->count());
        $insertados = 0;
        $validados = 0;

        foreach ($entidades as $entidad) {
            // Convertir el objeto stdClass a array para evitar problemas de acceso
            $clienteData = [
                'fecha' => $entidad->fecha,
                'nombre' => $entidad->nombre,
                'documento' => $entidad->documento,
                'correo' => $entidad->correo,
                'telefono' => $entidad->telefono
            ];

            $clienteObj = (object)$clienteData;
            
            Log::info("Procesando entidad - Fuente: entidad", [
                'nombre' => $clienteObj->nombre,
                'telefono' => $clienteObj->telefono,
                'documento' => $clienteObj->documento,
                'correo' => $clienteObj->correo
            ]);
            
            // Validar datos antes de insertar
            if ($this->validateClienteData($clienteObj)) {
                $validados++;
                $clienteId = $this->insertOrGetCliente($clienteObj, 'entidad');
                if ($clienteId) {
                    $insertados++;
                    Log::info("✅ Cliente insertado/obtenido desde entidad", [
                        'cliente_id' => $clienteId,
                        'nombre' => $clienteObj->nombre
                    ]);
                } else {
                    Log::warning("❌ Error al insertar cliente desde entidad", [
                        'nombre' => $clienteObj->nombre
                    ]);
                }
            } else {
                Log::warning("❌ Cliente no válido desde entidad", [
                    'nombre' => $clienteObj->nombre,
                    'telefono' => $clienteObj->telefono,
                    'documento' => $clienteObj->documento,
                    'correo' => $clienteObj->correo
                ]);
            }
        }

        Log::info("=== RESUMEN entidad ===", [
            'total_procesados' => $entidades->count(),
            'validados' => $validados,
            'insertados' => $insertados
        ]);
    }

    /**
     * Normalizar número de teléfono eliminando espacios, caracteres especiales y +
     */
    private function normalizePhone($phone)
    {
        if (empty($phone)) {
            return null;
        }

        // Eliminar espacios, guiones, paréntesis, puntos y símbolo +
        $normalized = preg_replace('/[\s\-\(\)\.\+]/', '', $phone);

        // Solo mantener números
        $normalized = preg_replace('/[^0-9]/', '', $normalized);

        return $normalized ?: null;
    }

    /**
     * Insertar cliente si no existe, o retornar ID si ya existe
     */
    private function insertOrGetCliente($data, $fuente = 'desconocida')
    {
        Log::info("🔍 Buscando cliente existente - Fuente: {$fuente}", [
            'nombre' => $data->nombre,
            'telefono' => $data->telefono,
            'documento' => $data->documento,
            'correo' => $data->correo
        ]);
        
        // Normalizar teléfono
        $telefonoNormalizado = $this->normalizePhone($data->telefono ?? null);

        // Buscar por teléfono normalizado primero
        $cliente = null;
        if (!empty($telefonoNormalizado)) {
            $cliente = DB::table('clientes')
                ->where('telefono', $telefonoNormalizado)
                ->first();
            
            if ($cliente) {
                Log::info("✅ Cliente encontrado por teléfono - Fuente: {$fuente}", [
                    'cliente_id' => $cliente->id,
                    'telefono' => $telefonoNormalizado
                ]);
                return $cliente->id;
            }
        }

        // Si no se encuentra por teléfono, buscar por documento
        if (!$cliente && !empty($data->documento)) {
            $cliente = DB::table('clientes')
                ->where('documento', $data->documento)
                ->first();
                
            if ($cliente) {
                Log::info("✅ Cliente encontrado por documento - Fuente: {$fuente}", [
                    'cliente_id' => $cliente->id,
                    'documento' => $data->documento
                ]);
                return $cliente->id;
            }
        }

        // Si no se encuentra por documento, buscar por correo
        if (!$cliente && !empty($data->correo)) {
            $cliente = DB::table('clientes')
                ->where('correo', $data->correo)
                ->first();
                
            if ($cliente) {
                Log::info("✅ Cliente encontrado por correo - Fuente: {$fuente}", [
                    'cliente_id' => $cliente->id,
                    'correo' => $data->correo
                ]);
                return $cliente->id;
            }
        }

        // Validación final antes de insertar
        $nombre = trim($data->nombre ?? '');
        $documento = !empty($data->documento) ? trim($data->documento) : null;
        $correo = !empty($data->correo) ? trim($data->correo) : null;

        // Verificar que el nombre sea válido
        if (empty($nombre) || strlen($nombre) < 2) {
            Log::warning("❌ Nombre inválido - Fuente: {$fuente}", [
                'nombre' => $nombre
            ]);
            return null;
        }

        // Verificar que tenga al menos un método de contacto válido
        $hasValidContact = false;
        if (!empty($telefonoNormalizado) && strlen($telefonoNormalizado) >= 7) {
            $hasValidContact = true;
        }
        if (!$hasValidContact && !empty($documento) && strlen($documento) >= 5) {
            $hasValidContact = true;
        }
        if (!$hasValidContact && !empty($correo) && filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $hasValidContact = true;
        }

        if (!$hasValidContact) {
            Log::warning("❌ Sin método de contacto válido - Fuente: {$fuente}", [
                'telefono' => $telefonoNormalizado,
                'documento' => $documento,
                'correo' => $correo
            ]);
            return null;
        }

        // Insertar nuevo cliente con teléfono normalizado
        $clienteId = DB::table('clientes')->insertGetId([
            'nombre' => $nombre,
            'documento' => $documento,
            'correo' => $correo,
            'telefono' => $telefonoNormalizado,
            'fecha' => $data->fecha ?? now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info("🆕 Nuevo cliente insertado - Fuente: {$fuente}", [
            'cliente_id' => $clienteId,
            'nombre' => $nombre,
            'telefono' => $telefonoNormalizado,
            'documento' => $documento,
            'correo' => $correo
        ]);

        return $clienteId;
    }

    /**
     * Actualizar referencias en pedido_curso
     */
    private function updatePedidoCurso()
    {
        Log::info('=== INICIANDO ACTUALIZACIÓN DE REFERENCIAS EN pedido_curso ===');
        
        // Agregar columna id_cliente si no existe
        if (!Schema::hasColumn('pedido_curso', 'id_cliente')) {
            Schema::table('pedido_curso', function (Blueprint $table) {
                $table->unsignedBigInteger('id_cliente')->nullable()->after('ID_Entidad');
                $table->foreign('id_cliente')->references('id')->on('clientes');
            });
            Log::info("✅ Columna id_cliente agregada a pedido_curso");
        }

        // Actualizar registros con Nu_Estado = 2
        $pedidos = DB::table('pedido_curso')
            ->where('Nu_Estado', 2)
            ->get();

        Log::info("Total de pedidos a actualizar: " . $pedidos->count());
        $actualizados = 0;

        foreach ($pedidos as $pedido) {
            // Buscar cliente por ID_Entidad
            $entidad = DB::table('entidad')
                ->where('ID_Entidad', $pedido->ID_Entidad)
                ->first();

            if ($entidad) {
                // Convertir datos de entidad al formato esperado por insertOrGetCliente
                $clienteData = [
                    'fecha' => $entidad->Fe_Registro,
                    'nombre' => $entidad->No_Entidad,
                    'documento' => $entidad->Nu_Documento_Identidad,
                    'correo' => $entidad->Txt_Email_Entidad,
                    'telefono' => $entidad->Nu_Celular_Entidad
                ];
                
                $clienteObj = (object)$clienteData;
                $clienteId = $this->insertOrGetCliente($clienteObj, 'pedido_curso_update');
                
                if ($clienteId) {
                    // Usar la clave primaria correcta (probablemente ID_Pedido_Curso o similar)
                    $primaryKey = $this->getPrimaryKeyColumn('pedido_curso');

                    DB::table('pedido_curso')
                        ->where($primaryKey, $pedido->$primaryKey)
                        ->update(['id_cliente' => $clienteId]);
                    
                    $actualizados++;
                    Log::info("✅ Pedido actualizado con cliente", [
                        'pedido_id' => $pedido->$primaryKey,
                        'cliente_id' => $clienteId,
                        'entidad_id' => $pedido->ID_Entidad
                    ]);
                } else {
                    Log::warning("❌ No se pudo obtener cliente para pedido", [
                        'pedido_id' => $pedido->ID_Pedido_Curso ?? $pedido->id ?? 'N/A',
                        'entidad_id' => $pedido->ID_Entidad
                    ]);
                }
            } else {
                Log::warning("❌ Entidad no encontrada para pedido", [
                    'pedido_id' => $pedido->ID_Pedido_Curso ?? $pedido->id ?? 'N/A',
                    'entidad_id' => $pedido->ID_Entidad
                ]);
            }
        }

        Log::info("=== RESUMEN actualización pedido_curso ===", [
            'total_pedidos' => $pedidos->count(),
            'actualizados' => $actualizados
        ]);
    }

    /**
     * Obtener el nombre de la columna de clave primaria de una tabla
     */
    private function getPrimaryKeyColumn($tableName)
    {
        // Intentar con nombres comunes de claves primarias
        $commonPrimaryKeys = ['id', 'ID_Pedido_Curso', 'ID_PedidoCurso', 'pedido_curso_id'];

        foreach ($commonPrimaryKeys as $key) {
            if (Schema::hasColumn($tableName, $key)) {
                return $key;
            }
        }

        // Si no encuentra ninguna, usar 'id' como fallback
        return 'id';
    }

    /**
     * Actualizar referencias en contenedor_consolidado_cotizacion
     */
    private function updateContenedorCotizacion()
    {
        Log::info('=== INICIANDO ACTUALIZACIÓN DE REFERENCIAS EN contenedor_consolidado_cotizacion ===');
        
        // Agregar columna id_cliente si no existe
        if (!Schema::hasColumn('contenedor_consolidado_cotizacion', 'id_cliente')) {
            Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
                $table->unsignedBigInteger('id_cliente')->nullable()->after('id');
                $table->foreign('id_cliente')->references('id')->on('clientes');
            });
            Log::info("✅ Columna id_cliente agregada a contenedor_consolidado_cotizacion");
        }

        // Actualizar registros confirmados
        $cotizaciones = DB::table('contenedor_consolidado_cotizacion')
            ->whereNotNull('estado_cliente')
            ->where('estado_cotizador', 'CONFIRMADO')
            ->get();

        Log::info("Total de cotizaciones a actualizar: " . $cotizaciones->count());
        $actualizadas = 0;

        foreach ($cotizaciones as $cotizacion) {
            $clienteId = $this->insertOrGetCliente($cotizacion, 'contenedor_cotizacion_update');
            
            if ($clienteId) {
                // Usar la clave primaria correcta
                $primaryKey = $this->getPrimaryKeyColumn('contenedor_consolidado_cotizacion');

                DB::table('contenedor_consolidado_cotizacion')
                    ->where($primaryKey, $cotizacion->$primaryKey)
                    ->update(['id_cliente' => $clienteId]);
                
                $actualizadas++;
                Log::info("✅ Cotización actualizada con cliente", [
                    'cotizacion_id' => $cotizacion->$primaryKey,
                    'cliente_id' => $clienteId,
                    'nombre' => $cotizacion->nombre ?? 'N/A'
                ]);
            } else {
                Log::warning("❌ No se pudo obtener cliente para cotización", [
                    'cotizacion_id' => $cotizacion->id ?? 'N/A',
                    'nombre' => $cotizacion->nombre ?? 'N/A'
                ]);
            }
        }

        Log::info("=== RESUMEN actualización contenedor_consolidado_cotizacion ===", [
            'total_cotizaciones' => $cotizaciones->count(),
            'actualizadas' => $actualizadas
        ]);
    }

    /**
     * Crear triggers para mantener sincronización automática
     */
    private function createTriggers()
    {
        // Trigger para pedido_curso (asumiendo que la clave primaria es ID_Pedido_Curso)
        DB::unprepared("
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
                IF (nombre_entidad IS NOT NULL AND nombre_entidad != '') AND
                   (telefono_normalizado IS NOT NULL AND telefono_normalizado != '' OR
                    documento_entidad IS NOT NULL AND documento_entidad != '' OR
                    correo_entidad IS NOT NULL AND correo_entidad != '') THEN
                    
                    -- Normalizar teléfono
                    SET telefono_normalizado = REGEXP_REPLACE(
                        REGEXP_REPLACE(telefono_normalizado, '[\\s\\-\\(\\)\\.\\+]', ''),
                        '[^0-9]', ''
                    );
                    
                    -- Buscar cliente existente por teléfono normalizado
                    SELECT id INTO cliente_id FROM clientes 
                    WHERE telefono = telefono_normalizado
                    LIMIT 1;
                    
                    -- Si no se encuentra por teléfono, buscar por documento
                    IF cliente_id IS NULL AND documento_entidad IS NOT NULL AND documento_entidad != '' THEN
                        SELECT id INTO cliente_id FROM clientes 
                        WHERE documento = documento_entidad
                        LIMIT 1;
                    END IF;
                    
                    -- Si no se encuentra por documento, buscar por correo
                    IF cliente_id IS NULL AND correo_entidad IS NOT NULL AND correo_entidad != '' THEN
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
                    
                    -- Actualizar referencia (asumiendo que la clave primaria es ID_Pedido_Curso)
                    UPDATE pedido_curso SET id_cliente = cliente_id WHERE ID_Pedido_Curso = NEW.ID_Pedido_Curso;
                END IF;
            END
        ");

        // Trigger para contenedor_consolidado_cotizacion (asumiendo que la clave primaria es id)
        DB::unprepared("
            CREATE TRIGGER after_contenedor_cotizacion_insert 
            AFTER INSERT ON contenedor_consolidado_cotizacion
            FOR EACH ROW
            BEGIN
                DECLARE cliente_id INT;
                DECLARE telefono_normalizado VARCHAR(20);
                
                -- Validar que tenga al menos un campo de contacto válido
                IF (NEW.nombre IS NOT NULL AND NEW.nombre != '') AND
                   (NEW.telefono IS NOT NULL AND NEW.telefono != '' OR
                    NEW.documento IS NOT NULL AND NEW.documento != '' OR
                    NEW.correo IS NOT NULL AND NEW.correo != '') THEN
                    
                    -- Normalizar teléfono
                    SET telefono_normalizado = REGEXP_REPLACE(
                        REGEXP_REPLACE(NEW.telefono, '[\\s\\-\\(\\)\\.\\+]', ''),
                        '[^0-9]', ''
                    );
                    
                    -- Buscar cliente existente por teléfono normalizado
                    SELECT id INTO cliente_id FROM clientes 
                    WHERE telefono = telefono_normalizado
                    LIMIT 1;
                    
                    -- Si no se encuentra por teléfono, buscar por documento
                    IF cliente_id IS NULL AND NEW.documento IS NOT NULL AND NEW.documento != '' THEN
                        SELECT id INTO cliente_id FROM clientes 
                        WHERE documento = NEW.documento
                        LIMIT 1;
                    END IF;
                    
                    -- Si no se encuentra por documento, buscar por correo
                    IF cliente_id IS NULL AND NEW.correo IS NOT NULL AND NEW.correo != '' THEN
                        SELECT id INTO cliente_id FROM clientes 
                        WHERE correo = NEW.correo
                        LIMIT 1;
                    END IF;
                    
                    -- Si no existe, crear nuevo cliente
                    IF cliente_id IS NULL THEN
                        INSERT INTO clientes (nombre, documento, correo, telefono, fecha, created_at, updated_at)
                        VALUES (NEW.nombre, NEW.documento, NEW.correo, telefono_normalizado, NEW.fecha, NOW(), NOW());
                        
                        SET cliente_id = LAST_INSERT_ID();
                    END IF;
                    
                    -- Actualizar referencia (asumiendo que la clave primaria es id)
                    UPDATE contenedor_consolidado_cotizacion SET id_cliente = cliente_id WHERE id = NEW.id;
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
        // Eliminar triggers
        DB::unprepared('DROP TRIGGER IF EXISTS after_pedido_curso_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS after_contenedor_cotizacion_insert');

        // Eliminar columnas agregadas
        if (Schema::hasColumn('pedido_curso', 'id_cliente')) {
            Schema::table('pedido_curso', function (Blueprint $table) {
                $table->dropForeign(['id_cliente']);
                $table->dropColumn('id_cliente');
            });
        }

        if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'id_cliente')) {
            Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
                $table->dropForeign(['id_cliente']);
                $table->dropColumn('id_cliente');
            });
        }

        // Eliminar tabla clientes
        Schema::dropIfExists('clientes');
    }
}
