<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\FacturaComercial;
use App\Traits\WhatsappTrait;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Traits\FileTrait;
class FacturaGuiaController extends Controller
{
    use WhatsappTrait;
    use FileTrait;
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
            'contenedor_consolidado_tipo_cliente.name as tipo_cliente_nombre',
        )
            ->with(['facturasComerciales' => function ($q) {
                $q->select('id', 'quotation_id', 'file_name', 'file_path', 'size', 'mime_type', 'created_at');
            }])
            ->join(
                'contenedor_consolidado_tipo_cliente',
                'contenedor_consolidado_cotizacion.id_tipo_cliente',
                '=',
                'contenedor_consolidado_tipo_cliente.id'
            )
            ->orderBy('contenedor_consolidado_cotizacion.id', 'asc')
            ->where('contenedor_consolidado_cotizacion.id_contenedor', $idContenedor)
            ->whereNotNull('contenedor_consolidado_cotizacion.estado_cliente')
            ->whereNull('contenedor_consolidado_cotizacion.id_cliente_importacion')
            ->where('contenedor_consolidado_cotizacion.estado_cotizador', "CONFIRMADO")
            ->paginate($perPage);

        // Agregar facturas_comerciales a cada item
        $items = collect($query->items())->map(function ($item) {
            $item->facturas_comerciales = $item->facturasComerciales;
            $item->id_cotizacion = $item->id;
            $item->file_path = $this->generateImageUrl($item->file_path);
            unset($item->facturasComerciales);
            return $item;
        });

        return response()->json([
            'data' => $items,
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
     *     summary="Subir factura(s) comercial(es)",
     *     description="Sube uno o múltiples archivos de factura comercial para una cotización",
     *     operationId="uploadFacturaComercialFG",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="idCotizacion", type="integer"),
     *                 @OA\Property(
     *                     property="files[]",
     *                     type="array",
     *                     @OA\Items(type="string", format="binary"),
     *                     description="Array de archivos de factura comercial"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Factura(s) subida(s) exitosamente")
     * )
     */
    public function uploadFacturaComercial(Request $request)
    {
        try {
            $idCotizacion = $request->idCotizacion;
            
            if (!$idCotizacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'El ID de cotización es requerido'
                ], 400);
            }

            // Verificar que la cotización existe
            $cotizacion = Cotizacion::find($idCotizacion);
            if (!$cotizacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cotización no encontrada'
                ], 404);
            }

            // Obtener los archivos (puede ser uno o múltiples)
            $files = $request->file('files');
            
            if (!$files || (is_array($files) && count($files) === 0)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se proporcionaron archivos para subir'
                ], 400);
            }

            // Asegurar que siempre sea un array
            if (!is_array($files)) {
                $files = [$files];
            }

            $uploadedFiles = [];
            $errors = [];

            foreach ($files as $file) {
                try {
                    // Validar que el archivo sea válido
                    if (!$file || !$file->isValid()) {
                        $errors[] = 'Archivo inválido: ' . ($file ? $file->getClientOriginalName() : 'desconocido');
                        continue;
                    }

                    $originalName = $file->getClientOriginalName();
                    $fileSize = $file->getSize();
                    $mimeType = $file->getMimeType();
                    
                    // Guardar el archivo en el almacenamiento
                    $storedPath = $file->storeAs(
                        'cargaconsolidada/facturacomercial/' . $idCotizacion,
                        $originalName
                    );

                    // Guardar el registro en la base de datos (tabla contenedor_consolidado_facturas_e)
                    $facturaComercial = FacturaComercial::create([
                        'quotation_id' => $idCotizacion,
                        'file_name' => $originalName,
                        'file_path' => $storedPath,
                        'size' => $fileSize,
                        'mime_type' => $mimeType,
                    ]);

                    $uploadedFiles[] = [
                        'id' => $facturaComercial->id,
                        'file_name' => $originalName,
                        'file_path' => $storedPath,
                        'size' => $fileSize,
                        'mime_type' => $mimeType,
                    ];

                    // Mantener compatibilidad: actualizar el campo factura_comercial en la cotización
                    // con el último archivo subido (para no romper funcionalidad existente)
                    $cotizacion->save();

                } catch (\Exception $e) {
                    $errors[] = 'Error al subir ' . ($file ? $file->getClientOriginalName() : 'archivo') . ': ' . $e->getMessage();
                    Log::error('Error al subir factura comercial individual', [
                        'quotation_id' => $idCotizacion,
                        'file' => $file ? $file->getClientOriginalName() : 'desconocido',
                        'error' => $e->getMessage()
                    ]);
                }
            }

            if (count($uploadedFiles) === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo subir ningún archivo',
                    'errors' => $errors
                ], 500);
            }

            $message = count($uploadedFiles) === 1 
                ? 'Factura comercial subida correctamente'
                : count($uploadedFiles) . ' facturas comerciales subidas correctamente';

            $response = [
                'success' => true,
                'message' => $message,
                'files' => $uploadedFiles,
                'count' => count($uploadedFiles)
            ];

            if (count($errors) > 0) {
                $response['warnings'] = $errors;
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Error al subir facturas comerciales', [
                'quotation_id' => $request->idCotizacion,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al subir facturas comerciales: ' . $e->getMessage()
            ], 500);
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
     *     path="/carga-consolidada/contenedor/factura-guia/general/delete-factura-comercial/{idFactura}",
     *     tags={"Factura y Guía"},
     *     summary="Eliminar factura comercial",
     *     description="Elimina una factura comercial específica por su ID",
     *     operationId="deleteFacturaComercialFG",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idFactura", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Factura eliminada exitosamente"),
     *     @OA\Response(response=404, description="Factura no encontrada")
     * )
     */
    public function deleteFacturaComercial($idFactura)
    {
        try {
            $facturaComercial = FacturaComercial::find($idFactura);
            
            if (!$facturaComercial) {
                //find factura comercial by id_cotizacion in table contenedor_consolidado_cotizacion
                $facturaComercial = Cotizacion::find($idFactura)->factura_comercial;
                if (!$facturaComercial) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Factura comercial no encontrada'
                    ], 404);
                }else{
                    unlink($this->generateImageUrl($facturaComercial));
                    Cotizacion::find($idFactura)->factura_comercial = null;
                    Cotizacion::find($idFactura)->save();
                    return response()->json([
                        'success' => true,
                        'message' => 'Factura comercial eliminada correctamente'
                    ]);
                }
            }

            // Eliminar el archivo físico
            $filePath = storage_path('app/' . $facturaComercial->file_path);
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // Eliminar el registro de la base de datos
            $facturaComercial->delete();

            // Verificar si quedan más facturas para esta cotización
            $cotizacion = Cotizacion::find($facturaComercial->quotation_id);
            $facturasRestantes = FacturaComercial::where('quotation_id', $facturaComercial->id_cotizacion)->count();
            
            // Si no quedan facturas, limpiar el campo legacy en la cotización
            if ($cotizacion && $facturasRestantes === 0) {
                $cotizacion->factura_comercial = null;
                $cotizacion->save();
            } elseif ($cotizacion && $facturasRestantes > 0) {
                // Si quedan facturas, actualizar con la última factura (más reciente)
                $ultimaFactura = FacturaComercial::where('quotation_id', $facturaComercial->id_cotizacion)
                    ->orderBy('created_at', 'desc')
                    ->first();
                if ($ultimaFactura) {
                    $cotizacion->factura_comercial = $ultimaFactura->nombre_archivo;
                    $cotizacion->save();
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Factura comercial eliminada correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar factura comercial', [
                'id_factura' => $idFactura,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar factura comercial: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/carga-consolidada/contenedor/factura-guia/general/get-facturas-comerciales/{idCotizacion}",
     *     tags={"Factura y Guía"},
     *     summary="Obtener facturas comerciales",
     *     description="Obtiene todas las facturas comerciales de una cotización",
     *     operationId="getFacturasComerciales",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idCotizacion", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Facturas obtenidas exitosamente")
     * )
     */
    public function getFacturasComerciales($idCotizacion)
    {
        try {
            $facturas = FacturaComercial::where('quotation_id', $idCotizacion)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $facturas,
                'count' => $facturas->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener facturas comerciales', [
                'id_cotizacion' => $idCotizacion,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener facturas comerciales: ' . $e->getMessage()
            ], 500);
        }
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
