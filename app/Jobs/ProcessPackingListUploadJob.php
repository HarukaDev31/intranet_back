<?php

namespace App\Jobs;

use App\Jobs\ValidateCotizacionesWithLoadedProveedoresJob;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\Usuario;
use App\Traits\GoogleSheetsHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPackingListUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, GoogleSheetsHelper;

    protected int $contenedorId;
    protected string $fileUrl;
    protected string $originalFileName;
    protected int $fileSize;
    protected ?int $uploadedByUserId;
    protected ?string $uploadedByUserGroup;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $contenedorId,
        string $fileUrl,
        string $originalFileName,
        int $fileSize,
        ?int $uploadedByUserId = null,
        ?string $uploadedByUserGroup = null
    ) {
        $this->contenedorId = $contenedorId;
        $this->fileUrl = $fileUrl;
        $this->originalFileName = $originalFileName;
        $this->fileSize = $fileSize;
        $this->uploadedByUserId = $uploadedByUserId;
        $this->uploadedByUserGroup = $uploadedByUserGroup;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $contenedor = Contenedor::find($this->contenedorId);

            if (!$contenedor) {
                Log::warning('Contenedor no encontrado al procesar packing list', [
                    'contenedor_id' => $this->contenedorId,
                ]);
                return;
            }

            $contenedor->update([
                'lista_embarque_url' => $this->fileUrl,
                'lista_embarque_uploaded_at' => now(),
            ]);

            Log::info('Packing list almacenado y contenedor actualizado', [
                'contenedor_id' => $contenedor->id,
                'file_url' => $this->fileUrl,
                'file_name' => $this->originalFileName,
                'file_size' => $this->fileSize,
            ]);

            $this->verifyContainerIsCompleted($contenedor);

            // Validación de usuarios/cotizaciones en segundo plano
            ValidateCotizacionesWithLoadedProveedoresJob::dispatch($this->contenedorId)
                ->onQueue('importaciones');

            $this->handleConsolidadoSheet($contenedor);
        } catch (\Exception $e) {
            Log::error('Error procesando packing list en job', [
                'contenedor_id' => $this->contenedorId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function verifyContainerIsCompleted(Contenedor $contenedor): void
    {
        try {
            $listaEmbarque = $contenedor->lista_embarque_url;

            $estadoProveedores = Cotizacion::where('id_contenedor', $contenedor->id)
                ->select('estado')
                ->get();

            $tieneDatosProveedor = $estadoProveedores->contains(function ($estado) {
                return $estado->estado === 'DATOS PROVEEDOR';
            });

            $updateData = [];

            if (!empty($listaEmbarque)) {
                $updateData['estado_china'] = 'COMPLETADO';
            } elseif ($tieneDatosProveedor) {
                // No hacer nada si hay proveedores en "DATOS PROVEEDOR"
            } else {
                $userGroup = $this->uploadedByUserGroup;
                if ($userGroup === Usuario::ROL_COORDINACION || $userGroup === 'Coordinación') {
                    $updateData['estado'] = 'RECIBIENDO';
                }
            }

            if (!empty($updateData)) {
                $contenedor->update($updateData);
                Log::info('Estado del contenedor actualizado tras verificar completion', [
                    'contenedor_id' => $contenedor->id,
                    'update_data' => $updateData,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error al verificar el estado del contenedor en job', [
                'contenedor_id' => $contenedor->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function handleConsolidadoSheet(Contenedor $contenedor): void
    {
        $sheetName = 'CONSOLIDADO ' . ($contenedor->carga ?? '');

        try {
            $spreadsheetId = config('google.post_sheet_status_doc_id');
            if (!$spreadsheetId) {
                Log::warning('POST_SHEET_STATUS_DOC_ID no está configurado');
                return;
            }

            $sheetId = $this->createSheet($sheetName, $spreadsheetId);
            if (!$sheetId) {
                Log::warning("No se pudo crear la hoja {$sheetName} para contenedor {$contenedor->id}");
                return;
            }

            $cotizaciones = $this->getCotizacionesForSheet($contenedor->id);
            if (empty($cotizaciones)) {
                Log::warning("No se encontraron cotizaciones para poblar la hoja {$sheetName}");
                return;
            }

            $numeroCarga = $contenedor->carga ?? '';
            $this->populateConsolidadoSheet($sheetName, $spreadsheetId, $cotizaciones, $numeroCarga);

            Log::info("Hoja {$sheetName} creada y poblada exitosamente", [
                'contenedor_id' => $contenedor->id,
                'cotizaciones' => count($cotizaciones),
            ]);
        } catch (\Exception $e) {
            Log::error("Error al manejar la hoja {$sheetName}", [
                'contenedor_id' => $contenedor->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getCotizacionesForSheet($idContenedor): array
    {
        try {
            $query = DB::table('contenedor_consolidado_cotizacion AS main')
                ->select([
                    'main.id',
                    'main.nombre',
                    'main.documento',
                    'main.telefono',
                    'main.id_contenedor',
                ])
                ->where('main.id_contenedor', $idContenedor)
                ->whereNull('main.id_cliente_importacion')
                ->where('main.estado_cotizador', 'CONFIRMADO')
                ->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('contenedor_consolidado_cotizacion_proveedores as proveedores')
                        ->whereRaw('proveedores.id_cotizacion = main.id')
                        ->where('proveedores.estados_proveedor', 'LOADED');
                })
                ->orderBy('main.id', 'asc');

            $cotizaciones = $query->get();
            $cotizacionesConProveedores = [];

            foreach ($cotizaciones as $cotizacion) {
                $proveedores = DB::table('contenedor_consolidado_cotizacion_proveedores')
                    ->where('id_cotizacion', $cotizacion->id)
                    ->select([
                        'id',
                        'id_cotizacion',
                        'factura_comercial',
                        'packing_list',
                        'excel_confirmacion',
                        'supplier',
                        'code_supplier',
                        'estados_proveedor',
                        'products',
                    ])
                    ->orderBy('id', 'asc')
                    ->get();

                $proveedoresValidados = [];
                foreach ($proveedores as $proveedor) {
                    if ((int) $proveedor->id_cotizacion === (int) $cotizacion->id) {
                        $proveedoresValidados[] = [
                            'id' => $proveedor->id,
                            'id_cotizacion' => $proveedor->id_cotizacion,
                            'factura_comercial' => $proveedor->factura_comercial ?? '',
                            'packing_list' => $proveedor->packing_list ?? '',
                            'excel_confirmacion' => $proveedor->excel_confirmacion ?? '',
                            'supplier' => $proveedor->supplier ?? '',
                            'code_supplier' => $proveedor->code_supplier ?? '',
                            'products' => $proveedor->products ?? '',
                        ];
                    } else {
                        Log::warning("Proveedor con ID {$proveedor->id} no pertenece a cotización {$cotizacion->id}", [
                            'proveedor_id_cotizacion' => $proveedor->id_cotizacion,
                            'cotizacion_id' => $cotizacion->id,
                        ]);
                    }
                }

                if (!empty($proveedoresValidados)) {
                    $cotizacionesConProveedores[] = [
                        'id' => $cotizacion->id,
                        'nombre' => $cotizacion->nombre,
                        'documento' => $cotizacion->documento,
                        'telefono' => $cotizacion->telefono ? preg_replace('/\s+/', '', $cotizacion->telefono) : '',
                        'proveedores' => $proveedoresValidados,
                    ];
                }
            }

            return $cotizacionesConProveedores;
        } catch (\Exception $e) {
            Log::error('Error obteniendo cotizaciones para sheet', [
                'contenedor_id' => $idContenedor,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}

