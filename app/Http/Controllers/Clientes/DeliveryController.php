<?php

namespace App\Http\Controllers\Clientes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\DeliveryAgency;
use App\Models\CargaConsolidada\ConsolidadoDeliveryFormProvince;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
            return response()->json(['error' => $e->getMessage()], 500);
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
            $user = JWTAuth::user();
            $user = User::find($user->id);
            /**
             {"importador":"72df7d85-f5c5-4053-bf38-a3e9dd314ba3","tipoComprobante":"boleta","tiposProductos":"213213","clienteDni":"21313213123","clienteNombre":"123213","clienteCorreo":"123123123","clienteRuc":"","clienteRazonSocial":"","tipoDestinatario":"persona","destinatarioDni":"123123","destinatarioNombre":"132321321312","destinatarioCelular":"312312312","destinatarioDepartamento":2,"destinatarioProvincia":2,"destinatarioDistrito":84,"destinatarioRuc":"","destinatarioRazonSocial":"","agenciaEnvio":5,"nombreAgencia":"213213","rucAgencia":"12321","direccionAgenciaLima":"312","direccionAgenciaDestino":"21321321","direccionDomicilio":"31313"}
             */
            $cotizacion = Cotizacion::where('uuid', $request->importador)->first();
            if (!$cotizacion) {
                return response()->json(['message' => 'Cotizacion no encontrada','success' => false], 404);
            }
            //if exists other delivery form registered with same id_cotizacion
            $otherDeliveryForm = ConsolidadoDeliveryFormProvince::where('id_cotizacion', $cotizacion->id)->first();
            if ($otherDeliveryForm) {
                return response()->json(['message' => 'Ya existe otro formulario de delivery registrado','success' => false], 400);
            }
            $data = [
                'id_cotizacion' => $cotizacion->id,
                'id_user' => $user->id,
                'id_contenedor' => $cotizacion->id_contenedor,
                'importer_nmae' => $request->clienteNombre,
                'voucher_doc' => $request->clienteDni,
                'voucher_doc_type' => $request->tipoComprobante,
                'voucher_name' => $request->clienteNombre,
                'voucher_email' => $request->clienteCorreo,
                'id_agency' => $request->agenciaEnvio,
                'agency_ruc' => $request->rucAgencia,
                'agency_name' => $request->nombreAgencia,
                'r_type' => $request->tipoDestinatario,
                'r_doc' => $request->destinatarioDni??$request->destinatarioRuc,
                'r_name' => $request->destinatarioNombre??$request->destinatarioRazonSocial,
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
                return response()->json(['message' => 'Error al registrar el formulario de delivery','success' => false], 500);
            }
            $cotizacion->update([
                'delivery_form_registered_at' => now(),
            ]);
            DB::commit();
            return response()->json([
                'data' => $deliveryForm,
                'success' => true,
                'message' => 'Formulario de delivery registrado correctamente',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage(),'success' => false], 500);
        }
    }
}
