<?php

namespace App\Http\Controllers\Fabricante;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fabricante\FabricanteFcmTokenRequest;
use App\Http\Requests\Fabricante\FabricanteFirebaseLoginRequest;
use App\Http\Requests\Fabricante\FabricanteLoginRequest;
use App\Http\Requests\Fabricante\FabricanteRegisterRequest;
use App\Http\Requests\Fabricante\FabricanteResendVerificationRequest;
use App\Http\Requests\Fabricante\FabricanteUpdateProfileRequest;
use App\Http\Requests\Fabricante\FabricanteVerifyEmailRequest;
use App\Models\Fabricante\PUser;
use App\Models\Fabricante\PUserSession;
use App\Services\Fabricante\FabricanteAuthService;
use App\Services\Fabricante\FabricanteSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FabricanteAuthController extends Controller
{
    public function __construct(
        private readonly FabricanteAuthService $authService,
        private readonly FabricanteSessionService $sessionService,
    ) {}

    public function register(FabricanteRegisterRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->registerWithEmail($request->validated(), $request);

            return response()->json([
                'success' => true,
                ...$result,
            ], 201);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('Fabricante register error', ['error' => $e->getMessage()]);

            return $this->errorResponse('No se pudo completar el registro.', 500);
        }
    }

    public function login(FabricanteLoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->loginWithEmail($request->validated(), $request);

            return response()->json([
                'success' => true,
                'message' => 'Sesión iniciada correctamente',
                ...$result,
            ]);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 401);
        } catch (\Throwable $e) {
            Log::error('Fabricante login error', ['error' => $e->getMessage()]);

            return $this->errorResponse('No se pudo iniciar sesión.', 500);
        }
    }

    public function loginFirebase(FabricanteFirebaseLoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->loginWithFirebase($request->validated(), $request);

            return response()->json([
                'success' => true,
                'message' => 'Sesión iniciada con Firebase',
                ...$result,
            ]);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 401);
        } catch (\Throwable $e) {
            Log::error('Fabricante firebase login error', ['error' => $e->getMessage()]);

            return $this->errorResponse('No se pudo validar Firebase.', 500);
        }
    }

    public function verifyEmail(FabricanteVerifyEmailRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->verifyEmail(
                $request->validated('email'),
                $request->validated('token'),
            );

            return response()->json([
                'success' => true,
                ...$result,
            ]);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function resendVerification(FabricanteResendVerificationRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->resendVerification($request->validated('email'));

            return response()->json([
                'success' => true,
                ...$result,
            ]);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return response()->json([
            'success' => true,
            'user' => $this->authService->formatUser($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($this->session($request));

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada',
        ]);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $currentSession = $this->session($request);

        $this->sessionService->revokeAllSessions($user->id, $currentSession->id);

        return response()->json([
            'success' => true,
            'message' => 'Se cerraron las demás sesiones activas',
        ]);
    }

    public function sessions(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $currentSession = $this->session($request);

        $sessions = $user->activeSessions()
            ->orderByDesc('last_activity_at')
            ->get()
            ->map(function (PUserSession $session) use ($currentSession) {
                $formatted = $this->authService->formatSession($session);
                $formatted['is_current'] = $session->id === $currentSession->id;

                return $formatted;
            });

        return response()->json([
            'success' => true,
            'sessions' => $sessions,
        ]);
    }

    public function revokeSession(Request $request, int $sessionId): JsonResponse
    {
        $user = $this->user($request);
        $currentSession = $this->session($request);

        $session = PUserSession::query()
            ->where('user_id', $user->id)
            ->where('id', $sessionId)
            ->first();

        if (! $session) {
            return $this->errorResponse('Sesión no encontrada', 404);
        }

        if ($session->id === $currentSession->id) {
            return $this->errorResponse('No puedes revocar la sesión actual desde aquí. Usa logout.', 422);
        }

        $this->sessionService->revokeSession($session);

        return response()->json([
            'success' => true,
            'message' => 'Sesión revocada',
        ]);
    }

    public function updateFcmToken(FabricanteFcmTokenRequest $request): JsonResponse
    {
        $session = $this->session($request);
        $this->sessionService->updateFcmToken($session, $request->validated('fcm_token'));

        return response()->json([
            'success' => true,
            'message' => 'Token FCM actualizado',
        ]);
    }

    public function updateProfile(FabricanteUpdateProfileRequest $request): JsonResponse
    {
        try {
            $user = $this->authService->updateProfile(
                $this->user($request),
                $request->validated(),
                $request->file('avatar'),
            );

            return response()->json([
                'success' => true,
                'message' => 'Perfil actualizado',
                'user' => $this->authService->formatUser($user),
            ]);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('Fabricante profile update error', ['error' => $e->getMessage()]);

            return $this->errorResponse('No se pudo actualizar el perfil.', 500);
        }
    }

    private function user(Request $request): PUser
    {
        return $request->attributes->get('fabricante_user');
    }

    private function session(Request $request): PUserSession
    {
        return $request->attributes->get('fabricante_session');
    }

    private function errorResponse(string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
