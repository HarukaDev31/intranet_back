<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreViaticoRequest;
use App\Http\Requests\UpdateViaticoRequest;
use App\Services\ViaticoService;
use App\Models\Viatico;
use App\Models\Notificacion;
use App\Models\Usuario;
use App\Events\ViaticoCreado;
use App\Events\ViaticoActualizado;
use App\Jobs\SendViaticoWhatsappNotificationJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Traits\WhatsappTrait;

class ViaticoController extends Controller
{
    use WhatsappTrait;
    protected $viaticoService;

    public function __construct(ViaticoService $viaticoService)
    {
        $this->viaticoService = $viaticoService;
    }

    /**
     * Obtener lista de viáticos
     */
    public function index(Request $request)
    {
        try {
            $filtros = [
                'status' => $request->get('status'),
                'fecha_inicio' => $request->get('fecha_inicio'),
                'fecha_fin' => $request->get('fecha_fin'),
                'search' => $request->get('search'),
                'sort_by' => $request->get('sort_by', 'created_at'),
                'sort_order' => $request->get('sort_order', 'desc'),
                'requesting_area' => $request->get('requesting_area'),
            ];

            // Si no es administración, filtrar por usuario actual
            $user = auth()->user();
            $grupo = $user->grupo ?? null;
            if (!$grupo || $grupo->No_Grupo !== 'Administración') {
                $filtros['user_id'] = $user->ID_Usuario;
            }

            $query = $this->viaticoService->obtenerViaticos($filtros);

            // Paginación
            $perPage = $request->get('per_page', 10);
            $page = (int) $request->get('page', 1);
            $viaticos = $query->paginate($perPage, ['*'], 'page', $page);
            //foreach viatico complete pago.file_path
            foreach ($viaticos as $viatico) {
                foreach ($viatico->pagos as $pago) {
                    $pago->file_path = asset('storage/' . $pago->file_path);
                }
            }
            // Transformar datos
            $data = $viaticos->items();
            foreach ($data as $viatico) {
                $viatico->url_comprobante = $viatico->receipt_file
                    ? asset('storage/' . $viatico->receipt_file)
                    : null;
                $viatico->url_payment_receipt = $viatico->payment_receipt_file
                    ? asset('storage/' . $viatico->payment_receipt_file)
                    : null;
                $viatico->nombre_usuario = optional($viatico->usuario)->No_Nombres_Apellidos ?? 'N/A';
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $viaticos->currentPage(),
                    'last_page' => $viaticos->lastPage(),
                    'per_page' => $viaticos->perPage(),
                    'total' => $viaticos->total(),
                    'from' => $viaticos->firstItem(),
                    'to' => $viaticos->lastItem()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener viáticos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener viáticos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener viáticos pendientes (para administración)
     */
    public function pendientes(Request $request)
    {
        try {
            $request->merge(['status' => 'PENDING']);
            return $this->index($request);
        } catch (\Exception $e) {
            Log::error('Error al obtener viáticos pendientes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener viáticos pendientes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener viáticos completados (para administración)
     */
    public function completados(Request $request)
    {
        try {
            $request->merge(['status' => 'CONFIRMED']);
            return $this->index($request);
        } catch (\Exception $e) {
            Log::error('Error al obtener viáticos completados: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener viáticos completados: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extrae archivos de items del request y normaliza data.items (concepto, monto, id).
     */
    private function extraerItemFilesYNormalizarItems(Request $request, array &$data): array
    {
        $items = $data['items'] ?? [];
        $itemFiles = [];
        foreach ($items as $index => $item) {
            $itemFiles[$index] = isset($item['receipt_file']) && $item['receipt_file'] instanceof \Illuminate\Http\UploadedFile
                ? $item['receipt_file']
                : $request->file("items.{$index}.receipt_file");
            unset($data['items'][$index]['receipt_file']);
        }
        $data['items'] = array_values($items);
        return $itemFiles;
    }

    /**
     * Crear un nuevo viático
     */
    public function store(StoreViaticoRequest $request)
    {
        try {
            $data = $request->validated();
            $itemFiles = $this->extraerItemFilesYNormalizarItems($request, $data);
            $archivo = $request->hasFile('receipt_file')
                ? $request->file('receipt_file')
                : null;
            $idViatico = $request->id;
            $user = auth()->user();
            $userPhone = $user ? normalizePhone($user->Nu_Celular) : null;
            if (!$userPhone) {

                return response()->json([
                    'success' => false,
                    'message' => 'Debe tener un número de teléfono para crear una solicitud de reintegro'
                ], 400);
            }
            if ($idViatico) {
                // Obtener el modelo y validar permisos antes de delegar al servicio
                $viaticoModel = $this->viaticoService->obtenerViaticoPorId($idViatico);

                if (!$viaticoModel) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Viático no encontrado'
                    ], 404);
                }

                $user = auth()->user();
                $grupo = $user->grupo ?? null;
                $isAdmin = $grupo && $grupo->No_Grupo === 'Administración';

                if (!$isAdmin && $viaticoModel->user_id !== $user->ID_Usuario) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No tienes permiso para editar este viático'
                    ], 403);
                }

                $viatico = $this->viaticoService->usuarioActualizarViatico($viaticoModel, $data, $archivo, $itemFiles);
                return response()->json([
                    'success' => true,
                    'message' => 'Viático actualizado exitosamente',
                    'data' => $viatico
                ]);
            } else {
                $viatico = $this->viaticoService->crearViatico($data, $archivo, $itemFiles);

               
                $viatico->url_comprobante = $viatico->receipt_file
                    ? asset('storage/' . $viatico->receipt_file)
                    : null;
                $viatico->url_payment_receipt = $viatico->payment_receipt_file
                    ? asset('storage/' . $viatico->payment_receipt_file)
                    : null;
                $viatico->nombre_usuario = optional($viatico->usuario)->No_Nombres_Apellidos ?? 'N/A';

                return response()->json([
                    'success' => true,
                    'message' => 'Viático creado exitosamente',
                    'data' => $viatico
                ], 201);
            }
        } catch (\Exception $e) {
            Log::error('Error al crear viático: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear viático: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un viático por ID
     */
    public function show($id)
    {
        try {
            $viatico = $this->viaticoService->obtenerViaticoPorId($id);

            if (!$viatico) {
                return response()->json([
                    'success' => false,
                    'message' => 'Viático no encontrado'
                ], 404);
            }

            // Verificar permisos: solo el usuario o administración puede ver
            $user = auth()->user();
            $grupo = $user->grupo ?? null;
            $isAdmin = $grupo && $grupo->No_Grupo === 'Administración';

            if (!$isAdmin && $viatico->user_id !== $user->ID_Usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para ver este viático'
                ], 403);
            }

            $viatico->url_comprobante = $viatico->receipt_file
                ? asset('storage/' . $viatico->receipt_file)
                : null;
            $viatico->url_payment_receipt = $viatico->payment_receipt_file
                ? asset('storage/' . $viatico->payment_receipt_file)
                : null;
            $viatico->nombre_usuario = optional($viatico->usuario)->No_Nombres_Apellidos ?? 'N/A';
            foreach ($viatico->pagos as $pago) {
                $pago->file_path = asset('storage/' . $pago->file_path);
            }
            return response()->json([
                'success' => true,
                'data' => $viatico
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener viático: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener viático: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un viático
     */
    public function update(UpdateViaticoRequest $request, $id)
    {
        try {
            $viatico = $this->viaticoService->obtenerViaticoPorId($id);

            if (!$viatico) {
                return response()->json([
                    'success' => false,
                    'message' => 'Viático no encontrado'
                ], 404);
            }

            // Verificar permisos: solo administración puede editar
            $user = auth()->user();
            $grupo = $user->grupo ?? null;
            $isAdmin = $grupo && $grupo->No_Grupo === 'Administración';
            $idUsuario = $viatico->user_id;
            $usuarioCreador = Usuario::find($idUsuario);
            $userPhone = $usuarioCreador ? normalizePhone($usuarioCreador->Nu_Celular) : null;
            Log::info('Número de teléfono del usuario creador: ' . $userPhone);
            if (!$isAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para editar este viático'
                ], 403);
            }
            $data = $request->validated();
            $data['return_date'] = Carbon::now();
            $archivo = $request->hasFile('payment_receipt_file')
                ? $request->file('payment_receipt_file')
                : null;

            $itemFiles = [];
            if (!empty($data['items'])) {
                $itemFiles = $this->extraerItemFilesYNormalizarItems($request, $data);
            }

            // Si se envía delete_file, eliminar el archivo
            if ($request->has('delete_file') && $request->delete_file == true) {
                $data['delete_file'] = true;
            }

            $viatico = $this->viaticoService->actualizarViatico($viatico, $data, $archivo, $itemFiles);

            // Ruta relativa para el job (antes de sobrescribir con URL para la respuesta)
            $paymentReceiptPathForJob = $viatico->payment_receipt_file;

            // El servicio ya devuelve el viático con usuario cargado; no hacer refresh() ni load() de nuevo
            $viatico->url_comprobante = $viatico->receipt_file
                ? asset('storage/' . $viatico->receipt_file)
                : null;
            $viatico->payment_receipt_file = $viatico->payment_receipt_file
                ? asset('storage/' . $viatico->payment_receipt_file)
                : null;
            $viatico->nombre_usuario = optional($viatico->usuario)->No_Nombres_Apellidos ?? 'N/A';

            $usuarioAdministracion = auth()->user();
            $usuarioCreador = $viatico->usuario;

            if ($usuarioCreador && $usuarioCreador->ID_Usuario !== $usuarioAdministracion->ID_Usuario) {
                $estadoTexto = $viatico->status === Viatico::STATUS_CONFIRMED ? 'confirmado' : ($viatico->status === Viatico::STATUS_REJECTED ? 'rechazado' : 'actualizado');
                $message = "Administración ha {$estadoTexto} tu viático: {$viatico->subject}";

                try {
                    Notificacion::create([
                        'titulo' => "Viático {$estadoTexto}",
                        'mensaje' => $message,
                        'descripcion' => "Viático #{$viatico->id} | Asunto: {$viatico->subject} | Monto: S/ {$viatico->total_amount} | Estado: {$estadoTexto} | Actualizado por: {$usuarioAdministracion->No_Nombres_Apellidos}",
                        'modulo' => Notificacion::MODULO_ADMINISTRACION,
                        'usuario_destinatario' => $usuarioCreador->ID_Usuario,
                        'navigate_to' => 'viaticos',
                        'navigate_params' => json_encode(['id' => $viatico->id]),
                        'tipo' => $viatico->status === Viatico::STATUS_CONFIRMED ? Notificacion::TIPO_SUCCESS : ($viatico->status === Viatico::STATUS_REJECTED ? Notificacion::TIPO_ERROR : Notificacion::TIPO_INFO),
                        'icono' => $viatico->status === Viatico::STATUS_CONFIRMED ? 'mdi:check-circle' : ($viatico->status === Viatico::STATUS_REJECTED ? 'mdi:close-circle' : 'mdi:information'),
                        'prioridad' => Notificacion::PRIORIDAD_MEDIA,
                        'referencia_tipo' => 'viatico',
                        'referencia_id' => $viatico->id,
                        'activa' => true,
                        'creado_por' => $usuarioAdministracion->ID_Usuario,
                    ]);

                    // WhatsApp en segundo plano: pasar ruta relativa (viaticos/xxx.jpg), no la URL
                    $messageWhatsapp = "Administración ha efectuado el depósito de tu reintegro de viáticos por el monto de S/.{$viatico->total_amount} ";
                    SendViaticoWhatsappNotificationJob::dispatch(
                        $messageWhatsapp,
                        $usuarioCreador->ID_Usuario,
                        $paymentReceiptPathForJob,
                        $userPhone
                    )->afterResponse();

                    ViaticoActualizado::dispatch($viatico, $usuarioAdministracion, $usuarioCreador, $message);
                } catch (\Exception $e) {
                    Log::error('Error al crear notificación o disparar evento ViaticoActualizado: ' . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Viático actualizado exitosamente',
                'data' => $viatico
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar viático: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar viático: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un viático
     */
    public function destroy($id)
    {
        try {
            $viatico = $this->viaticoService->obtenerViaticoPorId($id);

            if (!$viatico) {
                return response()->json([
                    'success' => false,
                    'message' => 'Viático no encontrado'
                ], 404);
            }

            // Verificar permisos: administración o el usuario creador puede eliminar
            $user = auth()->user();
            $grupo = $user->grupo ?? null;
            $isAdmin = $grupo && $grupo->No_Grupo === 'Administración';

            if (!$isAdmin && $viatico->user_id !== $user->ID_Usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para eliminar este viático'
                ], 403);
            }

            $this->viaticoService->eliminarViatico($viatico);

            return response()->json([
                'success' => true,
                'message' => 'Viático eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar viático: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar viático: ' . $e->getMessage()
            ], 500);
        }
    }
}
