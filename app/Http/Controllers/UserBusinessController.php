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
     * Crear o actualizar empresa del usuario
     *
     * @param UserBusinessRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeOrUpdate(Request $request)
    {
        try {
            $user = JWTAuth::user();
            Log::info('UserBusinessController - Usuario:', [
                'user' => $user
            ]);
            Log::info('UserBusinessController - Datos recibidos:', [
                'request' => $request->all()
            ]);
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
            
            // Validar cada campo individualmente
            if ($businessName !== null && $businessName !== '') {
                $validatedData['business_name'] = trim($businessName);
            }
            if ($businessRuc !== null && $businessRuc !== '') {
                $validatedData['business_ruc'] = trim($businessRuc);
            }
            if ($comercialCapacity !== null && $comercialCapacity !== '') {
                $validatedData['comercial_capacity'] = trim($comercialCapacity);
            }
            if ($rubric !== null && $rubric !== '') {
                $validatedData['rubric'] = trim($rubric);
            }
            if ($socialAddress !== null && $socialAddress !== '') {
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
                // Crear nueva empresa - requiere campos obligatorios
                $requiredFields = ['business_name', 'business_ruc', 'comercial_capacity', 'rubric'];
                $missingFields = [];
                
                foreach ($requiredFields as $field) {
                    if (!isset($validatedData[$field]) || empty(trim($validatedData[$field]))) {
                        $missingFields[] = $field;
                    }
                }
                
                if (!empty($missingFields)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Para crear una empresa se requieren: ' . implode(', ', $missingFields),
                        'received_data' => $validatedData // Para debug
                    ], 422);
                }

                $userBusiness = UserBusiness::create([
                    'name' => trim($validatedData['business_name']),
                    'ruc' => trim($validatedData['business_ruc']),
                    'comercial_capacity' => trim($validatedData['comercial_capacity']),
                    'rubric' => trim($validatedData['rubric']),
                    'social_address' => isset($validatedData['social_address']) ? trim($validatedData['social_address']) : null,
                ]);

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

    /**
     * Obtener empresa del usuario
     *
     * @return \Illuminate\Http\JsonResponse
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

            $userBusiness = $user->userBusiness;

            if (!$userBusiness) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró empresa asociada'
                ], 404);
            }

            return response()->json([
                'success' => true,
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
            Log::error('Error en show UserBusiness: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la empresa',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
