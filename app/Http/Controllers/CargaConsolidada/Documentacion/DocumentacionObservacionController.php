<?php

namespace App\Http\Controllers\CargaConsolidada\Documentacion;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Services\CargaConsolidada\DocumentacionObservacionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;

class DocumentacionObservacionController extends Controller
{
    /** @var DocumentacionObservacionService */
    protected $service;

    public function __construct(DocumentacionObservacionService $service)
    {
        $this->service = $service;
    }

    /**
     * @param int $idProveedor
     * @return \Illuminate\Http\JsonResponse
     */
    public function index($idProveedor)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $this->assertCoordinacion($user);

            $data = $this->service->listarPorProveedor((int) $idProveedor);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'Datos inválidos.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param int $idProveedor
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $idProveedor)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $this->assertCoordinacion($user);

            $observacion = $this->service->crear(
                (int) $idProveedor,
                $request->only(['categoria', 'mensaje']),
                $user
            );

            return response()->json([
                'success' => true,
                'data' => $observacion,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'Datos inválidos.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @param mixed $user
     * @return void
     */
    protected function assertCoordinacion($user)
    {
        if (!$user) {
            abort(401, 'No autenticado.');
        }

        $user->loadMissing('grupo');

        if (
            !$user->grupo
            || trim((string) $user->grupo->No_Grupo) !== Usuario::ROL_COORDINACION
        ) {
            abort(403, 'Solo usuarios de Coordinación pueden acceder a las observaciones del expediente.');
        }
    }
}
