<?php

namespace App\Http\Controllers\SoporteTi;

use App\Http\Controllers\Controller;
use App\Services\SoporteTi\SoporteTiService;
use App\Support\SoporteTi\RespondsSoporteTiJson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SoporteTiFaseHorasController extends Controller
{
    use RespondsSoporteTiJson;

    /** @var SoporteTiService */
    protected $service;

    public function __construct(SoporteTiService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        try {
            return $this->soporteTiOk($this->service->listarFaseHorasA(Auth::user()));
        } catch (\Throwable $e) {
            return $this->soporteTiFail($e);
        }
    }

    public function update(Request $request)
    {
        try {
            // Front envía `horas`; se acepta también `fases` por compatibilidad.
            $items = $request->input('horas', $request->input('fases', array()));
            if (!is_array($items)) {
                return response()->json(array('success' => false, 'message' => 'Formato inválido.'), 422);
            }
            $data = $this->service->actualizarFaseHorasA($items, Auth::user());

            return $this->soporteTiOk($data, 'Horas por fase actualizadas.');
        } catch (\Throwable $e) {
            return $this->soporteTiFail($e);
        }
    }
}
