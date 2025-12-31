<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Http\Requests\UserProfileRequest;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Traits\FileTrait;

class UserProfileController extends Controller
{
    use FileTrait;

    /**
     * @OA\Get(
     *     path="/auth/clientes/profile",
     *     tags={"Perfil Usuario"},
     *     summary="Obtener perfil del cliente",
     *     description="Obtiene la información del perfil del cliente autenticado",
     *     operationId="getClienteProfile",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Perfil obtenido exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="user", type="object")
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

            $user->load(['pais', 'provincia', 'departamento', 'distrito']);

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'photoUrl' => $this->generateImageUrl($user->photo_url),
                    'email' => $user->email,
                    'phone' => $user->whatsapp,
                    'fechaNacimiento' => $user->birth_date,
                    'country' => $user->pais_id,
                    'countryName' => $user->pais ? $user->pais->No_Pais : null,
                    'city' => $user->provincia_id,
                    'cityName' => $user->provincia ? $user->provincia->No_Provincia : null,
                    'departamento' => $user->departamento_id,
                    'departamentoName' => $user->departamento ? $user->departamento->No_Departamento : null,
                    'distrito' => $user->distrito_id,
                    'distritoName' => $user->distrito ? $user->distrito->No_Distrito : null,
                    'goals' => $user->goals,
                    'dni' => $user->dni,
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al obtener perfil: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el perfil'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/auth/clientes/profile",
     *     tags={"Perfil Usuario"},
     *     summary="Actualizar perfil del cliente",
     *     description="Actualiza la información del perfil del cliente autenticado",
     *     operationId="updateClienteProfile",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="email", type="string", format="email"),
     *                 @OA\Property(property="phone", type="string"),
     *                 @OA\Property(property="fecha_nacimiento", type="string", format="date"),
     *                 @OA\Property(property="country", type="integer"),
     *                 @OA\Property(property="city", type="integer"),
     *                 @OA\Property(property="departamento", type="integer"),
     *                 @OA\Property(property="distrito", type="integer"),
     *                 @OA\Property(property="dni", type="string"),
     *                 @OA\Property(property="goals", type="string"),
     *                 @OA\Property(property="photo", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Perfil actualizado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="user", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=422, description="Error de validación")
     * )
     *
     * Actualizar perfil del usuario
     *
     * @param UserProfileRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UserProfileRequest $request)
    {
        try {
            $user = JWTAuth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 401);
            }

            $validatedData = $request->validated();

            DB::beginTransaction();

            // Procesar foto si se proporciona
            $photoUrl = $user->photo_url; // Mantener la foto actual por defecto
            
            if ($request->hasFile('photo')) {
                // Eliminar foto anterior si existe
                if ($user->photo_url && Storage::disk('public')->exists($user->photo_url)) {
                    Storage::disk('public')->delete($user->photo_url);
                }
                
                // Guardar nueva foto
                $photo = $request->file('photo');
                $photoName = 'profile_' . $user->id . '_' . time() . '.' . $photo->getClientOriginalExtension();
                $photoPath = $photo->storeAs('profiles', $photoName, 'public');
                $photoUrl = $photoPath;
            }

            // Separar nombre completo en nombre y apellido
        

            // Actualizar usuario
            $user->update([
                'email' => $validatedData['email'],
                'phone' => $validatedData['phone'] ?? null,
                'whatsapp' => $validatedData['phone'] ?? null,
                'photo_url' => $this->generateImageUrl($photoUrl),
                'birth_date' => $validatedData['fecha_nacimiento'] ?? null,
                'pais_id' => $validatedData['country'] ?? null,
                'provincia_id' => $validatedData['city'] ?? null,
                'departamento_id' => $validatedData['departamento'] ?? null,
                'distrito_id' => $validatedData['distrito'] ?? null,
                'dni' => $validatedData['dni'] ?? null,
                'goals' => $validatedData['goals'] ?? null,
            ]);

            DB::commit();

            // Cargar las relaciones para devolver los nombres
            $user->load(['pais', 'provincia', 'departamento', 'distrito']);

            return response()->json([
                'success' => true,
                'message' => 'Perfil actualizado exitosamente',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'photoUrl' => $this->generateImageUrl($user->photo_url),
                    'email' => $user->email,
                    'phone' => $user->whatsapp,
                    'fechaNacimiento' => $user->birth_date,
                    'country' => $user->pais_id, // ID para el modo editar
                    'countryName' => $user->pais ? $user->pais->No_Pais : null, // Nombre para mostrar
                    'city' => $user->provincia_id, // ID para el modo editar
                    'cityName' => $user->provincia ? $user->provincia->No_Provincia : null, // Nombre para mostrar
                    'departamento' => $user->departamento_id, // ID para el modo editar
                    'departamentoName' => $user->departamento ? $user->departamento->No_Departamento : null, // Nombre para mostrar
                    'distrito' => $user->distrito_id, // ID para el modo editar
                    'distritoName' => $user->distrito ? $user->distrito->No_Distrito : null, // Nombre para mostrar
                    'goals' => $user->goals,
                    'dni' => $user->dni,
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en update UserProfile: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el perfil',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
