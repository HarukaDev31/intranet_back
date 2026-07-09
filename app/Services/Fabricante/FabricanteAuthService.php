<?php

namespace App\Services\Fabricante;

use App\Mail\FabricanteEmailVerificationMail;
use App\Models\Fabricante\PUser;
use App\Models\Fabricante\PUserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

class FabricanteAuthService
{
    public function __construct(
        private readonly FabricanteSessionService $sessionService,
        private readonly FirebaseAuthService $firebaseAuthService,
    ) {}

    public function registerWithEmail(array $data, Request $request): array
    {
        return DB::transaction(function () use ($data, $request) {
            if (PUser::withTrashed()->where('email', $data['email'])->exists()) {
                throw new RuntimeException('El correo ya está registrado.');
            }

            $verificationToken = Str::random(64);

            $user = PUser::create([
                'email' => $data['email'],
                'password' => $data['password'],
                'company_name' => $data['company_name'],
                'contact_name' => $data['contact_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'country' => $data['country'] ?? null,
                'auth_provider' => 'email',
                'email_verification_token' => hash('sha256', $verificationToken),
                'email_verification_sent_at' => now(),
                'is_active' => true,
            ]);

            $this->sendVerificationEmail($user, $verificationToken);

            return [
                'user' => $this->formatUser($user),
                'requires_email_verification' => true,
                'message' => 'Registro exitoso. Revisa tu correo para verificar la cuenta.',
            ];
        });
    }

    public function loginWithEmail(array $data, Request $request): array
    {
        $user = PUser::where('email', $data['email'])
            ->where('is_active', true)
            ->first();

        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password)) {
            throw new RuntimeException('Correo o contraseña incorrectos.');
        }

        if ($user->isEmailProvider() && ! $user->hasVerifiedEmail()) {
            throw new RuntimeException('Debes verificar tu correo antes de iniciar sesión.');
        }

