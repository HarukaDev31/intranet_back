<?php

namespace App\Http\Controllers\Google;

use App\Http\Controllers\Controller;
use App\Services\Google\GoogleDriveOAuthCredentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GoogleDriveOAuthController extends Controller
{
    /**
     * @return RedirectResponse|JsonResponse
     */
    public function authorizeRedirect(GoogleDriveOAuthCredentials $oauth)
    {
        if (!$oauth->hasClientCredentials()) {
            return response()->json([
                'success' => false,
                'message' => 'Configure GOOGLE_CLIENT_ID y GOOGLE_CLIENT_SECRET en .env',
            ], 422);
        }

        return redirect()->away($oauth->buildAuthorizationUrl());
    }

    public function callback(Request $request, GoogleDriveOAuthCredentials $oauth): JsonResponse
    {
        $code = trim((string) $request->query('code', ''));
        if ($code === '') {
            return response()->json([
                'success' => false,
                'message' => 'Falta parámetro code en la URL de callback',
            ], 400);
        }

        try {
            $oauth->exchangeCodeForToken($code);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Google Drive OAuth autorizado. Ya puede usar Solicitar documentos / subida de Excel.',
            'token_file' => $oauth->tokenPath(),
        ]);
    }
}
