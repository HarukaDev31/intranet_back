<?php

namespace App\Http\Middleware;

use App\Support\Organizacion\OrganizacionPortalUrls;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige header X-Org-Key válido. Sin key o key inválida → 403.
 */
class RequireOrgKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $orgId = OrganizacionPortalUrls::orgIdFromPublicRequest($request);
        $request->attributes->set('organizacion_id', $orgId);

        return $next($request);
    }
}
