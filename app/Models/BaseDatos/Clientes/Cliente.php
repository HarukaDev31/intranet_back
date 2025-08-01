<?php

namespace App\Models\BaseDatos\Clientes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Cliente extends Model
{
    protected $table = 'clientes';
    
    protected $fillable = [
        'nombre',
        'documento',
        'correo',
        'telefono',
        'fecha'
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    /**
     * Obtener el primer servicio del cliente (el más antiguo)
     */
    public function getPrimerServicioAttribute()
    {
        // Buscar en pedido_curso primero (por fecha de registro)
        $pedidoCurso = DB::table('pedido_curso as pc')
            ->join('entidad as e', 'pc.ID_Entidad', '=', 'e.ID_Entidad')
            ->where('pc.Nu_Estado', 2) // Estado confirmado
            ->where(function($query) {
                $query->where('e.Nu_Celular_Entidad', $this->telefono)
                      ->orWhere('e.Nu_Documento_Identidad', $this->documento)
                      ->orWhere('e.Txt_Email_Entidad', $this->correo);
            })
            ->orderBy('e.Fe_Registro', 'asc')
            ->first();

        if ($pedidoCurso) {
            return [
                'servicio' => 'Curso',
                'fecha' => $pedidoCurso->Fe_Registro,
                'categoria' => $this->determinarCategoria($pedidoCurso->Fe_Registro)
            ];
        }

        // Buscar en contenedor_consolidado_cotizacion
        $cotizacion = DB::table('contenedor_consolidado_cotizacion')
            ->whereNotNull('estado_cliente')
            ->where('estado_cotizador', 'CONFIRMADO')
            ->where(function($query) {
                $query->where('telefono', $this->telefono)
                      ->orWhere('documento', $this->documento)
                      ->orWhere('correo', $this->correo);
            })
            ->orderBy('fecha', 'asc')
            ->first();

        if ($cotizacion) {
            return [
                'servicio' => 'Consolidado',
                'fecha' => $cotizacion->fecha,
                'categoria' => $this->determinarCategoria($cotizacion->fecha)
            ];
        }

        return null;
    }

    /**
     * Obtener todos los servicios del cliente
     */
    public function getServiciosAttribute()
    {
        $servicios = [];

        // Buscar en pedido_curso
        $pedidosCurso = DB::table('pedido_curso as pc')
            ->join('entidad as e', 'pc.ID_Entidad', '=', 'e.ID_Entidad')
            ->where('pc.Nu_Estado', 2) // Estado confirmado
            ->where(function($query) {
                $query->where('e.Nu_Celular_Entidad', $this->telefono)
                      ->orWhere('e.Nu_Documento_Identidad', $this->documento)
                      ->orWhere('e.Txt_Email_Entidad', $this->correo);
            })
            ->where('pc.id_cliente', $this->id)
            ->orderBy('e.Fe_Registro', 'asc')
            ->get();

        foreach ($pedidosCurso as $pedido) {
            $servicios[] = [
                'servicio' => 'Curso',
                'fecha' => $pedido->Fe_Registro,
                'categoria' => $this->determinarCategoria($pedido->Fe_Registro)
            ];
        }

        // Buscar en contenedor_consolidado_cotizacion
        $cotizaciones = DB::table('contenedor_consolidado_cotizacion')
            ->whereNotNull('estado_cliente')
            ->where('estado_cotizador', 'CONFIRMADO')
            ->where(function($query) {
                $query->where('telefono', $this->telefono)
                      ->orWhere('documento', $this->documento)
                      ->orWhere('correo', $this->correo);
            })
            ->where('id_cliente', $this->id)
            ->orderBy('fecha', 'asc')
            ->get();

        foreach ($cotizaciones as $cotizacion) {
            $servicios[] = [
                'servicio' => 'Consolidado',
                'fecha' => $cotizacion->fecha,
                'categoria' => $this->determinarCategoria($cotizacion->fecha)
            ];
        }

        return $servicios;
    }

    /**
     * Obtener servicios del cliente sin categorización (para evitar recursión)
     */
    private function obtenerServiciosSinCategoria()
    {
        $servicios = [];

        // Buscar en pedido_curso
        $pedidosCurso = DB::table('pedido_curso as pc')
            ->join('entidad as e', 'pc.ID_Entidad', '=', 'e.ID_Entidad')
            ->where('pc.Nu_Estado', 2) // Estado confirmado
            ->where(function($query) {
                $query->where('e.Nu_Celular_Entidad', $this->telefono)
                      ->orWhere('e.Nu_Documento_Identidad', $this->documento)
                      ->orWhere('e.Txt_Email_Entidad', $this->correo);
            })
            ->where('pc.id_cliente', $this->id)
            ->orderBy('e.Fe_Registro', 'asc')
            ->get();

        foreach ($pedidosCurso as $pedido) {
            $servicios[] = [
                'servicio' => 'Curso',
                'fecha' => $pedido->Fe_Registro
            ];
        }

        // Buscar en contenedor_consolidado_cotizacion
        $cotizaciones = DB::table('contenedor_consolidado_cotizacion')
            ->whereNotNull('estado_cliente')
            ->where('estado_cotizador', 'CONFIRMADO')
            ->where(function($query) {
                $query->where('telefono', $this->telefono)
                      ->orWhere('documento', $this->documento)
                      ->orWhere('correo', $this->correo);
            })
            ->where('id_cliente', $this->id)
            ->orderBy('fecha', 'asc')
            ->get();

        foreach ($cotizaciones as $cotizacion) {
            $servicios[] = [
                'servicio' => 'Consolidado',
                'fecha' => $cotizacion->fecha
            ];
        }

        return $servicios;
    }

    /**
     * Determinar la categoría del cliente basada en su historial de servicios
     */
    private function determinarCategoria($fechaPrimerServicio)
    {
        // Obtener todos los servicios del cliente sin categorización
        $servicios = $this->obtenerServiciosSinCategoria();
        $totalServicios = count($servicios);
        
        if ($totalServicios === 0) {
            return 'Inactivo';
        }
        
        if ($totalServicios === 1) {
            return 'Cliente';
        }
        
        // Obtener la fecha del último servicio
        $ultimoServicio = end($servicios);
        $fechaUltimoServicio = \Carbon\Carbon::parse($ultimoServicio['fecha']);
        $hoy = \Carbon\Carbon::now();
        $mesesDesdeUltimaCompra = $fechaUltimoServicio->diffInMonths($hoy);
        
        // Si la última compra fue hace más de 6 meses, es Inactivo
        if ($mesesDesdeUltimaCompra > 6) {
            return 'Inactivo';
        }
        
        // Para clientes con múltiples servicios
        if ($totalServicios >= 2) {
            // Calcular frecuencia promedio de compras
            $fechaPrimerServicio = \Carbon\Carbon::parse($fechaPrimerServicio);
            $mesesEntrePrimeraYUltima = $fechaPrimerServicio->diffInMonths($fechaUltimoServicio);
            $frecuenciaPromedio = $mesesEntrePrimeraYUltima / ($totalServicios - 1);
            
            // Si compra cada 2 meses o menos Y la última compra fue hace ≤ 2 meses
            if ($frecuenciaPromedio <= 2 && $mesesDesdeUltimaCompra <= 2) {
                return 'Premium';
            }
            // Si tiene múltiples compras Y la última fue hace ≤ 6 meses
            else if ($mesesDesdeUltimaCompra <= 6) {
                return 'Recurrente';
            }
        }
        
        return 'Inactivo';
    }

    /**
     * Scope para buscar clientes por término
     */
    public function scopeBuscar($query, $termino)
    {
        return $query->where(function($q) use ($termino) {
            $q->where('nombre', 'LIKE', "%{$termino}%")
              ->orWhere('documento', 'LIKE', "%{$termino}%")
              ->orWhere('correo', 'LIKE', "%{$termino}%")
              ->orWhere('telefono', 'LIKE', "%{$termino}%");
        });
    }

    /**
     * Scope para filtrar por servicio
     */
    public function scopePorServicio($query, $servicio)
    {
        return $query->where(function($q) use ($servicio) {
            // Buscar en pedido_curso
            $q->whereRaw('EXISTS (
                SELECT 1 FROM pedido_curso pc 
                JOIN entidad e ON pc.ID_Entidad = e.ID_Entidad 
                WHERE pc.Nu_Estado = 2 
                AND (e.Nu_Celular_Entidad = clientes.telefono 
                     OR e.Nu_Documento_Identidad = clientes.documento 
                     OR e.Txt_Email_Entidad = clientes.correo)
                AND ? = "Curso"
            )', [$servicio])
            // Buscar en contenedor_consolidado_cotizacion
            ->orWhereRaw('EXISTS (
                SELECT 1 FROM contenedor_consolidado_cotizacion 
                WHERE estado_cotizador = "CONFIRMADO" 
                AND (telefono = clientes.telefono 
                     OR documento = clientes.documento 
                     OR correo = clientes.correo)
                AND ? = "Consolidado"
            )', [$servicio]);
        });
    }

        /**
     * Scope para filtrar por categoría (optimizado)
     */
    public function scopePorCategoria($query, $categoria)
    {
        // Si la categoría es "Cliente", usar una consulta más simple
        if ($categoria === 'Cliente') {
            return $query->where(function($q) {
                $q->whereRaw('(
                    (SELECT COUNT(*) FROM pedido_curso pc 
                     JOIN entidad e ON pc.ID_Entidad = e.ID_Entidad 
                     WHERE pc.Nu_Estado = 2 
                     AND (e.Nu_Celular_Entidad = clientes.telefono 
                          OR e.Nu_Documento_Identidad = clientes.documento 
                          OR e.Txt_Email_Entidad = clientes.correo)
                    ) +
                    (SELECT COUNT(*) FROM contenedor_consolidado_cotizacion 
                     WHERE estado_cotizador = "CONFIRMADO" 
                     AND (telefono = clientes.telefono 
                          OR documento = clientes.documento 
                          OR correo = clientes.correo)
                    ) = 1
                )');
            });
        }

        // Para otras categorías, usar una consulta más eficiente
        return $query->where(function($q) use ($categoria) {
            $q->whereRaw('EXISTS (
                SELECT 1 FROM (
                    SELECT 
                        COUNT(*) as total_servicios,
                        MAX(fecha_servicio) as ultima_fecha,
                        MIN(fecha_servicio) as primera_fecha
                    FROM (
                        SELECT e.Fe_Registro as fecha_servicio
                        FROM pedido_curso pc 
                        JOIN entidad e ON pc.ID_Entidad = e.ID_Entidad 
                        WHERE pc.Nu_Estado = 2 
                        AND (e.Nu_Celular_Entidad = clientes.telefono 
                             OR e.Nu_Documento_Identidad = clientes.documento 
                             OR e.Txt_Email_Entidad = clientes.correo)
                        
                        UNION ALL
                        
                        SELECT fecha as fecha_servicio
                        FROM contenedor_consolidado_cotizacion 
                        WHERE estado_cotizador = "CONFIRMADO" 
                        AND (telefono = clientes.telefono 
                             OR documento = clientes.documento 
                             OR correo = clientes.correo)
                    ) servicios_combinados
                ) stats
                WHERE ? = (
                    CASE 
                        WHEN total_servicios = 1 THEN "Cliente"
                        WHEN total_servicios >= 2 AND TIMESTAMPDIFF(MONTH, ultima_fecha, NOW()) <= 6 THEN "Recurrente"
                        WHEN total_servicios >= 2 
                             AND TIMESTAMPDIFF(MONTH, ultima_fecha, NOW()) <= 2 
                             AND (TIMESTAMPDIFF(MONTH, primera_fecha, ultima_fecha) / (total_servicios - 1)) <= 2 
                        THEN "Premium"
                        ELSE "Inactivo"
                    END
                )
            )', [$categoria]);
        });
    }

    /**
     * Scope para filtrar clientes recurrentes (optimizado)
     */
    public function scopeRecurrentes($query)
    {
        return $query->whereRaw('EXISTS (
            SELECT 1 FROM (
                SELECT COUNT(*) as total_servicios
                FROM (
                    SELECT 1
                    FROM pedido_curso pc 
                    JOIN entidad e ON pc.ID_Entidad = e.ID_Entidad 
                    WHERE pc.Nu_Estado = 2 
                    AND (e.Nu_Celular_Entidad = clientes.telefono 
                         OR e.Nu_Documento_Identidad = clientes.documento 
                         OR e.Txt_Email_Entidad = clientes.correo)
                    
                    UNION ALL
                    
                    SELECT 1
                    FROM contenedor_consolidado_cotizacion 
                    WHERE estado_cotizador = "CONFIRMADO" 
                    AND (telefono = clientes.telefono 
                         OR documento = clientes.documento 
                         OR correo = clientes.correo)
                ) servicios_combinados
            ) stats
            WHERE total_servicios > 1
        )');
    }
} 