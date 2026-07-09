<?php

namespace App\Services\Fabricante;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FirebaseAuthService
{
    public function verifyIdToken(string $idToken): array
    {
        $apiKey = config('fabricante.firebase_web_api_key');

        if (empty($apiKey)) {
            throw new RuntimeException('FIREBASE_WEB_API_KEY no configurada.');
        }

        $response = Http::timeout(10)->post(
            'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . $apiKey,
            ['idToken' => $idToken]
        );

        if (! $response->successful()) {
            Log::warning('Firebase token inválido', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new RuntimeException('Token de Firebase inválido o expirado.');
        }

        $users = $response->json('users', []);

        if (empty($users)) {
            throw new RuntimeException('No se pudo validar el usuario de Firebase.');
        }

        $firebaseUser = $users[0];

        return [
            'uid' => $firebaseUser['localId'] ?? null,
            'email' => $firebaseUser['email'] ?? null,
            'email_verified' => (bool) ($firebaseUser['emailVerified'] ?? false),
            'display_name' => $firebaseUser['displayName'] ?? null,
            'photo_url' => $firebaseUser['photoUrl'] ?? null,
        ];
    }
}