        return $this->completeLogin($user, $data, $request);
    }

    public function loginWithFirebase(array $data, Request $request): array
    {
        $firebaseUser = $this->firebaseAuthService->verifyIdToken($data['id_token']);

        if (empty($firebaseUser['uid']) || empty($firebaseUser['email'])) {
            throw new RuntimeException('Firebase no devolvió datos válidos del usuario.');
        }

        return DB::transaction(function () use ($firebaseUser, $data, $request) {
            $user = PUser::where(function ($query) use ($firebaseUser) {
                $query->where('firebase_uid', $firebaseUser['uid'])
                    ->orWhere('email', $firebaseUser['email']);
            })->first();

            if ($user && $user->trashed()) {
                throw new RuntimeException('La cuenta está desactivada.');
            }

            if (! $user) {
                $user = PUser::create([
                    'email' => $firebaseUser['email'],
                    'company_name' => $data['company_name'] ?? ($firebaseUser['display_name'] ?: 'Fabricante'),
                    'contact_name' => $firebaseUser['display_name'],
                    'avatar_url' => $firebaseUser['photo_url'],
                    'firebase_uid' => $firebaseUser['uid'],
                    'auth_provider' => 'firebase',
                    'email_verified_at' => $firebaseUser['email_verified'] ? now() : null,
                    'is_active' => true,
                ]);
            } else {
                $user->forceFill([
                    'firebase_uid' => $firebaseUser['uid'],
                    'auth_provider' => 'firebase',
                    'avatar_url' => $firebaseUser['photo_url'] ?? $user->avatar_url,
                    'email_verified_at' => $firebaseUser['email_verified']
                        ? ($user->email_verified_at ?? now())
                        : $user->email_verified_at,
                ])->save();
            }

            if (! $user->hasVerifiedEmail()) {
                throw new RuntimeException('El correo de Firebase no está verificado.');
            }

            return $this->completeLogin($user, $data, $request);
        });
    }

    public function verifyEmail(string $email, string $token): array
    {
        $user = PUser::where('email', $email)->first();

        if (! $user) {
            throw new RuntimeException('Usuario no encontrado.');
        }

        if ($user->hasVerifiedEmail()) {
            return [
                'user' => $this->formatUser($user),
                'message' => 'El correo ya estaba verificado.',
            ];
        }

        $tokenHash = hash('sha256', $token);

        if (! hash_equals((string) $user->email_verification_token, $tokenHash)) {
            throw new RuntimeException('Token de verificación inválido.');
        }

        $sentAt = $user->email_verification_sent_at;
        $ttlHours = config('fabricante.verification_token_ttl_hours', 48);

        if ($sentAt && $sentAt->copy()->addHours($ttlHours)->isPast()) {
            throw new RuntimeException('El token de verificación expiró. Solicita uno nuevo.');
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'email_verification_token' => null,
        ])->save();

        return [
            'user' => $this->formatUser($user->fresh()),
            'message' => 'Correo verificado correctamente.',
        ];
    }

    public function resendVerification(string $email): array
    {
        $user = PUser::where('email', $email)->first();

        if (! $user) {
            throw new RuntimeException('Usuario no encontrado.');
        }

        if ($user->hasVerifiedEmail()) {
            throw new RuntimeException('El correo ya está verificado.');
        }

        if (! $user->isEmailProvider()) {
            throw new RuntimeException('Esta cuenta usa autenticación de Firebase.');
        }

        $verificationToken = Str::random(64);

        $user->forceFill([
            'email_verification_token' => hash('sha256', $verificationToken),
            'email_verification_sent_at' => now(),
        ])->save();

        $this->sendVerificationEmail($user, $verificationToken);

        return ['message' => 'Correo de verificación reenviado.'];
    }

    public function logout(PUserSession $session): void
    {
        $this->sessionService->revokeSession($session);
    }

    public function updateProfile(PUser $user, array $data, $avatar = null): PUser
    {
        $updates = [
            'company_name' => $data['company_name'],
            'contact_name' => $data['contact_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'country' => $data['country'] ?? null,
        ];

        if ($avatar) {
            $path = $avatar->store('fabricante/avatars/' . $user->id, 'public');
            $updates['avatar_url'] = $this->publicAssetUrl($path);
        }

        $user->forceFill($updates)->save();

        return $user->fresh();
    }

    private function publicAssetUrl(string $storagePath): string
    {
        $url = \Illuminate\Support\Facades\Storage::disk('public')->url($storagePath);

        if (str_starts_with($url, 'http')) {
            return $url;
        }

        return rtrim(config('app.url'), '/') . '/' . ltrim($url, '/');
    }

    public function formatUser(PUser $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'company_name' => $user->company_name,
            'contact_name' => $user->contact_name,
            'phone' => $user->phone,
            'country' => $user->country,
            'avatar_url' => $user->avatar_url,
            'auth_provider' => $user->auth_provider,
            'email_verified' => $user->hasVerifiedEmail(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'last_login_ip' => $user->last_login_ip,
        ];
    }

    public function formatSession(PUserSession $session): array
    {
        return [
            'id' => $session->id,
            'device_id' => $session->device_id,
            'device_name' => $session->device_name,
            'platform' => $session->platform,
            'ip_address' => $session->ip_address,
            'has_fcm_token' => ! empty($session->fcm_token),
            'last_activity_at' => $session->last_activity_at?->toIso8601String(),
            'expires_at' => $session->expires_at?->toIso8601String(),
            'is_current' => false,
        ];
    }

    private function completeLogin(PUser $user, array $data, Request $request): array
    {
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $sessionData = $this->sessionService->createSession(
            user: $user,
            deviceId: $data['device_id'],
            platform: $data['platform'],
            request: $request,
            deviceName: $data['device_name'] ?? null,
            fcmToken: $data['fcm_token'] ?? null,
        );

        return [
            'token' => $sessionData['token'],
            'token_type' => 'Bearer',
            'expires_at' => $sessionData['expires_at'],
            'user' => $this->formatUser($user->fresh()),
            'session' => $this->formatSession($sessionData['session']),
        ];
    }

    private function sendVerificationEmail(PUser $user, string $plainToken): void
    {
        $verificationUrl = config('fabricante.verification_url') . '?' . http_build_query([
            'email' => $user->email,
            'token' => $plainToken,
        ]);

        try {
            Mail::to($user->email)->send(new FabricanteEmailVerificationMail(
                user: $user,
                verificationUrl: $verificationUrl,
            ));
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar correo de verificación fabricante', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
