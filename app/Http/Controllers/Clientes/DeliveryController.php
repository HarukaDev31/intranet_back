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
            $cotizaciones = Cotizacion::where('id_contenedor', $idConsolidado)->cotizacionesEnPasoClientes()->get();
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
                'id_user' => 1  ,
                'id_contenedor' => $cotizacion->id_contenedor,
                'importer_nmae' => $request->clienteNombre??$request->clienteRazonSocial,
                'voucher_doc' => $request->clienteDni??$request->clienteRuc,
                'voucher_doc_type' => $request->tipoComprobante,
                'voucher_name' => $request->clienteNombre??$request->clienteRazonSocial,
                'voucher_email' => $request->clienteCorreo,
                'id_agency' => $request->agenciaEnvio,
                'agency_ruc' => $request->rucAgencia,
                'agency_name' => $request->nombreAgencia,
                'r_type' => $request->tipoDestinatario,
                'r_doc' => $request->destinatarioDni ?? $request->destinatarioRuc,
                'r_name' => $request->destinatarioNombre ?? $request->destinatarioRazonSocial,
                'r_phone' => $request->destinatarioCelular,
                'id_department' => $request->destinatarioDepartamento,
                'id_province' => $request->destinatarioProvincia,
                'id_district' => $request->destinatarioDistrito,
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
            //SendDeliveryConfirmationWhatsAppProvinceJob::dispatch($deliveryForm->id);

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
            $rangeDate = DB::table('consolidado_delivery_range_date')
                ->where('id', $request->horarioSeleccionado['range_id'])
                ->first();
            $existsDelivery = DB::table('consolidado_delivery_form_lima')
                ->where('id_range_date', $request->horarioSeleccionado['range_id'])
                ->count();
            Log::info($existsDelivery);
            Log::info($rangeDate->delivery_count);
            if ($existsDelivery >= $rangeDate->delivery_count) {
                return response()->json(['message' => 'El Horario ya no se encuentra disponible', 'success' => false], 400);
            }
            // Mapear los datos del request a los campos de la tabla
            $formData = [
                'id_contenedor' => $cotizacion->id_contenedor,
                'id_user' => 1,
                'id_cotizacion' => $cotizacion->id,
                'id_range_date' => $request->horarioSeleccionado['range_id'] ?? null,
                'pick_name' => $request->nombreCompleto,
                'pick_doc' => $request->dni,
                'import_name' => $request->importador,
                'productos' => $request->tiposProductos,
                'voucher_doc' => $request->clienteDni??$request->clienteRuc,
                'voucher_doc_type' => strtoupper($request->tipoComprobante),
                'voucher_name' => $request->clienteNombre??$request->clienteRazonSocial,
                'voucher_email' => $request->clienteCorreo,
                'drver_name' => $request->choferNombre,
                'driver_doc_type' => 'DNI', // Por defecto DNI
                'driver_doc' => $request->choferDni,
                'driver_license' => $request->choferLicencia,
                'driver_plate' => $request->choferPlaca,
                'final_destination_place' => $request->direccionDestino,
                'final_destination_district' => $request->distritoDestino,
            ];

            // Crear el formulario de delivery}
           $deliveryForm = ConsolidadoDeliveryFormLima::create($formData);

            // Actualizar la cotización para marcar que el formulario fue registrado
            $cotizacion->update([
                'delivery_form_registered_at' => now()->toDateString()
            ]);

            //insert in consolidado_user_range_delivery
            DB::table('consolidado_user_range_delivery')->insert([
                'id_date' => $request->horarioSeleccionado['range_id'],
                'id_range_date' => $request->horarioSeleccionado['range_id'],
                'id_cotizacion' => $cotizacion->id,
                'id_user' => 1,
            ]); 
            // Despachar job para enviar mensaje de WhatsApp
            //SendDeliveryConfirmationWhatsAppLimaJob::dispatch($deliveryForm->id);
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
