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
        'ruc',
        'empresa',
        'correo',
        'telefono',
        'fecha',
        'id_cliente_importacion',
        'id'
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
            ->where(function ($query) {
                // Validar que el teléfono no sea nulo o vacío antes de procesar
                if (!empty($this->telefono) && $this->telefono !== null) {
                    $query->where('e.Nu_Celular_Entidad', $this->telefono);
                }
                
                // Validar que el documento no sea nulo o vacío antes de procesar
                if (!empty($this->documento) && $this->documento !== null) {
                    $query->orWhere('e.Nu_Documento_Identidad', $this->documento);
                }
                
                // Validar que el correo no sea nulo o vacío antes de procesar
                if (!empty($this->correo) && $this->correo !== null) {
                    $query->orWhere(function($q) {
                        $q->whereNotNull('e.Txt_Email_Entidad')
                          ->where('e.Txt_Email_Entidad', '!=', '')
                          ->where('e.Txt_Email_Entidad', $this->correo);
                    });
                }
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
            ->join('carga_consolidada_contenedor', 'contenedor_consolidado_cotizacion.id_contenedor', '=', 'carga_consolidada_contenedor.id')
            ->whereNotNull('estado_cliente')
            ->where('estado_cotizador', 'CONFIRMADO')
            ->where(function ($query) {
                // Validar que el teléfono no sea nulo o vacío antes de procesar
                if (!empty($this->telefono) && $this->telefono !== null) {
                    $telefonoLimpio = preg_replace('/[^0-9]/', '', $this->telefono);
                    $query->where('telefono', 'LIKE', "%{$telefonoLimpio}%")
                        ->orWhere('telefono', 'LIKE', "%" . str_replace(' ', '', $telefonoLimpio) . "%")
                        ->orWhere('telefono', 'LIKE', "%51 {$telefonoLimpio}%")
                        ->orWhere('telefono', 'LIKE', "%51" . str_replace(' ', '', $telefonoLimpio) . "%")
                        ->orWhere('telefono', 'LIKE', "%51 " . str_replace(' ', '', $telefonoLimpio) . "%");
                }
                
                // Validar que el documento no sea nulo o vacío antes de procesar
                if (!empty($this->documento) && $this->documento !== null) {
                    $query->orWhere('documento', $this->documento);
                }
                
                // Validar que el correo no sea nulo o vacío antes de procesar
                if (!empty($this->correo) && $this->correo !== null) {
                    $query->orWhere(function($q) {
                        $q->whereNotNull('correo')
                          ->where('correo', '!=', '')
                          ->where('correo', $this->correo);
                    });
                }
            })
            ->orderBy('fecha', 'asc')
            ->orderByRaw('CAST(carga_consolidada_contenedor.carga AS UNSIGNED)')
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
            ->leftJoin('campana_curso as cc', 'pc.ID_Campana', '=', 'cc.ID_Campana')
            ->where('pc.Nu_Estado', 2) // Estado confirmado
            ->where(function ($query) {
                // Validar que el teléfono no sea nulo o vacío antes de procesar
                if (!empty($this->telefono) && $this->telefono !== null) {
                    $query->where(DB::raw('REPLACE(TRIM(e.Nu_Celular_Entidad), " ", "")'), 'LIKE', "%{$this->telefono}%");
                }
                
                // Validar que el documento no sea nulo o vacío antes de procesar
                if (!empty($this->documento) && $this->documento !== null) {
                    $query->orWhere(function($q) {
                        $q->whereNotNull('e.Nu_Documento_Identidad')
                          ->where('e.Nu_Documento_Identidad', '!=', '')
                          ->where('e.Nu_Documento_Identidad', $this->documento);
                    });
                }
                
                // Validar que el correo no sea nulo o vacío antes de procesar
                if (!empty($this->correo) && $this->correo !== null) {
                    $query->orWhere(function($q) {
                        $q->whereNotNull('e.Txt_Email_Entidad')
                          ->where('e.Txt_Email_Entidad', '!=', '')
                          ->where('e.Txt_Email_Entidad', $this->correo);
                    });
                }
            })
            ->where('pc.id_cliente', $this->id)
            ->orderBy('e.Fe_Registro', 'asc')
            ->get();
        //from Fe_Inicio get month in spanish
        foreach ($pedidosCurso as $pedido) {
            $mes = \Carbon\Carbon::parse($pedido->Fe_Inicio)->locale('es')->monthName;

            $servicios[] = [
                'id' => $pedido->ID_Pedido_Curso,
                'is_imported' => $pedido->id_cliente_importacion ? 1 : 0,
                'detalle' => $mes,
                'monto' => $pedido->Ss_Total,
                'servicio' => 'Curso',
                'carga' => null,
                'empresa' => null,
                'fecha' => $pedido->Fe_Registro,
                'categoria' => $this->determinarCategoria($pedido->Fe_Registro)
            ];
        }

        // Buscar en contenedor_consolidado_cotizacion
        $cotizaciones = DB::table('contenedor_consolidado_cotizacion')
            ->join('carga_consolidada_contenedor', 'contenedor_consolidado_cotizacion.id_contenedor', '=', 'carga_consolidada_contenedor.id')
            ->whereNotNull('estado_cliente')
            ->where('estado_cotizador', 'CONFIRMADO')
            ->where(function ($query) {
                // Validar que el teléfono no sea nulo o vacío antes de procesar
                if (!empty($this->telefono) && $this->telefono !== null) {
                    $query->where(DB::raw('REPLACE(TRIM(telefono), " ", "")'), 'LIKE', "%{$this->telefono}%");
                }
                
                // Validar que el documento no sea nulo o vacío antes de procesar
                if (!empty($this->documento) && $this->documento !== null) {
                    $query->orWhere(function($q) {
                        $q->whereNotNull('documento')
                          ->where('documento', '!=', '')
                          ->where('documento', $this->documento);
                    });
                }
                
                // Validar que el correo no sea nulo o vacío antes de procesar
                if (!empty($this->correo) && $this->correo !== null) {
                    $query->orWhere(function($q) {
                        $q->whereNotNull('correo')
                          ->where('correo', '!=', '')
                          ->where('correo', $this->correo);
                    });
                }
            })
       
            ->orderBy('fecha', 'asc')
            ->orderByRaw('CAST(carga_consolidada_contenedor.carga AS UNSIGNED)')
            ->select(
                'contenedor_consolidado_cotizacion.*', 
                'carga_consolidada_contenedor.carga', 
                'carga_consolidada_contenedor.empresa',
                'carga_consolidada_contenedor.id as id_contenedor'
            )
            ->get();

        foreach ($cotizaciones as $cotizacion) {
            $servicios[] = [
                'id' => $cotizacion->id,
                'monto' => $cotizacion->monto,
                'is_imported' => $cotizacion->id_cliente_importacion ? 1 : 0,
                'servicio' => 'Consolidado',
                'detalle' => $cotizacion->carga,
                'carga' => $cotizacion->carga,
                'empresa' => $cotizacion->empresa,
                'fecha' => $cotizacion->fecha,
                'categoria' => $this->determinarCategoria($cotizacion->fecha)
            ];
        }
        
        // Ordenamiento personalizado
        usort($servicios, function ($a, $b) {
            $esConsolidadoA = $a['servicio'] === 'Consolidado';
            $esConsolidadoB = $b['servicio'] === 'Consolidado';
            
            // Determinar si es consolidado 2025 con carga >= 6
            $esConsolidado2025CargaAltaA = false;
            $esConsolidado2025CargaAltaB = false;
            
            if ($esConsolidadoA) {
                $cargaA = intval($a['carga'] ?? 0);
                $anioA = date('Y', strtotime($a['fecha']));
                $esConsolidado2025CargaAltaA = ($anioA == '2025' && $cargaA >= 6);
            }
            
            if ($esConsolidadoB) {
                $cargaB = intval($b['carga'] ?? 0);
                $anioB = date('Y', strtotime($b['fecha']));
                $esConsolidado2025CargaAltaB = ($anioB == '2025' && $cargaB >= 6);
            }
            
            // Si ambos son consolidados 2025 con carga >= 6, ordenar por carga DESC
            if ($esConsolidado2025CargaAltaA && $esConsolidado2025CargaAltaB) {
                $cargaA = intval($a['carga'] ?? 0);
                $cargaB = intval($b['carga'] ?? 0);
                
                if ($cargaA != $cargaB) {
                    return $cargaB - $cargaA; // DESC: carga mayor primero
                }
                
                // Si tienen la misma carga, ordenar por fecha DESC
                $fechaA = strtotime($a['fecha']);
                $fechaB = strtotime($b['fecha']);
                return $fechaB - $fechaA;
            }
            
            // Si solo A es consolidado 2025 con carga >= 6, va primero
            if ($esConsolidado2025CargaAltaA && !$esConsolidado2025CargaAltaB) {
                return -1;
            }
            
            // Si solo B es consolidado 2025 con carga >= 6, va primero
            if (!$esConsolidado2025CargaAltaA && $esConsolidado2025CargaAltaB) {
                return 1;
            }
            
            // Todo lo demás (cursos, consolidados con carga < 6, consolidados de otros años), ordenar por fecha DESC
            $fechaA = strtotime($a['fecha']);
            $fechaB = strtotime($b['fecha']);
            return $fechaB - $fechaA;
        });
        
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
            ->where(function ($query) {
                // Validar que el teléfono no sea nulo o vacío antes de procesar
                if (!empty($this->telefono) && $this->telefono !== null) {
                    $query->where(DB::raw('REPLACE(TRIM(e.Nu_Celular_Entidad), " ", "")'), 'LIKE', "%{$this->telefono}%");
                }
                
                // Validar que el documento no sea nulo o vacío antes de procesar
                if (!empty($this->documento) && $this->documento !== null) {
                    $query->orWhere(function($q) {
                        $q->whereNotNull('e.Nu_Documento_Identidad')
                          ->where('e.Nu_Documento_Identidad', '!=', '')
                          ->where('e.Nu_Documento_Identidad', $this->documento);
                    });
                }
                
                // Validar que el correo no sea nulo o vacío antes de procesar
                if (!empty($this->correo) && $this->correo !== null) {
                    $query->orWhere(function($q) {
                        $q->whereNotNull('e.Txt_Email_Entidad')
                          ->where('e.Txt_Email_Entidad', '!=', '')
                          ->where('e.Txt_Email_Entidad', $this->correo);
                    });
                }
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
            ->join('carga_consolidada_contenedor', 'contenedor_consolidado_cotizacion.id_contenedor', '=', 'carga_consolidada_contenedor.id')
            ->whereNotNull('estado_cliente')
            ->where('estado_cotizador', 'CONFIRMADO')
            ->where(function ($query) {
                // Validar que el teléfono no sea nulo o vacío antes de procesar
                if (!empty($this->telefono) && $this->telefono !== null) {
                    $query->where(DB::raw('REPLACE(TRIM(telefono), " ", "")'), 'LIKE', "%{$this->telefono}%");
                }
                
                // Validar que el documento no sea nulo o vacío antes de procesar
                if (!empty($this->documento) && $this->documento !== null) {
                    $query->orWhere('documento', $this->documento);
                }
                
                // Validar que el correo no sea nulo o vacío antes de procesar
                if (!empty($this->correo) && $this->correo !== null) {
                    $query->orWhere(function($q) {
                        $q->whereNotNull('correo')
                          ->where('correo', '!=', '')
                          ->where('correo', $this->correo);
                    });
                }
            })
            ->where('id_cliente', $this->id)
            ->orderBy('fecha', 'asc')
            ->orderByRaw('CAST(carga_consolidada_contenedor.carga AS UNSIGNED)')
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
     * Scope para buscar clientes por término (número, correo, documento o teléfono)
     * Trim tanto de BD como del request y usa LIKE
     */
    public function scopeBuscar($query, $termino)
    {
        // Trim del término de búsqueda
        $termino = trim($termino);
        
        if (empty($termino)) {
            return $query;
        }
        
        return $query->where(function ($q) use ($termino) {
            // Búsqueda en nombre (con TRIM de BD)
            $q->whereRaw('TRIM(nombre) LIKE ?', ["%{$termino}%"])
                // Búsqueda en documento (con TRIM de BD)
                ->orWhereRaw('TRIM(documento) LIKE ?', ["%{$termino}%"])
                // Búsqueda en correo (con TRIM de BD)
                ->orWhereRaw('TRIM(correo) LIKE ?', ["%{$termino}%"]);
                
            // Si el término parece ser un teléfono (contiene solo números, espacios, guiones, etc.)
            if (preg_match('/^[\d\s\-\(\)\.\+]+$/', $termino)) {
                // Normalizar el término de búsqueda (remover espacios, guiones, paréntesis, puntos y +)
                $telefonoNormalizado = preg_replace('/[\s\-\(\)\.\+]/', '', $termino);
                
                // Si empieza con 51 y tiene más de 9 dígitos, remover prefijo
                if (preg_match('/^51(\d{9})$/', $telefonoNormalizado, $matches)) {
                    $telefonoNormalizado = $matches[1];
                }
                
                if (!empty($telefonoNormalizado)) {
                    // Buscar coincidencias flexibles en teléfono
                    $q->orWhere(function($subQuery) use ($telefonoNormalizado, $termino) {
                        // Búsqueda por teléfono normalizado (eliminar espacios, guiones, etc. de BD)
                        $subQuery->whereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(telefono), " ", ""), "-", ""), "(", ""), ")", ""), "+", "") LIKE ?', ["%{$telefonoNormalizado}%"])
                            // Búsqueda con prefijo 51
                            ->orWhereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(telefono), " ", ""), "-", ""), "(", ""), ")", ""), "+", "") LIKE ?', ["%51{$telefonoNormalizado}%"])
                            // Búsqueda del término original también (con TRIM)
                            ->orWhereRaw('TRIM(telefono) LIKE ?', ["%{$termino}%"]);
                    });
                }
            } else {
                // Si no parece teléfono, hacer búsqueda normal en teléfono (con TRIM)
                $q->orWhereRaw('TRIM(telefono) LIKE ?', ["%{$termino}%"]);
            }
        });
    }

    /**
     * Scope para filtrar por servicio (versión optimizada para paginación)
     */
    public function scopePorServicio($query, $servicio)
    {
        if ($servicio === 'Curso') {
            return $query->whereIn('id', function ($subQuery) {
                $subQuery->select('pc.id_cliente')
                    ->from('pedido_curso as pc')
                    ->where('pc.Nu_Estado', 2)
                    ->whereNotNull('pc.id_cliente');
            });
        } elseif ($servicio === 'Consolidado') {
            return $query->whereIn('id', function ($subQuery) {
                $subQuery->select('id_cliente')
                    ->from('contenedor_consolidado_cotizacion')
                    ->where('estado_cotizador', 'CONFIRMADO')
                    ->whereNotNull('id_cliente');
            });
        }

        return $query;
    }

    /**
     * Scope para filtrar por categoría (optimizado)
     */
    public function scopePorCategoria($query, $categoria)
    {
        // Si la categoría es "Cliente", usar una consulta más simple
        if ($categoria === 'Cliente') {
            return $query->where(function ($q) {
                $q->whereRaw('(
                    (SELECT COUNT(*) FROM pedido_curso pc 
                     JOIN entidad e ON pc.ID_Entidad = e.ID_Entidad 
                     WHERE pc.Nu_Estado = 2 
                     AND (REPLACE(TRIM(e.Nu_Celular_Entidad), " ", "") = REPLACE(TRIM(clientes.telefono), " ", "") 
                          OR (e.Nu_Documento_Identidad IS NOT NULL AND e.Nu_Documento_Identidad != \'\' AND e.Nu_Documento_Identidad = clientes.documento)
                          OR (e.Txt_Email_Entidad IS NOT NULL AND e.Txt_Email_Entidad != \'\' AND e.Txt_Email_Entidad = clientes.correo))
                    ) +
                    (SELECT COUNT(*) FROM contenedor_consolidado_cotizacion 
                     WHERE estado_cotizador = "CONFIRMADO" 
                     AND (REPLACE(TRIM(telefono), " ", "") = REPLACE(TRIM(clientes.telefono), " ", "") 
                          OR (documento IS NOT NULL AND documento != \'\' AND documento = clientes.documento)
                          OR (correo IS NOT NULL AND correo != \'\' AND correo = clientes.correo))
                    ) = 1
                )');
            });
        }

        // Para otras categorías, usar una consulta más eficiente
        return $query->where(function ($q) use ($categoria) {
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
                        AND (REPLACE(TRIM(e.Nu_Celular_Entidad), " ", "") = REPLACE(TRIM(clientes.telefono), " ", "") 
                             OR e.Nu_Documento_Identidad = clientes.documento 
                             OR (e.Txt_Email_Entidad IS NOT NULL AND e.Txt_Email_Entidad != \'\' AND e.Txt_Email_Entidad = clientes.correo))
                        
                        UNION ALL
                        
                        SELECT fecha as fecha_servicio
                        FROM contenedor_consolidado_cotizacion 
                        WHERE estado_cotizador = "CONFIRMADO" 
                        AND (REPLACE(TRIM(telefono), " ", "") = REPLACE(TRIM(clientes.telefono), " ", "") 
                             OR documento = clientes.documento 
                             OR (correo IS NOT NULL AND correo != \'\' AND correo = clientes.correo))
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
                    AND (REPLACE(TRIM(e.Nu_Celular_Entidad), " ", "") = REPLACE(TRIM(clientes.telefono), " ", "") 
                         OR e.Nu_Documento_Identidad = clientes.documento 
                         OR e.Txt_Email_Entidad = clientes.correo)
                    
                    UNION ALL
                    
                    SELECT 1
                    FROM contenedor_consolidado_cotizacion 
                    WHERE estado_cotizador = "CONFIRMADO" 
                    AND (REPLACE(TRIM(telefono), " ", "") = REPLACE(TRIM(clientes.telefono), " ", "") 
                         OR documento = clientes.documento 
                         OR correo = clientes.correo)
                ) servicios_combinados
            ) stats
            WHERE total_servicios > 1
        )');
    }
}
