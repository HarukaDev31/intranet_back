<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\UserBusiness;
use App\Http\Requests\UserBusinessRequest;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserBusinessController extends Controller
{
    /**
     * @OA\Get(
     *     path="/auth/clientes/business",
     *     tags={"Empresa Usuario"},
     *     summary="Obtener datos de empresa del cliente",
     *     description="Obtiene la información de la empresa asociada al cliente autenticado",
     *     operationId="getClienteBusiness",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Datos de empresa obtenidos exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="business", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="ruc", type="string"),
     *                 @OA\Property(property="comercialCapacity", type="string"),
     *                 @OA\Property(property="rubric", type="string"),
     *                 @OA\Property(property="socialAddress", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function show()
    {
        try {
            $user = JWTAuth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 401);
            }

            $userBusiness = UserBusiness::find($user->id_user_business);

            return response()->json([
                'success' => true,
                'business' => $userBusiness ? [
                    'id' => $userBusiness->id,
                    'name' => $userBusiness->name,
                    'ruc' => $userBusiness->ruc,
                    'comercialCapacity' => $userBusiness->comercial_capacity,
                    'rubric' => $userBusiness->rubric,
                    'socialAddress' => $userBusiness->social_address,
                ] : null
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al obtener datos de empresa: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos de empresa'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/auth/clientes/business",
     *     tags={"Empresa Usuario"},
     *     summary="Crear o actualizar empresa del cliente",
     *     description="Crea o actualiza la información de la empresa del cliente autenticado",
     *     operationId="storeOrUpdateClienteBusiness",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="business_name", type="string", example="Mi Empresa S.A.C."),
     *             @OA\Property(property="business_ruc", type="string", example="20123456789"),
     *             @OA\Property(property="comercial_capacity", type="string", example="Gerente General"),
     *             @OA\Property(property="rubric", type="string", example="Importaciones"),
     *             @OA\Property(property="social_address", type="string", example="Av. Principal 123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Empresa creada/actualizada exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="business", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     *
     * Crear o actualizar empresa del usuario
     *
     * @param UserBusinessRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeOrUpdate(Request $request)
    {
        try {
            $user = JWTAuth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 401);
            }

            // Obtener datos de diferentes maneras según el Content-Type
            $validatedData = [];
            
            // Detectar el tipo de contenido
            $contentType = $request->header('Content-Type');
            
            if (strpos($contentType, 'multipart/form-data') !== false) {
                // Para FormData, usar diferentes métodos
                $businessName = $request->input('business_name') ?? $request->get('business_name');
                $businessRuc = $request->input('business_ruc') ?? $request->get('business_ruc');
                $comercialCapacity = $request->input('comercial_capacity') ?? $request->get('comercial_capacity');
                $rubric = $request->input('rubric') ?? $request->get('rubric');
                $socialAddress = $request->input('social_address') ?? $request->get('social_address');
                
                // También intentar obtener del raw content
                if (empty($businessName)) {
                    $rawContent = $request->getContent();
                    if (preg_match('/name="business_name"[^>]*>([^<]+)/', $rawContent, $matches)) {
                        $businessName = $matches[1];
                    }
                }
            } else {
                // Para JSON o application/x-www-form-urlencoded
                $businessName = $request->input('business_name');
                $businessRuc = $request->input('business_ruc');
                $comercialCapacity = $request->input('comercial_capacity');
                $rubric = $request->input('rubric');
                $socialAddress = $request->input('social_address');
            }
            
            // Validar cada campo individualmente - solo incluir si viene en la petición
            if ($businessName !== null && $businessName !== '' && $request->has('business_name')) {
                $validatedData['business_name'] = trim($businessName);
            }
            if ($businessRuc !== null && $businessRuc !== '' && $request->has('business_ruc')) {
                $validatedData['business_ruc'] = trim($businessRuc);
            }
            if ($comercialCapacity !== null && $comercialCapacity !== '' && $request->has('comercial_capacity')) {
                $validatedData['comercial_capacity'] = trim($comercialCapacity);
            }
            if ($rubric !== null && $rubric !== '' && $request->has('rubric')) {
                $validatedData['rubric'] = trim($rubric);
            }
            if ($socialAddress !== null && $socialAddress !== '' && $request->has('social_address')) {
                $validatedData['social_address'] = trim($socialAddress);
            }
            
            // Log para debug
            Log::info('UserBusinessController - Datos recibidos:', [
                'validated_data' => $validatedData,
                'raw_data' => $request->all(),
                'input_data' => $request->input(),
                'get_data' => $request->query(),
                'post_data' => $request->post(),
                'content' => $request->getContent(),
                'user_id' => $user->id,
                'has_business' => $user->id_user_business ? true : false,
                'content_type' => $request->header('Content-Type'),
                'method' => $request->method(),
                'files' => $request->allFiles(),
                'headers' => $request->headers->all()
            ]);

            DB::beginTransaction();

            // Buscar si el usuario ya tiene una empresa asociada
            $userBusiness = UserBusiness::find($user->id_user_business);

            if ($userBusiness) {
                // Actualizar empresa existente - solo actualizar campos proporcionados
                $updateData = [];
                if (isset($validatedData['business_name'])) {
                    $updateData['name'] = $validatedData['business_name'];
                }
                if (isset($validatedData['business_ruc'])) {
                    $updateData['ruc'] = $validatedData['business_ruc'];
                }
                if (isset($validatedData['comercial_capacity'])) {
                    $updateData['comercial_capacity'] = $validatedData['comercial_capacity'];
                }
                if (isset($validatedData['rubric'])) {
                    $updateData['rubric'] = $validatedData['rubric'];
                }
                if (isset($validatedData['social_address'])) {
                    $updateData['social_address'] = $validatedData['social_address'];
                }

                $userBusiness->update($updateData);
                $message = 'Empresa actualizada exitosamente';
            } else {
                // Crear nueva empresa - solo con los campos que vienen en la petición
                $createData = [];
                
                if (isset($validatedData['business_name'])) {
                    $createData['name'] = $validatedData['business_name'];
                }
                if (isset($validatedData['business_ruc'])) {
                    $createData['ruc'] = $validatedData['business_ruc'];
                }
                if (isset($validatedData['comercial_capacity'])) {
                    $createData['comercial_capacity'] = $validatedData['comercial_capacity'];
                }
                if (isset($validatedData['rubric'])) {
                    $createData['rubric'] = $validatedData['rubric'];
                }
                if (isset($validatedData['social_address'])) {
                    $createData['social_address'] = $validatedData['social_address'];
                }

                // Solo crear si hay al menos un campo
                if (empty($createData)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se proporcionaron datos para crear la empresa'
                    ], 400);
                }

                $userBusiness = UserBusiness::create($createData);

                // Asociar la empresa al usuario
                $user->update(['id_user_business' => $userBusiness->id]);
                $message = 'Empresa creada exitosamente';
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'business' => [
                    'id' => $userBusiness->id,
                    'name' => $userBusiness->name,
                    'ruc' => $userBusiness->ruc,
                    'comercialCapacity' => $userBusiness->comercial_capacity,
                    'rubric' => $userBusiness->rubric,
                    'socialAddress' => $userBusiness->social_address,
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en storeOrUpdate UserBusiness: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la empresa',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
