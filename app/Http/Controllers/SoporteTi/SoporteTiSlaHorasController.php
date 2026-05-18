<?php

namespace App\Http\Controllers\SoporteTi;

use App\Http\Controllers\Controller;
use App\Services\SoporteTi\SoporteTiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SoporteTiSlaHorasController extends Controller
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
            $tipo = strtoupper(trim((string) $request->query('tipo', 'B')));
            if (!in_array($tipo, array('A', 'B'), true)) {
                return response()->json(array('success' => false, 'message' => 'Tipo de solicitud no válido.'), 422);
            }

            $ambito = $request->query('ambito');
            $data = $this->service->listarSlaHoras($tipo, Auth::user(), $ambito);

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
            $tipo = strtoupper(trim((string) $request->input('tipo', $request->query('tipo', 'B'))));
            if (!in_array($tipo, array('A', 'B'), true)) {
                return response()->json(array('success' => false, 'message' => 'Tipo de solicitud no válido.'), 422);
            }

            $items = $request->input('horas', array());
            if (!is_array($items)) {
                return response()->json(array('success' => false, 'message' => 'Formato de horas inválido.'), 422);
            }

            $ambito = $request->input('ambito', $request->query('ambito'));
            $data = $this->service->actualizarSlaHoras($tipo, $items, Auth::user(), $ambito);

            return response()->json(array(
                'success' => true,
                'message' => 'Horas SLA actualizadas.',
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
