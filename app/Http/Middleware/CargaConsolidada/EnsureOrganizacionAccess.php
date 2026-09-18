<?php

namespace App\Http\Middleware\CargaConsolidada;

use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\CotizacionProveedor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierre centralizado del hueco IDOR de organizacion: muchos endpoints de
 * Carga Consolidada reciben {idContenedor}/{idCotizacion}/{idProveedor} en la
 * URL y arman su query con DB::table() puro, sin pasar antes por el modelo
 * Eloquent escopeado (Contenedor/Cotizacion/CotizacionProveedor ya filtran
 * por organizacion via OrganizacionScope). Este middleware valida esos tres
 * parametros de ruta -- si el registro existe pero no pertenece a ninguna
 * organizacion del usuario, el scope ya lo hace invisible y esto responde
 * 404, sin tocar cada controller uno por uno.
 *
 * No reemplaza revisar los controllers: solo cubre los parametros de ruta
 * con estos tres nombres exactos. Un ID recibido por otro medio (query
 * string, body) sigue sin protegerse aqui.
 */
class EnsureOrganizacionAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $idContenedor = $request->route('idContenedor');
        if ($idContenedor !== null && !Contenedor::where('id', $idContenedor)->exists()) {
            abort(404, 'Contenedor no encontrado');
        }

        $idCotizacion = $request->route('idCotizacion');
        if ($idCotizacion !== null && !Cotizacion::where('id', $idCotizacion)->exists()) {
            abort(404, 'Cotización no encontrada');
        }

        $idProveedor = $request->route('idProveedor');
        if ($idProveedor !== null && !CotizacionProveedor::where('id', $idProveedor)->exists()) {
            abort(404, 'Proveedor no encontrado');
        }

        return $next($request);
    }
}
