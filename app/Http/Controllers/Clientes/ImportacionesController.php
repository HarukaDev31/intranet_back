<?php

namespace App\Http\Controllers\Clientes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\Cotizacion;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Models\CargaConsolidada\AlmacenInspection;
use App\Models\CargaConsolidada\Pago;

class ImportacionesController extends Controller
{
    private $pasoCompleted = 'COMPLETADO';
    private $pasoPending = 'PENDIENTE';
    private $pasosSeguimiento;

    public function __construct()
    {
        $this->pasosSeguimiento = [
            [
                'key' => 'carga_recibida',
                'name' => 'Carga Recibida',
                'status' => $this->pasoPending,
                'date' => '-',
                'description' => 'La carga ha sido recibida en el almacén'
            ],
            [
                'key' => 'llenado_de_contenedor',
                'name' => 'LLenado de contenedor',
                'status' => $this->pasoPending,
                'date' => '-',
                'description' => 'Almacen Yiwu'
            ],
            [
                'key' => 'zarpe',
                'name' => 'Zarpe',
                'status' => $this->pasoPending,
                'date' => '-',
                'description' => 'La carga ha sido recibida en el almacén'
            ],
            [
                'key' => 'en_trayecto',
                'name' => 'En Trayecto',
                'status' => $this->pasoPending,
                'date' => '-',
                'description' => 'La carga ha sido recibida en el almacén'
            ],
            [
                'key' => 'declaracion_aduanera',
                'name' => 'Declaracion aduanera',
                'status' => $this->pasoPending,
                'description' => 'La carga ha sido recibida en el almacén'
            ],
            [
                'key' => 'arribo',
                'name' => 'Arribo',
                'status' => $this->pasoPending,
                'date' => '-',
                'description' => 'La carga ha sido recibida en el almacén'
            ],

            [
                'key' => 'levante',
                'name' => 'Levante',
                'status' => $this->pasoPending,
                'date' => '-',
                'description' => 'La carga ha sido recibida en el almacén'
            ],
            [
                'key' => 'pago',
                'name' => 'Pago',
                'status' => $this->pasoPending,
                'date' => '-',
                'description' => 'La carga ha sido recibida en el almacén'
            ],
            [
                'key' => 'entregado',
                'name' => 'Entregado',
                'status' => $this->pasoPending,
                'date' => '-',
                'description' => 'La carga ha sido recibida en el almacén'
            ]
        ];
    }
    public function getTrayectos(Request $request)
    {
        try {
            //get current user whatsapp number from jwt
            $user = JWTAuth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 401);
            }
            $whatsapp = $user->whatsapp;
            $documento = $user->dni;
            $correo = $user->email;
            
