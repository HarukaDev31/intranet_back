<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Helpers\ClienteLookupHelper;
use App\Support\Phone\CountryPhoneHelper;
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
                ->leftJoin('carga_consolidada_contenedor as cont', 'cont.id', '=', 'ccc.id_contenedor')
                ->leftJoin('pais_flags as pf', 'pf.id_pais', '=', 'cont.id_pais')
                ->where('ccc.id_contenedor', $this->contenedorId)
                ->whereNull('ccc.deleted_at')
                ->where('cccp.estados_proveedor', 'LOADED')
                ->where(function ($q) {
                    $q->where('ccc.estado_cotizador', 'CONFIRMADO')
                        ->orWhere('ccc.estado_resumen', 'CONFIRMADO');
                })
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
                ->select(
                    'ccc.id',
                    'ccc.telefono',
                    'ccc.nombre',
                    'ccc.documento',
                    'ccc.correo',
                    'ccc.fecha',
                    'cont.id_pais',
                    'pf.phone_code',
                    DB::raw('COALESCE(ccc.organizacion_id, cont.organizacion_id) as organizacion_id')
                )
                ->distinct()
                ->get();

            Log::info('Cotizaciones encontradas con proveedores cargados: ' . $cotizaciones->count());

            $validados = 0;
            $clientesCreados = 0;
            $clientesEncontrados = 0;

            foreach ($cotizaciones as $cotizacion) {
                $telefono = CountryPhoneHelper::ensureCountryCode(
                    $cotizacion->telefono,
                    $cotizacion->phone_code ?? null
                );
                $clienteObj = (object) [
                    'nombre' => $cotizacion->nombre,
                    'documento' => $cotizacion->documento,
                    'correo' => $cotizacion->correo,
                    'telefono' => $telefono !== '' ? $telefono : $cotizacion->telefono,
                ];

                if ($this->validateClienteDataFromCommand($clienteObj)) {
                    $validados++;
                    $orgId = (int) ($cotizacion->organizacion_id ?: 1);
                    $resultado = $this->insertOrGetClienteFromCommand(
                        $clienteObj,
                        $cotizacion->fecha ?? null,
                        $orgId,
                        $cotizacion->phone_code ?? null
                    );
                    $clienteId = $resultado ? (int) $resultado['id'] : 0;
                    $fueCreado = $resultado ? (bool) $resultado['created'] : false;

                    if ($clienteId) {
                        DB::table('contenedor_consolidado_cotizacion')
                            ->where('id', $cotizacion->id)
                            ->update(['id_cliente' => $clienteId]);

                        if ($fueCreado) {
                            $clientesCreados++;
                        } else {
                            $clientesEncontrados++;
                        }

                        Log::info("✅ Cliente validado para cotización con proveedor cargado", [
                            'cotizacion_id' => $cotizacion->id,
                            'cliente_id' => $clienteId,
                            'nombre' => $clienteObj->nombre,
                            'fue_creado' => $fueCreado
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
        $digits = CountryPhoneHelper::digits($phone);

        return $digits !== '' ? $digits : null;
    }

    private function insertOrGetClienteFromCommand($data, $fecha = null, $organizacionId = 1, $callingCode = null)
    {
        $organizacionId = (int) $organizacionId ?: 1;

        $existente = ClienteLookupHelper::findClienteByContact(
            $data->correo ?? null,
            $data->telefono ?? null,
            $data->documento ?? null,
            $organizacionId,
            $callingCode
        );

        if ($existente) {
            return ['id' => (int) $existente->id, 'created' => false];
        }

        $clienteId = DB::table('clientes')->insertGetId([
            'nombre' => $data->nombre,
            'documento' => $data->documento,
            'correo' => $data->correo,
            'telefono' => $this->normalizePhoneFromCommand($data->telefono ?? null),
            'fecha' => $fecha ?: now()->toDateString(),
            'organizacion_id' => $organizacionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => (int) $clienteId, 'created' => true];
    }
}

