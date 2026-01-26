<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait DatabaseConnectionTrait
{
    /**
     * Mapeo de dominios a nombres de conexiones de base de datos
     * Debe coincidir con DatabaseSelectionMiddleware
     */
    private function getDomainDatabaseMap()
    {
        return [
            'intranetv2.probusiness.pe' => 'mysql', 
            'clientes.probusiness.pe' => 'mysql', 
            'datosprovedor.probusiness.pe' => 'mysql', 
            'tienda.probusiness.pe' => 'mysql', 
            'agentecompras.probusiness.pe' => 'mysql',
            'cargaconsolidada.probusiness.pe' => 'mysql', 
            'qaintranet.probusiness.pe' => 'mysql_qa', 
            'localhost' => 'mysql_local',
        ];
    }

    /**
     * Obtener la conexión de base de datos según el dominio
     */
    private function getDatabaseConnectionFromDomain($domain)
    {
        $domainDatabaseMap = $this->getDomainDatabaseMap();
        
        // Buscar en el mapa de dominios
        if (isset($domainDatabaseMap[$domain])) {
            return $domainDatabaseMap[$domain];
        }
        
        // Conexión por defecto si no encuentra el dominio (BD de QA)
        return config('database.default', 'mysql_qa');
    }

    /**
     * Establecer la conexión de base de datos basándose en el dominio
     * 
     * @param string|null $domain Dominio del frontend. Si es null, intenta obtenerlo desde la request
     * @return string Nombre de la conexión establecida
     */
    protected function setDatabaseConnection($domain = null)
    {
        try {
            // Si no se proporciona el dominio, intentar obtenerlo desde la request
            if (!$domain) {
                $request = request();
                if ($request) {
                    // Priorizar el dominio proveniente de Origin/Referer
                    $origin = $request->headers->get('origin');
                    $referer = $request->headers->get('referer');

                    $sourceHost = null;
                    if ($origin) {
                        $sourceHost = parse_url($origin, PHP_URL_HOST);
                    }
                    if (!$sourceHost && $referer) {
                        $sourceHost = parse_url($referer, PHP_URL_HOST);
                    }

                    if ($sourceHost) {
                        // Extraer el dominio del host (implementación inline para evitar conflicto con WhatsappTrait)
                        $domain = explode(':', $sourceHost)[0];
                        $domain = preg_replace('/^www\./', '', $domain);
                    }
                }
            }

            // Si aún no hay dominio, usar la conexión actual o por defecto
            if (!$domain) {
                $currentConnection = DB::getDefaultConnection();
                Log::info('No se pudo obtener dominio, usando conexión actual', [
                    'connection' => $currentConnection
                ]);
                return $currentConnection;
            }

            // Obtener la conexión de base de datos según el dominio
            $databaseConnection = $this->getDatabaseConnectionFromDomain($domain);
            
            // Establecer la conexión de base de datos por defecto
            config(['database.default' => $databaseConnection]);
            DB::setDefaultConnection($databaseConnection);
            
            Log::info('Conexión de BD establecida en Job', [
                'domain' => $domain,
                'connection' => $databaseConnection
            ]);
            
            return $databaseConnection;
        } catch (\Exception $e) {
            Log::warning('Error al establecer conexión de BD en Job: ' . $e->getMessage());
            // Retornar la conexión actual como fallback
            return DB::getDefaultConnection();
        }
    }
}

