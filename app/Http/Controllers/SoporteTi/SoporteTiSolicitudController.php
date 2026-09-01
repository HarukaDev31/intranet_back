<?php

namespace App\Http\Controllers\SoporteTi;

use App\Http\Controllers\Controller;
use App\Services\SoporteTi\SoporteTiService;
use App\Support\SoporteTi\RespondsSoporteTiJson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SoporteTiSolicitudController extends Controller
{
    use RespondsSoporteTiJson;

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
        } catch (\Throwable $e) {
            return $this->soporteTiFail($e);
        }
    }

    public function listarCreadores(Request $request)
    {
        try {
            return $this->soporteTiOk($this->service->listarCreadoresFiltro($request->all(), Auth::user()));
        } catch (\Throwable $e) {
            return $this->soporteTiFail($e);
        }
    }

    public function show($id)
    {
        try {
            return $this->soporteTiOk($this->service->obtenerSolicitud($id, Auth::user()));
        } catch (\Throwable $e) {
            return $this->soporteTiFail($e);
        }
    }

    public function destroy($id)
    {
        try {
            $this->service->eliminarSolicitud($id, Auth::user());

            return $this->soporteTiOk(null, 'Solicitud eliminada');
        } catch (\Throwable $e) {
            return $this->soporteTiFail($e);
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
            'imagenes.*' => 'file|mimes:jpg,jpeg,png,gif,webp,bmp|max:10240',
        ));

        $payload = $request->except('imagenes');
        $imagenes = $this->extraerImagenesRequest($request);

        if (isset($payload['tipo_solicitud']) && $payload['tipo_solicitud'] === 'A') {
            unset($payload['seccion_ruta']);
            $imagenes = array();
        }

        try {
            $data = $this->service->crearSolicitud($payload, Auth::user(), $imagenes);

            return $this->soporteTiOk($data, null, 201);
        } catch (\Throwable $e) {
            return $this->soporteTiFail($e);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            return $this->soporteTiOk(
                $this->service->actualizarSolicitud($id, $request->all(), Auth::user())
            );
        } catch (\Throwable $e) {
            return $this->soporteTiFail($e);
        }
    }

    public function postMensaje(Request $request, $id)
    {
        $request->validate(array(
            'texto' => 'nullable|string',
            'reply_to_id' => 'nullable|integer',
            'imagenes' => 'nullable|array',
            'imagenes.*' => 'file|mimes:jpg,jpeg,png,gif,webp,bmp|max:10240',
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

            return $this->soporteTiOk($mensaje);
        } catch (\Throwable $e) {
            return $this->soporteTiFail($e);
        }
    }

    public function postMaqueta(Request $request, $id)
    {
        $request->validate(array(
            'archivo' => 'required|file|mimes:jpg,jpeg,png,gif,webp,bmp,pdf|max:20480',
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

            return $this->soporteTiOk($data);
        } catch (\Throwable $e) {
            return $this->soporteTiFail($e);
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

            return $this->soporteTiOk($data);
        } catch (\Throwable $e) {
            return $this->soporteTiFail($e);
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

            return $this->soporteTiOk($data);
        } catch (\Throwable $e) {
            return $this->soporteTiFail($e);
        }
    }

    public function actualizarAsignacion(Request $request, $id)
    {
        $request->validate(array(
            'pm_user_id' => 'nullable|integer|min:1',
            'analista_user_id' => 'nullable|integer|min:1',
        ));

        try {
            $data = $this->service->actualizarAsignacion(
                $id,
                $request->only(array('pm_user_id', 'analista_user_id')),
                Auth::user()
            );

            return $this->soporteTiOk($data, 'Asignación actualizada.');
        } catch (\Throwable $e) {
            return $this->soporteTiFail($e);
        }
    }

    public function actualizarEstado(Request $request, $id)
    {
        return $this->responderActualizacionEstado($request, $id);
    }

    public function historialEstados($id)
    {
        try {
            return $this->soporteTiOk($this->service->historialEstados($id, Auth::user()));
        } catch (\Throwable $e) {
            return $this->soporteTiFail($e);
        }
    }

    public function listarStaff()
    {
        try {
            return $this->soporteTiOk($this->service->listarStaffAsignable(Auth::user()));
        } catch (\Throwable $e) {
            return $this->soporteTiFail($e);
        }
    }

    /**
     * PATCH/POST estado: una sola respuesta; el servicio resuelve por código o por id.
     *
     * @param Request $request
     * @param int|string $id
     * @return \Illuminate\Http\JsonResponse
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

            return $this->soporteTiOk($data);
        } catch (\Throwable $e) {
            return $this->soporteTiFail($e);
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
