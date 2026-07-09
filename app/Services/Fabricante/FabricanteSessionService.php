<?php

namespace App\Services\Fabricante;

use App\Models\Fabricante\PUser;
use App\Models\Fabricante\PUserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FabricanteSessionService
{
    public function createSession(
        PUser $user,
        string $deviceId,
        string $platform,
        Request $request,
        ?string $deviceName = null,
        ?string $fcmToken = null,
    ): array {
        $plainToken = $this->generateToken();
        $now = now();
        $expiresAt = $now->copy()->addDays(config('fabricante.session_ttl_days', 30));

        $session = PUserSession::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'device_id' => $deviceId,
            ],
            [
                'token_hash' => $this->hashToken($plainToken),
                'token_prefix' => $this->tokenPrefix($plainToken),
                'device_name' => $deviceName,
                'platform' => $platform,
                'fcm_token' => $fcmToken,
                'ip_address' => $request->ip() ?? '0.0.0.0',
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'last_activity_at' => $now,
                'expires_at' => $expiresAt,
                'revoked_at' => null,
            ],
        );

        return [
            'session' => $session,
            'token' => $plainToken,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function findActiveSessionByToken(string $plainToken): ?PUserSession
    {
        if (! str_starts_with($plainToken, 'pbf_')) {
            return null;
        }

        $prefix = $this->tokenPrefix($plainToken);
        $hash = $this->hashToken($plainToken);

        $session = PUserSession::query()
            ->where('token_prefix', $prefix)
            ->where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        return $session;
    }

    public function touchSession(PUserSession $session): void
    {
        $session->forceFill(['last_activity_at' => now()])->save();
    }

    public function revokeSession(PUserSession $session): void
    {
        $session->revoke();
    }

    public function revokeDeviceSession(int $userId, string $deviceId): void
    {
        PUserSession::query()
            ->where('user_id', $userId)
            ->where('device_id', $deviceId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function revokeAllSessions(int $userId, ?int $exceptSessionId = null): void
    {
        $query = PUserSession::query()
            ->where('user_id', $userId)
            ->whereNull('revoked_at');

        if ($exceptSessionId) {
            $query->where('id', '!=', $exceptSessionId);
        }

        $query->update(['revoked_at' => now()]);
    }

    public function updateFcmToken(PUserSession $session, ?string $fcmToken): void
    {
        $session->forceFill(['fcm_token' => $fcmToken])->save();
    }

    public function generateToken(): string
    {
        return 'pbf_' . Str::random(64);
    }

    public function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function tokenPrefix(string $plainToken): string
    {
        return substr($plainToken, 0, 16);
    }
}
