<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
    use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabaseSelectionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    /**
     * Mapeo de dominios a nombres de conexiones de base de datos
     */
    private $domainDatabaseMap = [
        'intranetv2.probusiness.pe' => 'mysql', 
        'clientes.probusiness.pe' => 'mysql', 
        'datosprovedor.probusiness.pe' => 'mysql', 
        'tienda.probusiness.pe' => 'mysql', 
        'agentecompras.probusiness.pe' => 'mysql',
        'cargaconsolidada.probusiness.pe' => 'mysql', 
        'qaintranet.probusiness.pe' => 'mysql_qa', 
        'localhost' => 'mysql_local', 

    ];

    public function handle(Request $request, Closure $next)
    {
        // Priorizar el dominio proveniente de Origin/Referer (cuando hay frontend en otro dominio)
        $origin = $request->headers->get('origin');
        $referer = $request->headers->get('referer');

        $sourceHost = null;
        if ($origin) {
            $sourceHost = parse_url($origin, PHP_URL_HOST);
        }
        if (!$sourceHost && $referer) {
            $sourceHost = parse_url($referer, PHP_URL_HOST);
        }

        // Si no hay Origin/Referer válidos, usar el host de la request
        $host = $sourceHost ?: $request->getHost();

        // Extraer solo el dominio (sin puerto o subdirectorios)
        $domain = $this->extractDomain($host);

        // Obtener la conexión de base de datos según el dominio
        $databaseConnection = $this->getDatabaseConnection($domain);
        
        // Establecer la conexión de base de datos por defecto para esta request
        config(['database.default' => $databaseConnection]);
        
        // También puedes establecerlo en DB facade
        DB::setDefaultConnection($databaseConnection);
        
        
        return $next($request);
    }

    /**
     * Extraer el dominio del host
     */
    private function extractDomain($host)
    {
        // Remover el puerto si existe (ej: localhost:8000 -> localhost)
        $domain = explode(':', $host)[0];
        
        // Si tiene www, removerlo
        $domain = preg_replace('/^www\./', '', $domain);
        
        return $domain;
    }

    /**
     * Obtener la conexión de base de datos según el dominio
     */
    private function getDatabaseConnection($domain)
    {
        // Buscar en el mapa de dominios
        if (isset($this->domainDatabaseMap[$domain])) {
            return $this->domainDatabaseMap[$domain];
        }
        
        // Conexión por defecto si no encuentra el dominio (BD de QA)
        return config('database.default', 'mysql_qa');
    }
}
