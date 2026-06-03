<?php

namespace App\Console\Commands;

use App\Services\Google\GoogleDriveOAuthCredentials;
use Illuminate\Console\Command;

class GoogleDriveOAuthAuthorizeCommand extends Command
{
    protected $signature = 'google:drive-oauth
                            {--code= : Código de autorización (si no usa callback HTTP)}
                            {--url-only : Solo imprimir la URL de autorización}';

    protected $description = 'Autoriza Google Drive OAuth para subir Excel de confirmación (My Drive)';

    public function handle(GoogleDriveOAuthCredentials $oauth): int
    {
        if (!$oauth->hasClientCredentials()) {
            $this->error('Configure GOOGLE_CLIENT_ID y GOOGLE_CLIENT_SECRET en .env');
            $this->line('Redirect URI: ' . $oauth->redirectUri());

            return self::FAILURE;
        }

        $code = trim((string) $this->option('code'));

        if ($code === '') {
            $url = $oauth->buildAuthorizationUrl();
            $this->info('1. Abra esta URL e inicie sesión con la cuenta dueña de la carpeta Excel Confirmacion:');
            $this->line($url);
            $this->newLine();
            $this->info('2. Opción A — callback HTTP (recomendado):');
            $this->line('   Añada en Google Cloud → Credenciales → URI de redirección:');
            $this->line('   ' . $oauth->redirectUri());
            $this->line('   Tras autorizar, Google redirige al callback y guarda el token.');
            $this->newLine();
            $this->info('2. Opción B — pegar código manual:');
            $this->line('   php artisan google:drive-oauth --code="CODIGO"');

            return self::SUCCESS;
        }

        try {
            $oauth->exchangeCodeForToken($code);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Token guardado en: ' . $oauth->tokenPath());
        $this->info('Pruebe Solicitar documentos o: php artisan google:drive-oauth (debe indicar que ya hay token si repite)');

        return self::SUCCESS;
    }
}
