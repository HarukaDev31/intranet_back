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
        'intranetv2.probusiness.pe' => 'mysql', // Base de datos principal (PROD)
        'probusiness-intranet.com' => 'mysql_qa', // Base de datos de QA
        'localhost' => 'mysql_local', // Para desarrollo
        // Agrega más dominios según necesites
    ];

    public function handle(Request $request, Closure $next)
    {
        // Obtener el host del request
        $host = $request->getHost();
        
        // Extraer solo el dominio (sin puerto o subdirectorios)
        $domain = $this->extractDomain($host);
        
        // Obtener la conexión de base de datos según el dominio
        $databaseConnection = $this->getDatabaseConnection($domain);
        
        // Establecer la conexión de base de datos por defecto para esta request
        config(['database.default' => $databaseConnection]);
        
        // También puedes establecerlo en DB facade
        DB::setDefaultConnection($databaseConnection);
        
        // Log para debugging
        Log::info('Database connection selected', [
            'domain' => $domain,
            'host' => $host,
            'connection' => $databaseConnection
        ]);
        
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
