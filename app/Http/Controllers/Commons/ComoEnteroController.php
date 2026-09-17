<?php

namespace App\Http\Controllers\Commons;

use App\Http\Controllers\Controller;
use App\Support\Organizacion\OrganizacionPortalUrls;
use App\Support\Register\ComoEnteroCatalog;
use Illuminate\Http\Request;

class ComoEnteroController extends Controller
{
    /**
     * Opciones de "por qué medio nos encontraste" según org / versión.
     * Acepta ?version=v1|v2 para forzar (útil en QA).
     */
    public function options(Request $request)
    {
        $orgId = (int) $request->attributes->get(
            'organizacion_id',
            OrganizacionPortalUrls::tryOrgIdFromPublicRequest($request) ?? 0
        );

        $forced = strtolower(trim((string) $request->query('version', '')));
        if (in_array($forced, [ComoEnteroCatalog::VERSION_V1, ComoEnteroCatalog::VERSION_V2], true)) {
            $version = $forced;
        } else {
            $version = $orgId > 0
                ? ComoEnteroCatalog::versionForOrganizacion($orgId)
                : ComoEnteroCatalog::VERSION_V2;
        }

        return response()->json([
            'success' => true,
            'version' => $version,
            'otros_codes' => ComoEnteroCatalog::OTROS_CODES,
            'data' => ComoEnteroCatalog::registerOptions($version),
            'labels' => ComoEnteroCatalog::labels(),
        ]);
    }
}
