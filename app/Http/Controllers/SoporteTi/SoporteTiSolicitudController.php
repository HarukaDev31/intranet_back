<?php

namespace App\Http\Controllers\SoporteTi;

use App\Http\Controllers\Controller;
use App\Services\SoporteTi\SoporteTiService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
        }

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

        $imagenes = array();
        if ($request->hasFile('imagenes')) {
            $imagenes = $request->file('imagenes');
            if (!is_array($imagenes)) {
                $imagenes = array($imagenes);
            }
        } else {
            $all = $request->allFiles();
            foreach ($all as $key => $file) {
                if (strpos($key, 'imagenes') === 0 && $file) {
                    if (is_array($file)) {
                        $imagenes = array_merge($imagenes, $file);
                    } else {
                        $imagenes[] = $file;
                    }
                }
            }
        }

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
        $request->validate(array(
            'estado_id' => 'required|integer|min:1|max:255',
            'comentario' => 'nullable|string',
        ));

        try {
            $data = $this->service->cambiarEstado(
                $id,
                (int) $request->input('estado_id'),
                $request->input('comentario'),
                Auth::user()
            );
            return response()->json(array('success' => true, 'data' => $data));
        } catch (\Exception $e) {
            return response()->json(array('success' => false, 'message' => $e->getMessage()), 500);
        }
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
}
