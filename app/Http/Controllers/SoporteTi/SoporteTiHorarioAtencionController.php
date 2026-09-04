<?php

namespace App\Http\Controllers\SoporteTi;

use App\Http\Controllers\Controller;
use App\Services\SoporteTi\SoporteTiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SoporteTiHorarioAtencionController extends Controller
{
    /** @var SoporteTiService */
    protected $service;

    public function __construct(SoporteTiService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        try {
            $data = $this->service->listarHorarioAtencion(Auth::user());

            return response()->json(array('success' => true, 'data' => $data));
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(array('success' => false, 'message' => $e->getMessage()), 403);
        } catch (\Exception $e) {
            return response()->json(array('success' => false, 'message' => $e->getMessage()), 500);
        }
    }

    public function update(Request $request)
    {
        try {
            $dias = $request->input('dias', array());
            if (!is_array($dias)) {
                return response()->json(array('success' => false, 'message' => 'Formato de días inválido.'), 422);
            }

            $data = $this->service->actualizarHorarioAtencion($dias, Auth::user());

            return response()->json(array(
                'success' => true,
                'message' => 'Horario de atención actualizado.',
                'data' => $data,
            ));
        } catch (\InvalidArgumentException $e) {
            return response()->json(array('success' => false, 'message' => $e->getMessage()), 422);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(array('success' => false, 'message' => $e->getMessage()), 403);
        } catch (\Exception $e) {
            return response()->json(array('success' => false, 'message' => $e->getMessage()), 500);
        }
    }
}
