<?php

namespace App\Http\Controllers\Verificacion;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Services\Verificacion\VerificacionContadoresService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificacionContadoresController extends Controller
{
    /** @var VerificacionContadoresService */
    private $contadores;

    public function __construct(VerificacionContadoresService $contadores)
    {
        $this->contadores = $contadores;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'No autenticado.'], 401);
        }

        $user->loadMissing('grupo');
        $grupoRelacion = $user->getRelationValue('grupo');
        $grupo = trim((string) ($grupoRelacion instanceof \Illuminate\Database\Eloquent\Model
            ? $grupoRelacion->getAttribute('No_Grupo')
            : ''));

        if ($grupo !== Usuario::ROL_ADMINISTRACION) {
            return response()->json([
                'success' => true,
                'data' => [
                    'consolidado' => 0,
                    'cursos' => 0,
                    'delivery' => 0,
                    'permisos' => 0,
                    'boletin_quimico' => 0,
                    'supplier_payment' => 0,
                    'supplier_approvals' => 0,
                    'supplier_executions' => 0,
                    'viaticos' => 0,
                    'verificacion_total' => 0,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $this->contadores->all(),
        ]);
    }
}
