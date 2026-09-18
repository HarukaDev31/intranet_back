<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Support\Organizacion\OrganizacionPortalUrls;
use Illuminate\Http\Request;

/**
 * URLs públicas de la org del JWT. Org 1 puede pedir otra org (panel / headers).
 */
class OrganizacionPortalController extends Controller
{
    public function show(Request $request)
    {
        $authUser = auth()->user();
        if (!$authUser) {
            return response()->json(['success' => false, 'message' => 'No autorizado'], 401);
        }

        $authOrg = (int) $authUser->getAttribute('ID_Organizacion');
        $orgId = $authOrg;
        if ($authOrg === Usuario::ID_ORGANIZACION_ADMIN) {
            $fromRequest = $request->input('organizacion_id', $request->input('id_org'));
            if ($fromRequest !== null && $fromRequest !== '') {
                $orgId = (int) $fromRequest;
            }
        }

        return response()->json([
            'success' => true,
            'data' => OrganizacionPortalUrls::toArray($orgId, $authOrg === Usuario::ID_ORGANIZACION_ADMIN),
        ]);
    }
}
