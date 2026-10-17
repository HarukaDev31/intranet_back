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
        ));

        try {
            return $this->soporteTiOk(
                $this->indicadores->obtener(
                    $request->input('mes'),
                    $request->input('area'),
                    Auth::user()
                )
            );
        } catch (\Throwable $e) {
            return $this->soporteTiFail($e);
        }
    }
}