            // Limpiar whatsapp para búsqueda (remover espacios, guiones, etc)
            $cleanWhatsapp = preg_replace('/[\s\-\(\)\.\+]/', '', trim($whatsapp));
            //if lenght is 9 remove 51 to cleanWhatsapp
            if (strlen($cleanWhatsapp) == 9) {
                $cleanWhatsapp = preg_replace('/^51/', '', $cleanWhatsapp);
            }
           
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);

            $trayectos = Cotizacion::with(['contenedor' => function ($query) {
                $query->select('id', 'carga', 'f_puerto', 'f_entrega', 'f_cierre');
            }])
                ->with(['proveedores' => function ($query) {
                    $query->select('id_cotizacion', 'cbm_total', 'qty_box', 'qty_box_china', 'cbm_total_china', 'estados_proveedor');
                }])
                ->where('estado_cotizador', 'CONFIRMADO')
                ->whereNotNull('estado_cliente')
                ->whereNull('id_cliente_importacion')
                ->whereHas('contenedor', function ($query) {
                    $query->whereRaw('CAST(carga AS UNSIGNED) >= 13');
                })
                ->whereHas('proveedores', function ($query) {
                    $query->where(DB::raw('(SELECT COUNT(*) FROM contenedor_consolidado_cotizacion_proveedores WHERE id_cotizacion = id)'), '>', 0);
                })
                ->where(function ($query) use ($cleanWhatsapp, $documento, $correo) {
                    // Usar la misma validación del modelo Cliente (getServiciosAttribute)
                    Log::info('CleanWhatsapp: ' . $cleanWhatsapp);
                    Log::info('Documento: ' . $documento);
                    Log::info('Correo: ' . $correo);
                    // Validar que el teléfono no sea nulo o vacío antes de procesar
                    if (!empty($cleanWhatsapp) && $cleanWhatsapp !== null) {
                        $query->where(DB::raw('REPLACE(TRIM(telefono), " ", "")'), 'LIKE', "%{$cleanWhatsapp}%");
                    }

                  
                    if (!empty($documento) && $documento !== null) {
                        $query->orWhere(function ($q) use ($documento) {
                            $q->whereNotNull('documento')
                                ->where('documento', '!=', '')
                                ->where('documento', $documento);
                        });
                    }

                    // Validar que el correo no sea nulo o vacío antes de procesar
                    if (!empty($correo) && $correo !== null) {
                        $query->orWhere(function ($q) use ($correo) {
                            $q->whereNotNull('correo')
                                ->where('correo', '!=', '')
                                ->where('correo', $correo);
                        });
                    }
                })
                //a where carga >11
                //where not has any row in consolidado_delivery_form_lima_conformidad or consolidado_delivery_form_provincia_conformidad with id_cotizacion
                ->where(DB::raw('(SELECT COUNT(*) FROM consolidado_delivery_form_lima_conformidad WHERE id_cotizacion = id)'), 0)
                ->where(DB::raw('(SELECT COUNT(*) FROM consolidado_delivery_form_province_conformidad WHERE id_cotizacion = id)'), 0)
                ->select('id', 'id_contenedor', 'qty_item', 'volumen_final', 'volumen', 'fob_final', 'logistica_final', 'fob', 'monto', 'estado_cliente', 'uuid', 'impuestos_final', 'impuestos')
                ->orderBy('id', 'desc')
                ->paginate($perPage);

            // Transformar los datos para incluir la información del contenedor
            $trayectosData = $trayectos->getCollection()->map(function ($cotizacion) {

                return [
                    'id' => $cotizacion->uuid,
                    'id_contenedor' => $cotizacion->id_contenedor,
                    'carga' => $cotizacion->contenedor ? $cotizacion->contenedor->carga : null,
                    'fecha_cierre' => $cotizacion->contenedor ? $cotizacion->contenedor->f_cierre : null,
                    'fecha_arribo' => $cotizacion->contenedor ? $cotizacion->contenedor->f_puerto : null,
                    'fecha_entrega' => $cotizacion->contenedor ? $cotizacion->contenedor->f_entrega : null,
                    'qty_box' => $cotizacion->getSumQtyBoxChinaAttribute(),
                    'cbm' => $cotizacion->getSumVolumeFinalAttribute(),
                    'fob' => $cotizacion->fob_final==0||$cotizacion->fob_final==null ? $cotizacion->fob : $cotizacion->fob_final,
                    'logistica' => $cotizacion->logistica_final==0||$cotizacion->logistica_final==null ? $cotizacion->monto : $cotizacion->logistica_final,
                    'impuestos' => $cotizacion->impuestos_final==0||$cotizacion->impuestos_final==null ? $cotizacion->impuestos : $cotizacion->impuestos_final,
                    'estado_cliente' => $cotizacion->estado_cliente,
                    'seguimiento' => null, // Agregar lógica según tu modelo
                    'inspecciones' => null // Agregar lógica según tu modelo
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $trayectosData,
                'pagination' => [
                    'total' => $trayectos->total(),
                    'per_page' => $trayectos->perPage(),
                    'current_page' => $trayectos->currentPage(),
                    'last_page' => $trayectos->lastPage(),
                    'from' => $trayectos->firstItem(),
                    'to' => $trayectos->lastItem()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener trayectos: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }
    public function getEntregados(Request $request)
    {
        try {
            //get current user whatsapp number from jwt
            $user = JWTAuth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 401);
            }
            $whatsapp = $user->whatsapp;
            $documento = $user->dni;
            $correo = $user->email;
            
            // Limpiar whatsapp para búsqueda (remover espacios, guiones, etc)
            $cleanWhatsapp = preg_replace('/[\s\-\(\)\.\+]/', '', trim($whatsapp));
            //if lenght is 9 remove 51 to cleanWhatsapp
            if (strlen($cleanWhatsapp) == 9) {
                $cleanWhatsapp = preg_replace('/^51/', '', $cleanWhatsapp);
            }
            Log::info('Whatsapp original: ' . $whatsapp);
            Log::info('Whatsapp limpio: ' . $cleanWhatsapp);
            
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);

            $trayectos = Cotizacion::with(['contenedor' => function ($query) {
                $query->select('id', 'carga', 'fecha_arribo', 'f_entrega', 'f_cierre', 'f_puerto');
            }])
                ->with(['proveedores' => function ($query) {
                    $query->select('id_cotizacion', 'cbm_total', 'qty_box', 'qty_box_china', 'cbm_total_china', 'estados_proveedor');
                }])
                ->where('estado_cotizador', 'CONFIRMADO')
                ->whereNotNull('estado_cliente')
                ->whereNull('id_cliente_importacion')
                ->where(function ($query) use ($cleanWhatsapp, $documento, $correo) {
                    if (!empty($cleanWhatsapp) && $cleanWhatsapp !== null) {
                        $query->where(DB::raw('REPLACE(TRIM(telefono), " ", "")'), 'LIKE', "%{$cleanWhatsapp}%");
                    }
                    if (!empty($documento) && $documento !== null) {
                        $query->orWhere(function ($q) use ($documento) {
                            $q->whereNotNull('documento')
                                ->where('documento', '!=', '')
                                ->where('documento', $documento);
                        });
                    }
                    if (!empty($correo) && $correo !== null) {
                        $query->orWhere(function ($q) use ($correo) {
                            $q->whereNotNull('correo')
                                ->where('correo', '!=', '')
                                ->where('correo', $correo);
                        });
                    }
                })
                ->where(function ($query) {
                    $query->where(function ($q) {
                        // Condición 1: Tiene conformidades de Lima y Provincia
                        $q->where(DB::raw('(SELECT COUNT(*) FROM consolidado_delivery_form_lima_conformidad WHERE id_cotizacion = id)'), '>', 0)
                          ->where(DB::raw('(SELECT COUNT(*) FROM consolidado_delivery_form_province_conformidad WHERE id_cotizacion = id)'), '>', 0);
                    })
                    ->orWhereHas('contenedor', function ($q) {
                        // Condición 2: La carga es menor a 13
                        $q->whereRaw('CAST(carga AS UNSIGNED) < 13');
                    });
                })
                ->select('id', 'id_contenedor', 'qty_item', 'volumen_final', 'fob_final', 'logistica_final', 'fob', 'monto', 'estado_cliente', 'uuid', 'impuestos_final', 'impuestos')
                ->orderBy('id', 'desc')
                ->paginate($perPage);
            $trayectosData = $trayectos->getCollection()->map(function ($cotizacion) {
                return [
                    'id' => $cotizacion->uuid,
                    'id_contenedor' => $cotizacion->id_contenedor,
                    'carga' => $cotizacion->contenedor ? $cotizacion->contenedor->carga : null,
                    'fecha_cierre' => $cotizacion->contenedor ? $cotizacion->contenedor->f_cierre : null,
                    'fecha_arribo' => $cotizacion->contenedor ? $cotizacion->contenedor->f_puerto : null,
                    'fecha_entrega' => $cotizacion->contenedor ? $cotizacion->contenedor->f_entrega : null,
                    'qty_box' => $cotizacion->getSumQtyBoxChinaAttribute(),
                    'cbm' => $cotizacion->getSumVolumeFinalAttribute(),
                    'fob' => $cotizacion->fob_final ?? $cotizacion->fob,
                    'logistica' => $cotizacion->logistica_final ?? $cotizacion->monto,
                    'impuestos' => $cotizacion->impuestos_final ?? $cotizacion->impuestos,
                    'estado_cliente' => $cotizacion->estado_cliente,
                    'seguimiento' => null, // Agregar lógica según tu modelo
                    'inspecciones' => null // Agregar lógica según tu modelo
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $trayectosData,
                'pagination' => [
                    'total' => $trayectos->total(),
                    'per_page' => $trayectos->perPage(),
                    'current_page' => $trayectos->currentPage(),
                    'last_page' => $trayectos->lastPage(),
                    'from' => $trayectos->firstItem(),
                    'to' => $trayectos->lastItem()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener trayectos: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }
    public function getInspecciones(Request $request, $uuid)
    {
        try {
            $user = JWTAuth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 401);
            }

            $whatsapp = $user->whatsapp;
            
            // Limpiar whatsapp para búsqueda (remover espacios, guiones, etc)
            $cleanWhatsapp = preg_replace('/[\s\-\(\)\.\+]/', '', trim($whatsapp));

            // Validar que la cotización pertenece al cliente actual
            $cotizacion = DB::table('contenedor_consolidado_cotizacion as main')
                ->select([
                    'main.*',
                    DB::raw("(
                        SELECT JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'id', docs.id,
                                'file_url', docs.file_url,
                                'folder_name', docs.name,
                                'id_proveedor', docs.id_proveedor
                            )
                        )
                        FROM contenedor_consolidado_cotizacion_documentacion docs
                        WHERE docs.id_cotizacion = main.id
                    ) as files"),
                    DB::raw("(
                        SELECT JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'id', almacen_docs.id,
                                'file_url', almacen_docs.file_path,
                                'folder_name', almacen_docs.file_name,
                                'file_name', almacen_docs.file_name,
                                'id_proveedor', almacen_docs.id_proveedor,
                                'file_ext', almacen_docs.file_ext
                            )
                        )
                        FROM contenedor_consolidado_almacen_documentacion almacen_docs
                        WHERE almacen_docs.id_cotizacion = main.id
                    ) as files_almacen_documentacion"),
                    DB::raw("(
                        SELECT JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'code_supplier', prov.code_supplier,
                                'id', prov.id,
                                'volumen_doc', prov.volumen_doc,
                                'valor_doc', prov.valor_doc,
                                'factura_comercial', prov.factura_comercial,
                                'excel_confirmacion', prov.excel_confirmacion,
                                'packing_list', prov.packing_list
                            )
                        )
                        FROM contenedor_consolidado_cotizacion_proveedores prov
                        WHERE prov.id_cotizacion = main.id
                    ) as providers"),
                    DB::raw("(
                        SELECT JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'id', inspection_docs.id,
                                'file_url', inspection_docs.file_path,
                                'file_name', inspection_docs.file_name,
                                'id_proveedor', inspection_docs.id_proveedor,
                                'file_ext', inspection_docs.file_type
                            )
                        )
                        FROM contenedor_consolidado_almacen_inspection inspection_docs
                        WHERE inspection_docs.id_cotizacion = main.id
                    ) as files_almacen_inspection")
                ])
                ->where('main.uuid', $uuid) // Validar ID específico
                ->where(DB::raw('REPLACE(TRIM(main.telefono), " ", "")'), 'like', '%' . $cleanWhatsapp . '%') // Validar que pertenece al cliente
                ->where('main.estado_cotizador', 'CONFIRMADO')
                ->whereNull('main.id_cliente_importacion')
                ->whereNotNull('main.estado_cliente')
                ->first();

            // Si no encuentra la cotización, significa que no pertenece al cliente o no existe
            if (!$cotizacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cotización no encontrada o no autorizada',
                    'data' => null
                ], 404);
            }

            // Decodificar los JSON arrays
            $cotizacion->files = json_decode($cotizacion->files, true) ?? [];
            $cotizacion->files_almacen_documentacion = json_decode($cotizacion->files_almacen_documentacion, true) ?? [];
            $cotizacion->providers = json_decode($cotizacion->providers, true) ?? [];
            $cotizacion->files_almacen_inspection = json_decode($cotizacion->files_almacen_inspection, true) ?? [];

            return response()->json([
                'success' => true,
                'data' => $cotizacion
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener inspecciones: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
    public function getSeguimiento(Request $request, $uuid)
    {
        try {
            $user = JWTAuth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo obtener el usuario'
                ], 401);
            }
            //get contenedor from id_cotizacion with uuid
            $idContenedor = Cotizacion::where('uuid', $uuid)->first()->id_contenedor;
            $contenedor = Contenedor::where('id', $idContenedor)->first();
            $cotizacion = Cotizacion::where('uuid', $uuid)->first();
            $idCotizacion = $cotizacion->id;
            $hasInspection = CotizacionProveedor::where('id_cotizacion', $idCotizacion)->whereHas('inspectionAlmacen')->exists();
            if ($hasInspection) {
                //get gile with min last_modified_at
                $file = AlmacenInspection::where('id_cotizacion', $idCotizacion)->orderBy('last_modified', 'asc')->first();
                $this->pasosSeguimiento[0]['status'] = $this->pasoCompleted;
                $this->pasosSeguimiento[0]['date'] = $file->last_modified;
                //description is Inspeccion en Yiwu \n Cajas is qty_box_china and volumen_doc from provideer 
                // sum of qty_box_china and cbm_total_china and valor_doc from provideer
                $this->pasosSeguimiento[0]['description'] = "Inspeccion en Yiwu \n Cajas: " . $cotizacion->getSumQtyBoxChinaAttribute();
                $maxVol = max($cotizacion->getSumCbmTotalChinaAttribute(), $cotizacion->getSumVolumeDocAttribute());
                $this->pasosSeguimiento[0]['description'] .= " \n Volumen: " . $maxVol . " m3";
            }
            if ($contenedor->lista_embarque_url) {
                $this->pasosSeguimiento[1]['status'] = $this->pasoCompleted;
                $this->pasosSeguimiento[1]['date'] = $contenedor->lista_embarque_uploaded_at;
            }
            //if contenedor has fecha_zarpe
            if ($contenedor->fecha_zarpe) {
                //if current date > fecha_zarpe
                if (date('Y-m-d') >= $contenedor->fecha_zarpe) {
                    $this->pasosSeguimiento[2]['status'] = $this->pasoCompleted;
                }
                $this->pasosSeguimiento[2]['date'] = $contenedor->fecha_zarpe;
                //tambien if current date > fecha_zarpe sum 2 days
                if (date('Y-m-d') >= date('Y-m-d', strtotime($contenedor->fecha_zarpe . ' + 2 days'))) {
                    $this->pasosSeguimiento[3]['status'] = $this->pasoCompleted;
                    $this->pasosSeguimiento[3]['date'] = date('Y-m-d', strtotime($contenedor->fecha_zarpe . ' + 2 days'));
                    $this->pasosSeguimiento[3]['description'] = "El contenedor va camino al puerto del Callao-Peru";
                }
                //description is naviera: $contenedor->naviera
                $this->pasosSeguimiento[2]['description'] = "Naviera: " . $contenedor->naviera;
            }
            if ($contenedor->fecha_declaracion) {
                if (date('Y-m-d') >= $contenedor->fecha_declaracion) {
                    $this->pasosSeguimiento[4]['status'] = $this->pasoCompleted;
                }
                $this->pasosSeguimiento[4]['date'] = $contenedor->fecha_declaracion;
                //description canal control: $contenedor->canal_control
                $this->pasosSeguimiento[4]['description'] = "Canal control: " . $contenedor->canal_control . " Esperando Levante";
            }
            if ($contenedor->fecha_arribo) {
                if (date('Y-m-d') >= $contenedor->fecha_arribo) {
                    $this->pasosSeguimiento[5]['status'] = $this->pasoCompleted;
                }
                $this->pasosSeguimiento[5]['date'] = $contenedor->fecha_arribo;
                $this->pasosSeguimiento[5]['description'] = "El contenedor ha llegado al puerto del Callao-Peru";
            }
            if ($contenedor->fecha_levante) {
                if (date('Y-m-d') >= $contenedor->fecha_levante) {
                    $this->pasosSeguimiento[6]['status'] = $this->pasoCompleted;
                }
                $this->pasosSeguimiento[6]['date'] = $contenedor->fecha_levante;
                $this->pasosSeguimiento[6]['description'] = "Podemos retirar el contenedor de aduanas.";
            }
            //for pagos sum all pagos for this cotizacion and if sum >logistica_final + impuestos_final then pago is completed and date is last payment date from file

            $pagos = Pago::where('id_cotizacion', $idCotizacion)->get();
            if ($pagos->count() > 0) {
                $totalPagos = $pagos->sum('monto');
                if ($totalPagos >= $cotizacion->logistica_final + $cotizacion->impuestos_final) {
                    $this->pasosSeguimiento[7]['status'] = $this->pasoCompleted;
                    if ($pagos->where('status', '!=', 'CONFIRMADO')->count() > 0) {
                        $this->pasosSeguimiento[7]['description'] = "Tu pago ha sido recibido, pero aún no ha sido confirmado";
                        $this->pasosSeguimiento[7]['date'] = $pagos->last()->payment_date;
                    } else {
                        $this->pasosSeguimiento[7]['description'] = "Tu pago ha sido confirmado exitosamente";
                        //confirm date is last payment date from file
                        $this->pasosSeguimiento[7]['date'] = $pagos->last()->confirmation_date ?? $pagos->last()->payment_date;
                    }
                }
            }
            //FIRST CHECK IF container->carga to int >11
            if ($contenedor->carga < 11) {
                $this->pasosSeguimiento[8]['status'] = $this->pasoCompleted;
                $this->pasosSeguimiento[8]['date'] = $contenedor->created_at;
            } else {
                //check if exists any row in consolidado_delivery_form_lima_conformidad or consolidado_delivery_form_provincia_conformidad with id_cotizacion
                $deliveryFormLimaConformidad = DB::table('consolidado_delivery_form_lima_conformidad')->where('id_cotizacion', $idCotizacion)->first();
                $deliveryFormProvinciaConformidad = DB::table('consolidado_delivery_form_province_conformidad')->where('id_cotizacion', $idCotizacion)->first();
                if ($deliveryFormLimaConformidad) {
                    $this->pasosSeguimiento[8]['status'] = $this->pasoCompleted;
                    $this->pasosSeguimiento[8]['date'] = $deliveryFormLimaConformidad->created_at;
                }
                if ($deliveryFormProvinciaConformidad) {
                    $this->pasosSeguimiento[8]['status'] = $this->pasoCompleted;
                    $this->pasosSeguimiento[8]['date'] = $deliveryFormProvinciaConformidad->fecha_conformidad;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $this->pasosSeguimiento,
                'carga' => $contenedor->carga
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener seguimiento: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
