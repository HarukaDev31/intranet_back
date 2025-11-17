<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ValidateCotizacionesWithLoadedProveedoresJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var int
     */
    protected $contenedorId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $contenedorId)
    {
        $this->contenedorId = $contenedorId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('🔍 Iniciando validación de usuarios en cotizaciones con proveedores cargados', [
                'contenedor_id' => $this->contenedorId
            ]);

            $cotizaciones = DB::table('contenedor_consolidado_cotizacion as ccc')
                ->join('contenedor_consolidado_cotizacion_proveedores as cccp', 'ccc.id', '=', 'cccp.id_cotizacion')
                ->where('ccc.id_contenedor', $this->contenedorId)
                ->where('cccp.estados_proveedor', 'LOADED')
                ->whereNotNull('ccc.nombre')
                ->where('ccc.nombre', '!=', '')
                ->whereRaw('LENGTH(TRIM(ccc.nombre)) >= 2')
                ->where(function ($query) {
                    $query->where(function ($q) {
                        $q->whereNotNull('ccc.telefono')
                            ->where('ccc.telefono', '!=', '')
                            ->whereRaw('LENGTH(TRIM(ccc.telefono)) >= 7');
                    })
                    ->orWhere(function ($q) {
                        $q->whereNotNull('ccc.documento')
                            ->where('ccc.documento', '!=', '')
                            ->whereRaw('LENGTH(TRIM(ccc.documento)) >= 5');
                    })
                    ->orWhere(function ($q) {
                        $q->whereNotNull('ccc.correo')
                            ->where('ccc.correo', '!=', '')
                            ->whereRaw('ccc.correo REGEXP "^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$"');
                    });
                })
                ->select('ccc.id', 'ccc.telefono', 'ccc.nombre', 'ccc.documento', 'ccc.correo')
                ->distinct()
                ->get();

            Log::info('Cotizaciones encontradas con proveedores cargados: ' . $cotizaciones->count());

            $validados = 0;
            $clientesCreados = 0;
            $clientesEncontrados = 0;

            foreach ($cotizaciones as $cotizacion) {
                $clienteObj = (object) [
                    'nombre' => $cotizacion->nombre,
                    'documento' => $cotizacion->documento,
                    'correo' => $cotizacion->correo,
                    'telefono' => $cotizacion->telefono,
                ];

                if ($this->validateClienteDataFromCommand($clienteObj)) {
                    $validados++;
                    $clienteId = $this->insertOrGetClienteFromCommand($clienteObj, 'cotizacion_proveedor_loaded');

                    if ($clienteId) {
                        $clienteExistia = DB::table('clientes')->where('id', $clienteId)->exists();

                        if ($clienteExistia) {
                            $clientesEncontrados++;
                        } else {
                            $clientesCreados++;
                        }

                        Log::info("✅ Cliente validado para cotización con proveedor cargado", [
                            'cotizacion_id' => $cotizacion->id,
                            'cliente_id' => $clienteId,
                            'nombre' => $clienteObj->nombre,
                            'fue_creado' => !$clienteExistia
                        ]);
                    }
                } else {
                    Log::warning("❌ Cliente no válido en cotización con proveedor cargado", [
                        'cotizacion_id' => $cotizacion->id,
                        'nombre' => $clienteObj->nombre,
                        'telefono' => $clienteObj->telefono,
                        'documento' => $clienteObj->documento,
                        'correo' => $clienteObj->correo
                    ]);
                }
            }

            Log::info('🎉 Validación completada', [
                'contenedor_id' => $this->contenedorId,
                'total_procesados' => $cotizaciones->count(),
                'validados' => $validados,
                'clientes_encontrados' => $clientesEncontrados,
                'clientes_creados' => $clientesCreados
            ]);
        } catch (\Exception $e) {
            Log::error('Error en validación de usuarios con proveedores cargados: ' . $e->getMessage(), [
                'contenedor_id' => $this->contenedorId,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function validateClienteDataFromCommand($data): bool
    {
        $telefono = trim($data->telefono ?? '');
        $documento = trim($data->documento ?? '');
        $correo = trim($data->correo ?? '');
        $nombre = trim($data->nombre ?? '');

        if (empty($nombre) || strlen($nombre) < 2) {
            return false;
        }

        $hasValidPhone = !empty($telefono) && strlen($telefono) >= 7;
        $hasValidDocument = !empty($documento) && strlen($documento) >= 5;
        $hasValidEmail = !empty($correo) && filter_var($correo, FILTER_VALIDATE_EMAIL);

        if (!$hasValidPhone && !$hasValidDocument && !$hasValidEmail) {
            return false;
        }

        return true;
    }

    private function normalizePhoneFromCommand($phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $normalized = preg_replace('/[\\s\\-\\(\\)\\.\\+]/', '', $phone);
        $normalized = preg_replace('/[^0-9]/', '', $normalized);

        return $normalized ?: null;
    }

    private function insertOrGetClienteFromCommand($data, $fuente = 'desconocida')
    {
        $telefonoNormalizado = $this->normalizePhoneFromCommand($data->telefono ?? null);

        $cliente = null;

        if (!empty($telefonoNormalizado)) {
            $cliente = DB::table('clientes')
                ->where('telefono', 'like', $telefonoNormalizado)
                ->first();

            if ($cliente) {
                return $cliente->id;
            }
        }

        if (!$cliente && !empty(trim($data->documento ?? ''))) {
            $cliente = DB::table('clientes')
                ->where('documento', $data->documento)
                ->first();

            if ($cliente) {
                return $cliente->id;
            }
        }

        if (!$cliente && !empty(trim($data->correo ?? ''))) {
            $cliente = DB::table('clientes')
                ->where('correo', $data->correo)
                ->first();

            if ($cliente) {
                return $cliente->id;
            }
        }

        $clienteId = DB::table('clientes')->insertGetId([
            'nombre' => $data->nombre,
            'documento' => $data->documento,
            'correo' => $data->correo,
            'telefono' => $telefonoNormalizado,
            'fuente' => $fuente,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $clienteId;
    }
}

