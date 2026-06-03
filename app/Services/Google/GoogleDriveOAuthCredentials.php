<?php

namespace App\Services\Google;

use Google\Client as GoogleClient;
use Google\Service\Drive;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * OAuth de usuario para Google Drive (My Drive personal).
 * Guarda access + refresh token en storage/app/google-drive-oauth-token.json.
 */
class GoogleDriveOAuthCredentials
{
    public function isEnabled(): bool
    {
        return (bool) config('google.drive_oauth.enabled', true);
    }

    public function hasClientCredentials(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function isConfigured(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        if (!$this->hasClientCredentials()) {
            return false;
        }

        return $this->refreshTokenFromEnv() !== '' || $this->tokenFileHasRefreshToken();
    }

    public function createBaseClient(): GoogleClient
    {
        $client = new GoogleClient();
        $client->setApplicationName((string) config('google.application_name', 'Probusiness Intranet'));
        $client->setClientId($this->clientId());
        $client->setClientSecret($this->clientSecret());
        $client->setRedirectUri($this->redirectUri());
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->addScope(Drive::DRIVE);

        return $client;
    }

    public function createAuthenticatedClient(): GoogleClient
    {
        $client = $this->createBaseClient();
        $token = $this->loadToken();

        if ($token === null) {
            throw new \RuntimeException(
                'Google Drive OAuth sin token. Ejecute: php artisan google:drive-oauth'
            );
        }

        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired()) {
            $refresh = $client->getRefreshToken()
                ?: ($token['refresh_token'] ?? $this->refreshTokenFromEnv());

            if ($refresh === '') {
                throw new \RuntimeException(
                    'Google Drive OAuth: refresh token ausente. Vuelva a autorizar: php artisan google:drive-oauth'
                );
            }

            $newToken = $client->fetchAccessTokenWithRefreshToken($refresh);

            if (isset($newToken['error'])) {
                $message = is_array($newToken['error'])
                    ? ($newToken['error']['message'] ?? json_encode($newToken['error']))
                    : (string) $newToken['error'];

                throw new \RuntimeException('Google Drive OAuth refresh falló: ' . $message);
            }

            if (!isset($newToken['refresh_token']) && $refresh !== '') {
                $newToken['refresh_token'] = $refresh;
            }

            $this->saveToken($newToken);
            $client->setAccessToken($newToken);
        }

        return $client;
    }

    public function buildAuthorizationUrl(): string
    {
        return $this->createBaseClient()->createAuthUrl();
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeCodeForToken(string $code): array
    {
        $client = $this->createBaseClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            $message = is_array($token['error'])
                ? ($token['error']['message'] ?? json_encode($token['error']))
                : (string) $token['error'];

            throw new \RuntimeException('Google Drive OAuth: ' . $message);
        }

        if (!isset($token['refresh_token'])) {
            $existing = $this->loadToken();
            if ($existing !== null && !empty($existing['refresh_token'])) {
                $token['refresh_token'] = $existing['refresh_token'];
            }
        }

        $this->saveToken($token);

        return $token;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function loadToken(): ?array
    {
        $envRefresh = $this->refreshTokenFromEnv();
        if ($envRefresh !== '') {
            return ['refresh_token' => $envRefresh];
        }

        $path = $this->tokenPath();
        if (!is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $token
     */
    public function saveToken(array $token): void
    {
        if ($this->refreshTokenFromEnv() !== '') {
            Log::info('Google Drive OAuth: token refrescado (refresh en .env, no se escribe archivo)');

            return;
        }

        $path = $this->tokenPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        file_put_contents($path, json_encode($token, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        Log::info('Google Drive OAuth: token guardado', ['path' => $path]);
    }

    public function tokenPath(): string
    {
        $relative = trim((string) config('google.drive_oauth.token_file', 'google-drive-oauth-token.json'));

        return storage_path('app/' . ltrim($relative, '/'));
    }

    public function redirectUri(): string
    {
        return trim((string) config('google.drive_oauth.redirect_uri', ''));
    }

    private function clientId(): string
    {
        return trim((string) config('google.drive_oauth.client_id', config('google.client_id', '')));
    }

    private function clientSecret(): string
    {
        return trim((string) config('google.drive_oauth.client_secret', config('google.client_secret', '')));
    }

    private function refreshTokenFromEnv(): string
    {
        return trim((string) config('google.drive_oauth.refresh_token', ''));
    }

    private function tokenFileHasRefreshToken(): bool
    {
        $token = $this->loadToken();

        return $token !== null && !empty($token['refresh_token']);
    }
}
