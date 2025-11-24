<?php

namespace App\Http\Controllers\Clientes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\DeliveryAgency;
use App\Models\CargaConsolidada\ConsolidadoDeliveryFormProvince;
use App\Models\CargaConsolidada\ConsolidadoDeliveryFormLima;
use App\Jobs\SendDeliveryConfirmationWhatsAppLimaJob;
use App\Jobs\SendDeliveryConfirmationWhatsAppProvinceJob;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeliveryController extends Controller
{
    public function getClientesConsolidado($idConsolidado)
    {
        try {
            // Obtener cotizaciones que:
            // 1. No tienen formulario registrado, O
            // 2. Tienen formulario de Lima pero sin horario asignado (id_range_date es null)
            $cotizaciones = Cotizacion::where('id_contenedor', $idConsolidado)
                ->whereNull('id_cliente_importacion')
                ->where('estado_cotizador', 'CONFIRMADO')
                ->whereNotNull('estado_cliente')
                ->cotizacionesEnPasoClientes()
                ->where(function ($query) {
                    // No tienen formulario registrado
                    $query->whereNull('delivery_form_registered_at')
                        // O tienen formulario de Lima pero sin horario asignado
                        ->orWhereExists(function ($subQuery) {
                            $subQuery->select(DB::raw(1))
                                ->from('consolidado_delivery_form_lima')
                                ->whereColumn('consolidado_delivery_form_lima.id_cotizacion', 'contenedor_consolidado_cotizacion.id')
                                ->whereNull('consolidado_delivery_form_lima.id_range_date');
                        });
                })
                ->get();
            
            $contenedor = Contenedor::find($idConsolidado);
            if (!$contenedor) {
                return response()->json(['error' => 'Carga no encontrada'], 404);
            }
            $carga = $contenedor->carga;
            $cotizaciones = $cotizaciones->map(function ($cotizacion) {
                return [
                    'value' => $cotizacion->uuid,
                    'label' => $cotizacion->nombre,
                ];
            });
            $response = [
                'data' => $cotizaciones,
                'carga' => $carga,
                'success' => true,
                'message' => 'Cotizaciones obtenidas correctamente',
            ];
            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage(), 'success' => false], 500);
        }
    }

    /**
     * Obtiene el estado del formulario de delivery de Lima guardado para una cotización
     * Devuelve el formulario sin horario (sin fechaEntrega ni horarioSeleccionado)
     */
    public function getFormularioLimaByCotizacion($cotizacionUuid)
    {
        try {
            // Buscar la cotización por UUID
            $cotizacion = Cotizacion::where('uuid', $cotizacionUuid)->first();
            
            if (!$cotizacion) {
                return response()->json([
                    'message' => 'Cotización no encontrada',
                    'success' => false
                ], 404);
            }

            // Buscar el formulario de delivery de Lima asociado
            $deliveryForm = ConsolidadoDeliveryFormLima::where('id_cotizacion', $cotizacion->id)
                ->first();

            if (!$deliveryForm) {
                return response()->json([
                    'message' => 'No se encontró formulario guardado',
                    'success' => false,
                    'data' => null
                ], 404);
            }

            // Solo devolver el formulario si NO tiene horario asignado (id_range_date es null)
            if ($deliveryForm->id_range_date !== null) {
                return response()->json([
                    'message' => 'El formulario ya tiene un horario asignado',
                    'success' => false,
                    'data' => null
                ], 400);
            }

            // Mapear los datos del formulario al formato esperado por el frontend
            $formData = [
                'nombreCompleto' => $deliveryForm->pick_name ?? '',
                'dni' => $deliveryForm->pick_doc ?? '',
                'importador' => [
                    'label' => $cotizacion->nombre ?? '',
                    'value' => $cotizacion->uuid ?? ''
                ],
                'tipoComprobante' => [
                    'label' => $deliveryForm->voucher_doc_type ?? 'BOLETA',
                    'value' => $deliveryForm->voucher_doc_type ?? 'BOLETA'
                ],
                'tiposProductos' => $deliveryForm->productos ?? '',
                'clienteDni' => $deliveryForm->voucher_doc_type === 'BOLETA' ? $deliveryForm->voucher_doc : '',
                'clienteNombre' => $deliveryForm->voucher_doc_type === 'BOLETA' ? $deliveryForm->voucher_name : '',
                'clienteCorreo' => $deliveryForm->voucher_email ?? '',
                'clienteRuc' => $deliveryForm->voucher_doc_type === 'FACTURA' ? $deliveryForm->voucher_doc : '',
                'clienteRazonSocial' => $deliveryForm->voucher_doc_type === 'FACTURA' ? $deliveryForm->voucher_name : '',
                'choferNombre' => $deliveryForm->drver_name ?? '',
                'choferDni' => $deliveryForm->driver_doc ?? '',
                'choferLicencia' => $deliveryForm->driver_license ?? '',
                'choferPlaca' => $deliveryForm->driver_plate ?? '',
                'direccionDestino' => $deliveryForm->final_destination_place ?? '',
                'distritoDestino' => $deliveryForm->final_destination_district ?? '',
                // No incluir fechaEntrega ni horarioSeleccionado
            ];

            return response()->json([
                'message' => 'Formulario obtenido correctamente',
                'success' => true,
                'data' => [
                    'formData' => $formData,
                    'currentStep' => 4, // Siempre devolver en el paso 4 (selección de fecha)
                    'timestamp' => $deliveryForm->updated_at->timestamp * 1000
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error obteniendo formulario de Lima por cotización', [
                'cotizacion_uuid' => $cotizacionUuid,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Error al obtener el formulario',
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getAgencies()
    {
        $agencies = DeliveryAgency::all();
        $agencies = $agencies->map(function ($agency) {
            return [
                'value' => $agency->id,
                'label' => $agency->name,
            ];
        });
        return response()->json([
            'data' => $agencies,
            'success' => true,
            'message' => 'Agencias obtenidas correctamente',
        ]);
    }
    public function storeProvinciaForm(Request $request)
    {
        DB::beginTransaction();
        try {
            $idUser = JWTAuth::user()->id;
            $cotizacion = Cotizacion::where('uuid', $request->importador)->first();
            if (!$cotizacion) {
                return response()->json(['message' => 'Cotizacion no encontrada', 'success' => false], 404);
            }
            //if exists other delivery form registered with same id_cotizacion
            $otherDeliveryForm = ConsolidadoDeliveryFormProvince::where('id_cotizacion', $cotizacion->id)->first();
            if ($otherDeliveryForm) {
                return response()->json(['message' => 'Ya existe otro formulario de delivery registrado', 'success' => false], 400);
            }
            $otherDeliveryFormLima = ConsolidadoDeliveryFormLima::where('id_cotizacion', $cotizacion->id)->first();
            if ($otherDeliveryFormLima) {
                return response()->json(['message' => 'Ya existe otro formulario de delivery registrado', 'success' => false], 400);
            }
            ///validate if existe equal or more deliveries for this date , use delivery_count column in table consolidado_delivery_range_date use query builder

            $data = [
                'id_cotizacion' => $cotizacion->id,
                'id_user' => $idUser,
                'id_contenedor' => $cotizacion->id_contenedor,
                'importer_nmae' => $request->clienteNombre ?? $request->clienteRazonSocial,
                'productos' => is_array($request->tiposProductos) ? implode(', ', $request->tiposProductos) : $request->tiposProductos,
                'voucher_doc' => $request->clienteDni ?? $request->clienteRuc,
                'voucher_doc_type' => $request->tipoComprobante,
                'voucher_name' => $request->clienteNombre ?? $request->clienteRazonSocial,
                'voucher_email' => $request->clienteCorreo,
                'id_agency' => $request->agenciaEnvio,
                'agency_ruc' => $request->rucAgencia,
                'agency_name' => $request->nombreAgencia,
                'r_type' => $request->tipoDestinatario,
                'r_doc' => $request->destinatarioDni ?? $request->destinatarioRuc,
                'r_name' => $request->destinatarioNombre ?? $request->destinatarioRazonSocial,
                'r_phone' => $request->destinatarioCelular,
                'id_department' => is_array($request->destinatarioDepartamento) ? $request->destinatarioDepartamento['value'] : $request->destinatarioDepartamento,
                'id_province' => is_array($request->destinatarioProvincia) ? $request->destinatarioProvincia['value'] : $request->destinatarioProvincia,
                'id_district' => is_array($request->destinatarioDistrito) ? $request->destinatarioDistrito['value'] : $request->destinatarioDistrito,
                'r_ruc' => $request->destinatarioRuc,
                'r_razon_social' => $request->destinatarioRazonSocial,
                'r_direccion' => $request->direccionDomicilio,
                'agency_address_initial_delivery' => $request->direccionAgenciaLima,
                'agency_address_final_delivery' => $request->direccionAgenciaDestino,
                'home_adress_delivery' => $request->direccionDomicilio,
            ];
            //store delivery form
            $deliveryForm = ConsolidadoDeliveryFormProvince::create($data);
            if (!$deliveryForm) {
                return response()->json(['message' => 'Error al registrar el formulario de delivery', 'success' => false], 500);
            }
            $cotizacion->update([
                'delivery_form_registered_at' => now(),
            ]);

            // Despachar job para enviar mensaje de WhatsApp
            SendDeliveryConfirmationWhatsAppProvinceJob::dispatch($deliveryForm->id)->onQueue('emails');

            DB::commit();
            return response()->json([
                'data' => $deliveryForm,
                'success' => true,
                'message' => 'Formulario de delivery registrado correctamente',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage(), 'success' => false], 500);
        }
    }
    public function storeLimaForm(Request $request)
    {
        /**
     * {"nombreCompleto":"Miguel Villegas Perez","dni":"48558558","importador":"miguel_villegas","tipoComprobante":"boleta","tiposProductos":"Juguetes, stickers, botellas de agua, artículos de oficina","clienteDni":"48558558","clienteNombre":"Miguel Villegas Perez","clienteCorreo":"mvillegas@probusiness.pe","clienteRuc":"20603287721","clienteRazonSocial":"Grupo Pro Business sac","choferNombre":"","choferDni":"456457457","choferLicencia":"456457457","choferPlaca":"456457457","direccionDestino":"","distritoDestino":"","fechaEntrega":"2025-09-30T05:00:00.000Z","horarioSeleccionado":{"range_id":1,"start_time":"10:00:00","end_time":"13:00:00","capacity":2,"assigned":0,"available":2}}
         */
        DB::beginTransaction();
        try {
            // Validar que la cotización existe
            $cotizacion = Cotizacion::where('uuid', $request->importador)->first();
            if (!$cotizacion) {
                return response()->json([
                    'message' => 'Cotización no encontrada',
                    'success' => false
                ], 404);
            }

           
            //validate if exists other delivery form registered with same id_cotizacion on table province or lima
            $otherDeliveryForm = ConsolidadoDeliveryFormLima::where('id_cotizacion', $cotizacion->id)->first();
            if ($otherDeliveryForm) {
                return response()->json(['message' => 'Ya existe otro formulario de delivery registrado', 'success' => false], 400);
            }
            $otherDeliveryFormProvince = ConsolidadoDeliveryFormProvince::where('id_cotizacion', $cotizacion->id)->first();
            if ($otherDeliveryFormProvince) {
                return response()->json(['message' => 'Ya existe otro formulario de delivery registrado', 'success' => false], 400);
            }
            // Obtener el rango de horario desde BD (fuente de la verdad) - Opcional
            $rangeId = $request->horarioSeleccionado['range_id'] ?? null;
            $rangeDate = null;
            
            // Solo validar horario si se proporciona range_id
            if ($rangeId) {
                $rangeDate = DB::table('consolidado_delivery_range_date')
                    ->select('id', 'id_date', 'delivery_count')
                    ->where('id', $rangeId)
                    ->first();
                if (!$rangeDate) {
                    return response()->json(['message' => 'Horario seleccionado no válido', 'success' => false], 400);
                }
                // Validar que el id_date referenciado exista para no romper la FK
                $rangeDateExists = DB::table('consolidado_delivery_date')
                    ->where('id', $rangeDate->id_date)
                    ->exists();
                if (!$rangeDateExists) {
                    return response()->json(['message' => 'Fecha de entrega no válida para el horario seleccionado', 'success' => false], 400);
                }
                // Validar disponibilidad del horario
                $existsDelivery = DB::table('consolidado_delivery_form_lima')
                    ->where('id_range_date', $rangeDate->id)
                    ->count();
                Log::info($existsDelivery);
                Log::info($rangeDate->delivery_count);
                if ($existsDelivery >= $rangeDate->delivery_count) {
                    return response()->json(['message' => 'El Horario ya no se encuentra disponible', 'success' => false], 400);
                }
            }
            $idUser = JWTAuth::user()->id;
            $formData = [
                'id_contenedor' => $cotizacion->id_contenedor,
                'id_user' => $idUser,
                'id_cotizacion' => $cotizacion->id,
                'id_range_date' => $rangeId, // Puede ser null si no se proporciona
                'pick_name' => $request->nombreCompleto,
                'pick_doc' => $request->dni,
                'import_name' => $request->importador,
                'productos' => is_array($request->tiposProductos) ? implode(', ', $request->tiposProductos) : $request->tiposProductos,
                'voucher_doc' => $request->clienteDni ?? $request->clienteRuc,
                'voucher_doc_type' => strtoupper($request->tipoComprobante),
                'voucher_name' => $request->clienteNombre ?? $request->clienteRazonSocial,
                'voucher_email' => $request->clienteCorreo,
                'drver_name' => $request->choferNombre,
                'driver_doc_type' => 'DNI', // Por defecto DNI
                'driver_doc' => $request->choferDni,
                'driver_license' => $request->choferLicencia,
                'driver_plate' => $request->choferPlaca,
                'final_destination_place' => $request->direccionDestino,
                'final_destination_district' => $request->distritoDestino,
            ];

            // Crear el formulario de delivery
            $deliveryForm = ConsolidadoDeliveryFormLima::create($formData);

            // Actualizar la cotización para marcar que el formulario fue registrado
            $cotizacion->update([
                'delivery_form_registered_at' => now()->toDateString()
            ]);

            // Insertar en consolidado_user_range_delivery solo si hay range_id
            if ($rangeId && $rangeDate) {
                DB::table('consolidado_user_range_delivery')->insert([
                    'id_date' => $rangeDate->id_date, // usar el id_date real del rango
                    'id_range_date' => $rangeDate->id,
                    'id_cotizacion' => $cotizacion->id,
                    'id_user' => $idUser,
                ]);
            }
            // Despachar job para enviar mensaje de WhatsApp
            SendDeliveryConfirmationWhatsAppLimaJob::dispatch($deliveryForm->id)->onQueue('emails');
            DB::commit();

            return response()->json([
                'message' => 'Formulario de delivery registrado correctamente',
                'success' => true,
                'data' => [
                    'id' => $deliveryForm->id,
                    'cotizacion_uuid' => $cotizacion->uuid,
                    'fecha_registro' => $deliveryForm->created_at->format('Y-m-d H:i:s')
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al registrar el formulario de delivery',
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        } 
    }
}
