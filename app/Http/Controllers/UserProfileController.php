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
            $fullName = $validatedData['full_name'];
            $nameParts = explode(' ', $fullName, 2);
            $firstName = $nameParts[0];
            $lastName = isset($nameParts[1]) ? $nameParts[1] : '';

            // Actualizar usuario
            $user->update([
                'name' => $firstName,
                'lastname' => $lastName,
                'email' => $validatedData['email'],
                'whatsapp' => $validatedData['phone'],
                'photo_url' => $this->generateImageUrl($photoUrl),
                'age' => $validatedData['age'] ?? null,
                'country' => $validatedData['country'] ?? null,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Perfil actualizado exitosamente',
                'user' => [
                    'id' => $user->id,
                    'fullName' => $user->full_name,
                    'photoUrl' => $this->generateImageUrl($user->photo_url),
                    'email' => $user->email,
                    'phone' => $user->whatsapp,
                    'age' => $user->age,
                    'country' => $user->country,
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

    /**
     * Obtener perfil del usuario
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

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'fullName' => $user->full_name,
                    'photoUrl' => $user->photo_url,
                    'email' => $user->email,
                    'phone' => $user->whatsapp,
                    'age' => $user->age,
                    'country' => $user->country,
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error en show UserProfile: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el perfil',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
