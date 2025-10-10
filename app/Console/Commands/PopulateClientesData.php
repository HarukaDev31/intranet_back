<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PopulateClientesData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clientes:populate {--force : Forzar la ejecución sin confirmación}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poblar la tabla de clientes con datos de contenedor_consolidado_cotizacion y entidad';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🚀 INICIANDO COMANDO: PopulateClientesData');
        
        if (!$this->option('force') && !$this->confirm('¿Estás seguro de que quieres poblar la tabla de clientes?')) {
            $this->info('Operación cancelada.');
            return 0;
        }

        try {
            // 1. Insertar datos de contenedor_consolidado_cotizacion
            $this->insertFromContenedorCotizacion();

            // 2. Insertar datos de entidad
            $this->insertFromEntidad();

            // 3. Actualizar referencias en pedido_curso
            $this->updatePedidoCurso();

            $this->info('🎉 COMANDO COMPLETADO: PopulateClientesData');
            return 0;
        } catch (\Exception $e) {
            $this->error('Error durante la ejecución: ' . $e->getMessage());
            Log::error('Error en PopulateClientesData: ' . $e->getMessage());
            return 1;
        }
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
        $this->info('=== INICIANDO INSERCIÓN DESDE contenedor_consolidado_cotizacion ===');
        
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

        $this->info("Total de cotizaciones encontradas: " . $cotizaciones->count());
        $insertados = 0;
        $validados = 0;

        $progressBar = $this->output->createProgressBar($cotizaciones->count());
        $progressBar->start();

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

            // Validar datos antes de insertar
            if ($this->validateClienteData($clienteObj)) {
                $validados++;
                $clienteId = $this->insertOrGetCliente($clienteObj, 'contenedor_consolidado_cotizacion');
                if ($clienteId) {
                    $insertados++;
                }
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("=== RESUMEN contenedor_consolidado_cotizacion ===");
        $this->table(['Métrica', 'Valor'], [
            ['Total procesados', $cotizaciones->count()],
            ['Validados', $validados],
            ['Insertados', $insertados]
        ]);
    }

    /**
     * Insertar datos desde entidad
     */
    private function insertFromEntidad()
    {
        $this->info('=== INICIANDO INSERCIÓN DESDE entidad ===');
        
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
            ->distinct()
            ->get();

        $this->info("Total de entidades encontradas: " . $entidades->count());
        $insertados = 0;
        $validados = 0;

        $progressBar = $this->output->createProgressBar($entidades->count());
        $progressBar->start();

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
            
            // Validar datos antes de insertar
            if ($this->validateClienteData($clienteObj)) {
                $validados++;
                $clienteId = $this->insertOrGetCliente($clienteObj, 'entidad');
                if ($clienteId) {
                    $insertados++;
                }
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("=== RESUMEN entidad ===");
        $this->table(['Métrica', 'Valor'], [
            ['Total procesados', $entidades->count()],
            ['Validados', $validados],
            ['Insertados', $insertados]
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
        // Normalizar teléfono
        $telefonoNormalizado = $this->normalizePhone($data->telefono ?? null);

        // Buscar por teléfono normalizado primero
        $cliente = null;
        if (!empty($telefonoNormalizado)) {
            $cliente = DB::table('clientes')
                ->where('telefono', 'like', $telefonoNormalizado)
                ->first();
            
            if ($cliente) {
                return $cliente->id;
            }
        }

        // Si no se encuentra por teléfono, buscar por documento
        if (!$cliente && !empty($data->documento)) {
            $cliente = DB::table('clientes')
                ->where('documento', $data->documento)
                ->first();
                
            if ($cliente) {
                return $cliente->id;
            }
        }

        // Si no se encuentra por documento, buscar por correo
        if (!$cliente && !empty($data->correo)) {
            $cliente = DB::table('clientes')
                ->where('correo', $data->correo)
                ->first();
                
            if ($cliente) {
                return $cliente->id;
            }
        }

        // Validación final antes de insertar
        $nombre = trim($data->nombre ?? '');
        $documento = !empty($data->documento) ? trim($data->documento) : null;
        $correo = !empty($data->correo) ? trim($data->correo) : null;

        // Verificar que el nombre sea válido
        if (empty($nombre) || strlen($nombre) < 2) {
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
            return null;
        }

        try {
            // Insertar nuevo cliente
            $clienteId = DB::table('clientes')->insertGetId([
                'nombre' => $nombre,
                'documento' => $documento,
                'correo' => $correo,
                'telefono' => $telefonoNormalizado,
                'fecha' => $data->fecha ?? now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return $clienteId;
        } catch (\Exception $e) {
            Log::error("Error al insertar cliente desde {$fuente}: " . $e->getMessage(), [
                'nombre' => $nombre,
                'telefono' => $telefonoNormalizado,
                'documento' => $documento,
                'correo' => $correo
            ]);
            return null;
        }
    }

    /**
     * Actualizar referencias en pedido_curso
     */
    private function updatePedidoCurso()
    {
        $this->info('=== ACTUALIZANDO REFERENCIAS EN pedido_curso ===');
        
        $pedidos = DB::table('pedido_curso as pc')
            ->join('entidad as e', 'pc.ID_Entidad', '=', 'e.ID_Entidad')
            ->whereNull('pc.id_cliente')
            ->select('pc.ID_Pedido_Curso', 'e.No_Entidad', 'e.Nu_Celular_Entidad', 'e.Nu_Documento_Identidad', 'e.Txt_Email_Entidad')
            ->get();

        $this->info("Total de pedidos a actualizar: " . $pedidos->count());
        $actualizados = 0;

        $progressBar = $this->output->createProgressBar($pedidos->count());
        $progressBar->start();

        foreach ($pedidos as $pedido) {
            $telefonoNormalizado = $this->normalizePhone($pedido->Nu_Celular_Entidad);
            
            $cliente = null;
            
            // Buscar cliente por teléfono
            if (!empty($telefonoNormalizado)) {
                $cliente = DB::table('clientes')
                    ->where('telefono', 'like', $telefonoNormalizado)
                    ->first();
            }
            
            // Si no se encuentra por teléfono, buscar por documento
            if (!$cliente && !empty($pedido->Nu_Documento_Identidad)) {
                $cliente = DB::table('clientes')
                    ->where('documento', $pedido->Nu_Documento_Identidad)
                    ->first();
            }
            
            // Si no se encuentra por documento, buscar por correo
            if (!$cliente && !empty($pedido->Txt_Email_Entidad)) {
                $cliente = DB::table('clientes')
                    ->where('correo', $pedido->Txt_Email_Entidad)
                    ->first();
            }

            if ($cliente) {
                DB::table('pedido_curso')
                    ->where('ID_Pedido_Curso', $pedido->ID_Pedido_Curso)
                    ->update(['id_cliente' => $cliente->id]);
                $actualizados++;
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("=== RESUMEN pedido_curso ===");
        $this->table(['Métrica', 'Valor'], [
            ['Total procesados', $pedidos->count()],
            ['Actualizados', $actualizados]
        ]);
    }
}
