<?php

namespace App\Http\Controllers\SoporteTi;

use App\Http\Controllers\Controller;
use App\Services\SoporteTi\SoporteTiIndicadoresService;
use App\Support\SoporteTi\RespondsSoporteTiJson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SoporteTiIndicadoresController extends Controller
{
    use RespondsSoporteTiJson;

    /** @var SoporteTiIndicadoresService */
    protected $indicadores;

    public function __construct(SoporteTiIndicadoresService $indicadores)
    {
        $this->indicadores = $indicadores;
    }

    public function show(Request $request)
    {
        $request->validate(array(
            'mes' => 'nullable|date_format:Y-m',
            'area' => 'nullable|string|max:80',
            'tab' => 'nullable|in:resumen,detalle,tiempos',
            'page' => 'nullable|integer|min:1',
            'page_fin' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:50',
        ));

        try {
            return $this->soporteTiOk(
                $this->indicadores->obtener(
                    $request->input('mes'),
                    $request->input('area'),
                    $request->input('tab', 'resumen'),
                    $request->input('page', 1),
                    $request->input('page_fin', 1),
                    $request->input('per_page', 15),
                    Auth::user()
                )
            );
        } catch (\Throwable $e) {
            return $this->soporteTiFail($e);
        }
    }
}
