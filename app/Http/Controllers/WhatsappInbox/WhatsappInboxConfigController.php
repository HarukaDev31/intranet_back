<?php

namespace App\Http\Controllers\WhatsappInbox;

use App\Http\Controllers\Controller;
use App\Services\WhatsappInbox\WhatsappInboxOrgConfigService;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class WhatsappInboxConfigController extends Controller
{
    /** @var WhatsappInboxOrgConfigService */
    protected $orgConfig;

    public function __construct(WhatsappInboxOrgConfigService $orgConfig)
    {
        $this->orgConfig = $orgConfig;
    }

    public function show()
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user || !$user->puedeConfigurarWhatsappInbox()) {
            return response()->json(['success' => false, 'message' => 'No autorizado'], 403);
        }

        $orgId = (int) $user->getAttribute('ID_Organizacion');

        return response()->json([
            'success' => true,
            'data' => $this->orgConfig->adminPayload($orgId),
        ]);
    }

    public function update(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user || !$user->puedeConfigurarWhatsappInbox()) {
            return response()->json(['success' => false, 'message' => 'No autorizado'], 403);
        }

        $orgId = (int) $user->getAttribute('ID_Organizacion');

        try {
            $this->orgConfig->saveForOrganizacion($orgId, $request->all());
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo guardar la configuración',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Configuración guardada',
            'data' => $this->orgConfig->adminPayload($orgId),
        ]);
    }
}
