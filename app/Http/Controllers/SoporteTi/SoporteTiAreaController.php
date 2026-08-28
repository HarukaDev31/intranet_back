<?php

namespace App\Http\Controllers\SoporteTi;

use App\Http\Controllers\Controller;
use App\Services\SoporteTi\SoporteTiAreaService;
use App\Support\SoporteTi\RespondsSoporteTiJson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SoporteTiAreaController extends Controller
{
    use RespondsSoporteTiJson;

    /** @var SoporteTiAreaService */
    protected $service;

    public function __construct(SoporteTiAreaService $service)
    {
        $this->service = $service;
    }

    public function catalogo()
    {
        try {
            return $this->soporteTiOk($this->service->catalogoParaCrear(Auth::user()));
        } catch (\Throwable $e) {
            return $this->soporteTiFail($e);
        }
    }

    public function index()
    {
        try {
            return $this->soporteTiOk($this->service->listarGestion(Auth::user()));
        } catch (\Throwable $e) {
            return $this->soporteTiFail($e);
        }
    }

    public function store(Request $request)
    {
        $request->validate(array(
            'nombre' => 'required|string|max:80',
            'orden' => 'nullable|integer|min:0|max:9999',
            'activo' => 'nullable|boolean',
            'grupo_ids' => 'nullable|array',
            'grupo_ids.*' => 'integer|min:1',
        ));

        try {
            $data = $this->service->crear($request->only(array('nombre', 'orden', 'activo', 'grupo_ids')), Auth::user());

            return $this->soporteTiOk($data, 'Área creada', 201);
        } catch (\Throwable $e) {
            return $this->soporteTiFail($e);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate(array(
            'nombre' => 'sometimes|required|string|max:80',
            'orden' => 'nullable|integer|min:0|max:9999',
            'activo' => 'nullable|boolean',
            'grupo_ids' => 'nullable|array',
            'grupo_ids.*' => 'integer|min:1',
        ));

        try {
            $data = $this->service->actualizar(
                $id,
                $request->only(array('nombre', 'orden', 'activo', 'grupo_ids')),
                Auth::user()
            );

            return $this->soporteTiOk($data, 'Área actualizada');
        } catch (\Throwable $e) {
            return $this->soporteTiFail($e);
        }
    }

    public function destroy($id)
    {
        try {
            $result = $this->service->eliminar($id, Auth::user());
            $msg = !empty($result['desactivada'])
                ? 'El área se usa en solicitudes existentes. Se desactivó en lugar de eliminarla.'
                : 'Área eliminada';

            return $this->soporteTiOk($result, $msg);
        } catch (\Throwable $e) {
            return $this->soporteTiFail($e);
        }
    }
}
