<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\Contenedor;
use App\Traits\WhatsappTrait;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FacturaGuiaController extends Controller
{
    use WhatsappTrait;

    /**
     * @OA\Get(
     *     path="/carga-consolidada/contenedores/{idContenedor}/factura-guia",
     *     tags={"Factura y Guía"},
     *     summary="Obtener cotizaciones para factura y guía",
     *     description="Obtiene las cotizaciones confirmadas de un contenedor para gestión de facturas y guías",
     *     operationId="getContenedorFacturaGuia",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idContenedor", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Response(response=200, description="Cotizaciones obtenidas exitosamente")
     * )
     */
    public function getContenedorFacturaGuia(Request $request, $idContenedor)
    {
        $perPage = $request->input('per_page', 10);

        $query = Cotizacion::select(
            'contenedor_consolidado_cotizacion.*',
            'contenedor_consolidado_tipo_cliente.*',
            'contenedor_consolidado_cotizacion.id as id_cotizacion'
        )
            ->join(
                'contenedor_consolidado_tipo_cliente',
                'contenedor_consolidado_cotizacion.id_tipo_cliente',
                '=',
                'contenedor_consolidado_tipo_cliente.id'
            )
            ->orderBy('contenedor_consolidado_cotizacion.id', 'asc')
            ->where('id_contenedor', $idContenedor)
            ->whereNotNull('estado_cliente')
            ->whereNull('id_cliente_importacion')
            ->where('estado_cotizador',"CONFIRMADO")

            ->paginate($perPage);

        return response()->json([
            'data' => $query->items(),
            'pagination' => [
                'total' => $query->total(),
                'per_page' => $query->perPage(),
                'current_page' => $query->currentPage(),
                'last_page' => $query->lastPage(),
                'from' => $query->firstItem(),
                'to' => $query->lastItem()
            ],
            'success' => true
        ]);
    }
    /**
     * @OA\Post(
     *     path="/carga-consolidada/contenedor/factura-guia/general/upload-guia-remision",
     *     tags={"Factura y Guía"},
     *     summary="Subir guía de remisión",
     *     description="Sube un archivo de guía de remisión para una cotización",
     *     operationId="uploadGuiaRemision",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="idCotizacion", type="integer"),
     *                 @OA\Property(property="file", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Guía subida exitosamente")
     * )
     */
    public function uploadGuiaRemision(Request $request)
    {
        try {
            $idContenedor = $request->idCotizacion;
            $file = $request->file('file');
            $file->storeAs('cargaconsolidada/guiaremision/' . $idContenedor, $file->getClientOriginalName());
            //update guia remision url
            $cotizacion = Cotizacion::find($idContenedor);
            $cotizacion->guia_remision_url = $file->getClientOriginalName();
            $cotizacion->save();
            return response()->json([
                'success' => true,
                'message' => 'Guia remision actualizada correctamente',
                'path' => $file->getClientOriginalName()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar guia remision: ' . $e->getMessage()
            ]);
        }
    }
    /**
     * @OA\Post(
     *     path="/carga-consolidada/contenedor/factura-guia/general/upload-factura-comercial",
     *     tags={"Factura y Guía"},
     *     summary="Subir factura comercial",
     *     description="Sube un archivo de factura comercial para una cotización",
     *     operationId="uploadFacturaComercialFG",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="idCotizacion", type="integer"),
     *                 @OA\Property(property="file", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Factura subida exitosamente")
     * )
     */
    public function uploadFacturaComercial(Request $request)
    {
        try {
            $idContenedor = $request->idCotizacion;
            $file = $request->file('file');
            $file->storeAs('cargaconsolidada/facturacomercial/' . $idContenedor, $file->getClientOriginalName());
            //update factura comercial 
            $cotizacion = Cotizacion::find($idContenedor);
            $cotizacion->factura_comercial = $file->getClientOriginalName();
            $cotizacion->save();
            return response()->json([
                'success' => true,
                'message' => 'Factura comercial actualizada correctamente',
                'path' => $file->getClientOriginalName()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar factura comercial: ' . $e->getMessage()
            ]);
        }
    }
    /**
     * @OA\Get(
     *     path="/carga-consolidada/contenedor/factura-guia/general/{idContenedor}/headers",
     *     tags={"Factura y Guía"},
     *     summary="Obtener headers de factura y guía",
     *     description="Obtiene los headers de datos para factura y guía",
     *     operationId="getHeadersDataFG",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idContenedor", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Headers obtenidos exitosamente")
     * )
     */
    public function getHeadersData($idContenedor)
    {
        try {
            $contenedor = Contenedor::where('id', $idContenedor)->first();
            $headers = [];
            return response()->json([
                'success' => true,
                'data' => $headers,
                'carga' => $contenedor->carga ?? ''
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener headers: ' . $e->getMessage()
            ]);
        }
    }
    /**
     * @OA\Delete(
     *     path="/carga-consolidada/contenedor/factura-guia/general/delete-factura-comercial/{idContenedor}",
     *     tags={"Factura y Guía"},
     *     summary="Eliminar factura comercial",
     *     description="Elimina la factura comercial de una cotización",
     *     operationId="deleteFacturaComercialFG",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idContenedor", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Factura eliminada exitosamente"),
     *     @OA\Response(response=404, description="Factura no encontrada")
     * )
     */
    public function deleteFacturaComercial($idContenedor)
    {
        $cotizacion = Cotizacion::find($idContenedor);
        if($cotizacion->factura_comercial){
            $path = storage_path('app/' . $cotizacion->factura_comercial);
            if(file_exists($path)){
                unlink($path);
            }
        }else{
            return response()->json([
                'success' => false,
                'message' => 'Factura comercial no encontrada'
            ]);
        }
        $cotizacion->factura_comercial = null;
        $cotizacion->save();
        return response()->json([
            'success' => true,
            'message' => 'Factura comercial eliminada correctamente'
        ]);
    }
    /**
     * @OA\Delete(
     *     path="/carga-consolidada/contenedor/factura-guia/general/delete-guia-remision/{idContenedor}",
     *     tags={"Factura y Guía"},
     *     summary="Eliminar guía de remisión",
     *     description="Elimina la guía de remisión de una cotización",
     *     operationId="deleteGuiaRemision",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idContenedor", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Guía eliminada exitosamente"),
     *     @OA\Response(response=404, description="Guía no encontrada")
     * )
     */
    public function deleteGuiaRemision($idContenedor)
    {
        $cotizacion = Cotizacion::find($idContenedor);
        if($cotizacion->guia_remision_url){
            $path = storage_path('app/' . $cotizacion->guia_remision_url);
            if(file_exists($path)){
                unlink($path);
            }
        }else{
            return response()->json([
                'success' => false,
                'message' => 'Guia remision no encontrada'
            ]);
        }
        $cotizacion->guia_remision_url = null;
        $cotizacion->save();
        return response()->json([
            'success' => true,
            'message' => 'Guia remision eliminada correctamente'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/carga-consolidada/contenedor/factura-guia/send-factura/{idCotizacion}",
     *     tags={"Factura y Guía"},
     *     summary="Enviar factura por WhatsApp",
     *     description="Envía la factura comercial al cliente por WhatsApp",
     *     operationId="sendFactura",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idCotizacion", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Factura enviada exitosamente"),
     *     @OA\Response(response=404, description="Cotización no encontrada")
     * )
     *
     * Enviar factura comercial por WhatsApp
     */
    public function sendFactura($idCotizacion)
    {
        try {
            $cotizacion = Cotizacion::find($idCotizacion);
            
            if (!$cotizacion) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cotización no encontrada'
                ], 404);
            }

            // Validar que la factura existe
            if (!$cotizacion->factura_comercial) {
                return response()->json([
                    'success' => false,
                    'error' => 'No hay factura comercial disponible para esta cotización'
                ], 400);
            }

            // Obtener la ruta del archivo
            $filePath = storage_path('app/cargaconsolidada/facturacomercial/' . $idCotizacion . '/' . $cotizacion->factura_comercial);
            
            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'error' => 'El archivo de factura comercial no se encuentra en el servidor'
                ], 404);
            }

            // Obtener teléfono del cliente
            $telefono = preg_replace('/\D+/', '', $cotizacion->telefono);
            if (empty($telefono)) {
                return response()->json([
                    'success' => false,
                    'error' => 'El cliente no tiene un número de teléfono válido'
                ], 400);
            }

            // Formatear número de WhatsApp
            if (strlen($telefono) < 9) {
                $telefono = '51' . $telefono;
            }
            $numeroWhatsapp = $telefono . '@c.us';

            // Crear mensaje
            $contenedor = Contenedor::find($cotizacion->id_contenedor);
            $carga = $contenedor ? $contenedor->carga : 'N/A';
            /**message: Buenos tardes #nombrecliente 🙋🏻‍♀, te adjunto la factura de tu consolidado ##

✅ Verificar que el monto de crédito fiscal sea el correcto.
✅ Recordar, solo recuperan como crédito fiscal el 18% (IGV + IPM) que esta contemplado en su cotización final.
✅ El plazo máximo para notificar una observación de su comprobante es de 24 h. Después de este periodo, no será posible realizar modificaciones de ningún tipo. */
            $message = "Buenas tardes " . $cotizacion->nombre . " 🙋🏻‍♀, te adjunto la factura de tu consolidado #" . $carga . ".\n\n"  .
            "✅ Verificar que el monto de crédito fiscal sea el correcto.\n\n" .
            "✅ Recordar, solo recuperan como crédito fiscal el 18% (IGV + IPM) que esta contemplado en su cotización final.\n\n" .
            "✅ El plazo máximo para notificar una observación de su comprobante es de 24 h. Después de este periodo, no será posible realizar modificaciones de ningún tipo.";

            // Detectar MIME type del archivo
            $mimeType = mime_content_type($filePath);
            if (!$mimeType) {
                // Fallback a application/pdf si no se puede detectar
                $mimeType = 'application/pdf';
            }

            $result = $this->sendMedia($filePath, $mimeType, $message, $numeroWhatsapp, 0, 'administracion', $cotizacion->factura_comercial);

            if ($result === false) {
                Log::error('Error al enviar factura por WhatsApp: sendMedia devolvió false', [
                    'id_cotizacion' => $idCotizacion,
                    'telefono' => $numeroWhatsapp,
                    'archivo' => $cotizacion->factura_comercial
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Error al enviar la factura por WhatsApp: No se pudo procesar el archivo'
                ], 500);
            }

            // Verificar si es un array con la estructura esperada
            if (!is_array($result) || !isset($result['status'])) {
                Log::error('Error al enviar factura por WhatsApp: Respuesta inválida de sendMedia', [
                    'id_cotizacion' => $idCotizacion,
                    'result' => $result
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Error al enviar la factura por WhatsApp: Respuesta inválida del servicio'
                ], 500);
            }

            if ($result['status']) {
                Log::info('Factura comercial enviada por WhatsApp', [
                    'id_cotizacion' => $idCotizacion,
                    'telefono' => $numeroWhatsapp,
                    'archivo' => $cotizacion->factura_comercial
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Factura comercial enviada correctamente por WhatsApp',
                    'data' => [
                        'messageId' => $result['response']['messageId'] ?? null,
                        'sentAt' => now()->toISOString()
                    ]
                ]);
            } else {
                $errorMessage = $result['response']['error'] ?? 'Error desconocido al enviar el mensaje';
                Log::error('Error al enviar factura por WhatsApp', [
                    'id_cotizacion' => $idCotizacion,
                    'error' => $errorMessage,
                    'response' => $result['response'] ?? null
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Error al enviar la factura por WhatsApp: ' . $errorMessage
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Excepción al enviar factura por WhatsApp: ' . $e->getMessage(), [
                'id_cotizacion' => $idCotizacion,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al enviar la factura por WhatsApp: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/carga-consolidada/contenedor/factura-guia/send-guia/{idCotizacion}",
     *     tags={"Factura y Guía"},
     *     summary="Enviar guía por WhatsApp",
     *     description="Envía la guía de remisión al cliente por WhatsApp",
     *     operationId="sendGuia",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idCotizacion", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Guía enviada exitosamente"),
     *     @OA\Response(response=404, description="Cotización no encontrada")
     * )
     *
     * Enviar guía de remisión por WhatsApp
     */
    public function sendGuia($idCotizacion)
    {
        try {
            $cotizacion = Cotizacion::find($idCotizacion);
            
            if (!$cotizacion) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cotización no encontrada'
                ], 404);
            }

            // Validar que la guía existe
            if (!$cotizacion->guia_remision_url) {
                return response()->json([
                    'success' => false,
                    'error' => 'No hay guía de remisión disponible para esta cotización'
                ], 400);
            }

            // Obtener la ruta del archivo
            $filePath = storage_path('app/cargaconsolidada/guiaremision/' . $idCotizacion . '/' . $cotizacion->guia_remision_url);
            
            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'error' => 'El archivo de guía de remisión no se encuentra en el servidor'
                ], 404);
            }

            // Obtener teléfono del cliente
            $telefono = preg_replace('/\D+/', '', $cotizacion->telefono);
            if (empty($telefono)) {
                return response()->json([
                    'success' => false,
                    'error' => 'El cliente no tiene un número de teléfono válido'
                ], 400);
            }

            // Formatear número de WhatsApp
            if (strlen($telefono) < 9) {
                $telefono = '51' . $telefono;
            }
            $numeroWhatsapp = $telefono . '@c.us';

            // Crear mensaje
            $contenedor = Contenedor::find($cotizacion->id_contenedor);
            $carga = $contenedor ? $contenedor->carga : 'N/A';
            /**Hola [Nombre] 😊

Te envío tu Guía de Remisión del consolidado ## para que puedas realizar el recojo de tu mercadería.

🏢 Dirección de recojo:
Calle Río Nazca 243 – San Luis
📍 Referencia: Al costado de la Agencia Antezana

➡ MAPS: https://maps.app.goo.gl/5raLmkX65nNHB2Fr9

Cualquier duda nos escribe.  ¡Gracias! */
            $message =  "Hola " . $cotizacion->nombre . " 😊,\n\n" .
                       "Te envío tu Guía de Remisión del consolidado #" . $carga . " para que puedas realizar el recojo de tu mercadería.\n\n" .
                       "🏢 Dirección de recojo:\nCalle Río Nazca 243 – San Luis\n📍 Referencia: Al costado de la Agencia Antezana\n\n" .
                       "➡ MAPS: https://maps.app.goo.gl/5raLmkX65nNHB2Fr9\n\n" .
                       "Cualquier duda nos escribe.  ¡Gracias!";

            // Detectar MIME type del archivo
            $mimeType = mime_content_type($filePath);
            if (!$mimeType) {
                // Fallback a application/pdf si no se puede detectar
                $mimeType = 'application/pdf';
            }

            // Enviar documento por WhatsApp
            // sendMedia($filePath, $mimeType = null, $message = null, $phoneNumberId = null, $sleep = 0, $fromNumber = 'consolidado', $fileName = null)
            $result = $this->sendMedia($filePath, $mimeType, $message, $numeroWhatsapp, 0, 'administracion', $cotizacion->guia_remision_url);

            // Verificar si sendMedia devolvió false (error)
            if ($result === false) {
                Log::error('Error al enviar guía por WhatsApp: sendMedia devolvió false', [
                    'id_cotizacion' => $idCotizacion,
                    'telefono' => $numeroWhatsapp,
                    'archivo' => $cotizacion->guia_remision_url
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Error al enviar la guía de remisión por WhatsApp: No se pudo procesar el archivo'
                ], 500);
            }

            // Verificar si es un array con la estructura esperada
            if (!is_array($result) || !isset($result['status'])) {
                Log::error('Error al enviar guía por WhatsApp: Respuesta inválida de sendMedia', [
                    'id_cotizacion' => $idCotizacion,
                    'result' => $result
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Error al enviar la guía de remisión por WhatsApp: Respuesta inválida del servicio'
                ], 500);
            }

            if ($result['status']) {
                Log::info('Guía de remisión enviada por WhatsApp', [
                    'id_cotizacion' => $idCotizacion,
                    'telefono' => $numeroWhatsapp,
                    'archivo' => $cotizacion->guia_remision_url
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Guía de remisión enviada correctamente por WhatsApp',
                    'data' => [
                        'messageId' => $result['response']['messageId'] ?? null,
                        'sentAt' => now()->toISOString()
                    ]
                ]);
            } else {
                $errorMessage = $result['response']['error'] ?? 'Error desconocido al enviar el mensaje';
                Log::error('Error al enviar guía por WhatsApp', [
                    'id_cotizacion' => $idCotizacion,
                    'error' => $errorMessage,
                    'response' => $result['response'] ?? null
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Error al enviar la guía de remisión por WhatsApp: ' . $errorMessage
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Excepción al enviar guía por WhatsApp: ' . $e->getMessage(), [
                'id_cotizacion' => $idCotizacion,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al enviar la guía de remisión por WhatsApp: ' . $e->getMessage()
            ], 500);
        }
    }
}
