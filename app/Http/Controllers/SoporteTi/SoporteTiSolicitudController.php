<?php

namespace App\Http\Controllers\SoporteTi;

use App\Http\Controllers\Controller;
use App\Services\SoporteTi\SoporteTiService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SoporteTiSolicitudController extends Controller
{
    /** @var SoporteTiService */
    protected $service;

    public function __construct(SoporteTiService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        try {
            $payload = $this->service->listarSolicitudes($request->all(), Auth::user());
            return response()->json(array(
                'success' => true,
                'data' => $payload['solicitudes'],
                'resumen' => $payload['resumen'],
            ));
        } catch (\Exception $e) {
            return response()->json(array('success' => false, 'message' => $e->getMessage()), 500);
        }
    }

    public function show($id)
    {
        try {
            $data = $this->service->obtenerSolicitud($id, Auth::user());
            return response()->json(array('success' => true, 'data' => $data));
        } catch (ModelNotFoundException $e) {
            return response()->json(array('success' => false, 'message' => 'No encontrado'), 404);
        } catch (AuthorizationException $e) {
            return response()->json(
                array('success' => false, 'message' => $e->getMessage() ?: 'No autorizado'),
                403
            );
        } catch (\Exception $e) {
            return response()->json(array('success' => false, 'message' => $e->getMessage()), 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate(array(
            'tipo_solicitud' => 'required|in:A,B',
            'subtipo_b' => 'nullable|in:B1,B2',
            'titulo' => 'nullable|string|max:255',
            'area' => 'required|string|max:80',
            'seccion_ruta' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string',
            'imagenes' => 'nullable|array',
            'imagenes.*' => 'file|max:10240',
        ));

        $imagenes = $this->extraerImagenesRequest($request);

        try {
            $data = $this->service->crearSolicitud($request->except('imagenes'), Auth::user(), $imagenes);
            return response()->json(array('success' => true, 'data' => $data), 201);
        } catch (\Exception $e) {
            return response()->json(array('success' => false, 'message' => $e->getMessage()), 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $data = $this->service->actualizarSolicitud($id, $request->all(), Auth::user());
            return response()->json(array('success' => true, 'data' => $data));
        } catch (\InvalidArgumentException $e) {
            return response()->json(array('success' => false, 'message' => $e->getMessage()), 422);
        } catch (\Exception $e) {
            return response()->json(array('success' => false, 'message' => $e->getMessage()), 500);
        }
    }

    public function postMensaje(Request $request, $id)
    {
        $request->validate(array(
            'texto' => 'nullable|string',
            'reply_to_id' => 'nullable|integer',
        ));

        $imagenes = $this->extraerImagenesRequest($request);

        try {
            $mensaje = $this->service->enviarMensaje(
                $id,
                $request->input('texto', ''),
                $request->input('reply_to_id'),
                $imagenes,
                Auth::user()
            );
            return response()->json(array('success' => true, 'data' => $mensaje));
        } catch (\Exception $e) {
            return response()->json(array('success' => false, 'message' => $e->getMessage()), 500);
        }
    }

    public function postMaqueta(Request $request, $id)
    {
        $request->validate(array(
            'archivo' => 'required|file|max:20480',
        ));

        $file = $request->file('archivo') ?: $request->file('maqueta');
        if (!$file) {
            return response()->json(array('success' => false, 'message' => 'Archivo requerido'), 422);
        }

        try {
            $data = $this->service->subirMaqueta(
                $id,
                $file,
                $request->input('mensaje'),
                Auth::user()
            );
            return response()->json(array('success' => true, 'data' => $data));
        } catch (\Exception $e) {
            return response()->json(array('success' => false, 'message' => $e->getMessage()), 500);
        }
    }

    public function cambiarEstado(Request $request, $id)
    {
        return $this->responderActualizacionEstado($request, $id);
    }

    public function actualizarPrioridad(Request $request, $id)
    {
        $request->validate(array(
            'prioridad' => 'required|integer|in:1,2,3',
        ));

        try {
            $data = $this->service->actualizarSolicitud(
                $id,
                array('prioridad' => (int) $request->input('prioridad')),
                Auth::user()
            );
            return response()->json(array('success' => true, 'data' => $data));
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(array('success' => false, 'message' => $e->getMessage()), 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(array('success' => false, 'message' => $e->getMessage()), 422);
        } catch (\Exception $e) {
            return response()->json(array('success' => false, 'message' => $e->getMessage()), 500);
        }
    }

    public function actualizarComplejidad(Request $request, $id)
    {
        $request->validate(array(
            'criticidad' => 'required|string|in:Baja,Media,Alta,Máxima',
        ));

        try {
            $data = $this->service->actualizarComplejidad(
                $id,
                $request->input('criticidad'),
                Auth::user()
            );
            return response()->json(array('success' => true, 'data' => $data));
        } catch (\InvalidArgumentException $e) {
            return response()->json(array('success' => false, 'message' => $e->getMessage()), 422);
        } catch (\Exception $e) {
            return response()->json(array('success' => false, 'message' => $e->getMessage()), 500);
        }
    }

    public function actualizarEstado(Request $request, $id)
    {
        return $this->responderActualizacionEstado($request, $id);
    }

    public function historialEstados($id)
    {
        try {
            $data = $this->service->historialEstados($id, Auth::user());
            return response()->json(array('success' => true, 'data' => $data));
        } catch (\Exception $e) {
            return response()->json(array('success' => false, 'message' => $e->getMessage()), 500);
        }
    }

    /**
     * PATCH/POST estado: una sola respuesta; el servicio resuelve por código o por id.
     *
     * @param Request $request
     * @param int|string $id
     * @return JsonResponse
     */
    protected function responderActualizacionEstado(Request $request, $id)
    {
        $request->validate(array(
            'estado_id' => 'required_without:estado_codigo|integer|min:1|max:255',
            'estado_codigo' => 'required_without:estado_id|string|max:64',
            'comentario' => 'nullable|string',
        ));

        try {
            if ($request->filled('estado_codigo')) {
                $data = $this->service->actualizarEstadoPorCodigo(
                    $id,
                    $request->input('estado_codigo'),
                    $request->input('comentario'),
                    Auth::user()
                );
            } else {
                $data = $this->service->actualizarEstado(
                    $id,
                    (int) $request->input('estado_id'),
                    $request->input('comentario'),
                    Auth::user()
                );
            }

            return response()->json(array('success' => true, 'data' => $data));
        } catch (\InvalidArgumentException $e) {
            return response()->json(array('success' => false, 'message' => $e->getMessage()), 422);
        } catch (\Exception $e) {
            return response()->json(array('success' => false, 'message' => $e->getMessage()), 500);
        }
    }

    /**
     * @param Request $request
     * @return array
     */
    protected function extraerImagenesRequest(Request $request)
    {
        $imagenes = array();
        if ($request->hasFile('imagenes')) {
            $files = $request->file('imagenes');
            if (is_array($files)) {
                foreach ($files as $f) {
                    if ($f) {
                        $imagenes[] = $f;
                    }
                }
            } else {
                $imagenes[] = $files;
            }
            return $imagenes;
        }

        foreach ($request->allFiles() as $key => $file) {
            if (strpos($key, 'imagenes') === 0 && $file) {
                if (is_array($file)) {
                    $imagenes = array_merge($imagenes, $file);
                } else {
                    $imagenes[] = $file;
                }
            }
        }

        return $imagenes;
    }
}
