<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class Notificacion extends Model
{
    use HasFactory;

    protected $table = 'notificaciones';

    protected $fillable = [
        'titulo',
        'mensaje',
        'descripcion',
        'configuracion_roles',
        'modulo',
        'rol_destinatario',
        'usuario_destinatario',
        'navigate_to',
        'navigate_params',
        'tipo',
        'icono',
        'prioridad',
        'referencia_tipo',
        'referencia_id',
        'activa',
        'fecha_expiracion',
        'creado_por'
    ];

    protected $casts = [
        'configuracion_roles' => 'array',
        'navigate_params' => 'array',
        'activa' => 'boolean',
        'fecha_expiracion' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Constantes para módulos
    const MODULO_BASE_DATOS = 'BaseDatos';
    const MODULO_CARGA_CONSOLIDADA = 'CargaConsolidada';
    const MODULO_CURSOS = 'Cursos';
    const MODULO_CALCULADORA_IMPORTACION = 'CalculadoraImportacion';
    const MODULO_ADMINISTRACION = 'Administracion';

    // Constantes para tipos
    const TIPO_INFO = 'info';
    const TIPO_SUCCESS = 'success';
    const TIPO_WARNING = 'warning';
    const TIPO_ERROR = 'error';

    // Constantes para prioridades
    const PRIORIDAD_BAJA = 1;
    const PRIORIDAD_MEDIA = 3;
    const PRIORIDAD_ALTA = 5;

    /**
     * Relación con el usuario que creó la notificación
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por', 'ID_Usuario');
    }

    /**
     * Relación con el usuario destinatario específico (si aplica)
     */
    public function usuarioDestinatario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_destinatario', 'ID_Usuario');
    }

    /**
     * Relación many-to-many con usuarios a través de la tabla pivot
     */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(
            Usuario::class,
            'notificacion_usuario',
            'notificacion_id',
            'usuario_id'
        )->withPivot(['leida', 'fecha_lectura', 'archivada', 'fecha_archivado'])
          ->withTimestamps();
    }

    /**
     * Scope para notificaciones activas
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }

    /**
     * Scope para notificaciones no expiradas
     */
    public function scopeNoExpiradas(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('fecha_expiracion')
              ->orWhere('fecha_expiracion', '>', now());
        });
    }

    /**
     * Scope para notificaciones por módulo
     */
    public function scopePorModulo(Builder $query, string $modulo): Builder
    {
        return $query->where('modulo', $modulo);
    }

    /**
     * Scope para notificaciones por rol
     */
    public function scopePorRol(Builder $query, string $rol): Builder
    {
        return $query->where(function ($q) use ($rol) {
            $q->whereNull('rol_destinatario')
              ->orWhere('rol_destinatario', $rol);
        });
    }

    /**
     * Scope para notificaciones por usuario
     */
    public function scopePorUsuario(Builder $query, int $usuarioId): Builder
    {
        return $query->where(function ($q) use ($usuarioId) {
            $q->whereNull('usuario_destinatario')
              ->orWhere('usuario_destinatario', $usuarioId);
        });
    }

    /**
     * Scope para notificaciones por prioridad
     */
    public function scopePorPrioridad(Builder $query, int $prioridad): Builder
    {
        return $query->where('prioridad', '>=', $prioridad);
    }

    /**
     * Obtener el texto personalizado para un rol específico
     */
    public function getTextoParaRol(string $rol): array
    {
        $configuracion = $this->configuracion_roles ?? [];
        
        if (isset($configuracion[$rol])) {
            return [
                'titulo' => $configuracion[$rol]['titulo'] ?? $this->titulo,
                'mensaje' => $configuracion[$rol]['mensaje'] ?? $this->mensaje,
                'descripcion' => $configuracion[$rol]['descripcion'] ?? $this->descripcion
            ];
        }

        return [
            'titulo' => $this->titulo,
            'mensaje' => $this->mensaje,
            'descripcion' => $this->descripcion
        ];
    }

    /**
     * Verificar si la notificación está expirada
     */
    public function estaExpirada(): bool
    {
        if (!$this->fecha_expiracion) {
            return false;
        }

        return $this->fecha_expiracion->isPast();
    }

    /**
     * Verificar si un usuario ha leído la notificación
     */
    public function fueLeida(int $usuarioId): bool
    {
        return $this->usuarios()
            ->wherePivot('usuario_id', $usuarioId)
            ->wherePivot('leida', true)
            ->exists();
    }

    /**
     * Marcar como leída para un usuario
     */
    public function marcarComoLeida(int $usuarioId): void
    {
        $this->usuarios()->syncWithoutDetaching([
            $usuarioId => [
                'leida' => true,
                'fecha_lectura' => now(),
                'updated_at' => now()
            ]
        ]);
    }

    /**
     * Marcar como archivada para un usuario
     */
    public function archivar(int $usuarioId): void
    {
        $this->usuarios()->syncWithoutDetaching([
            $usuarioId => [
                'archivada' => true,
                'fecha_archivado' => now(),
                'updated_at' => now()
            ]
        ]);
    }

    /**
     * Obtener notificaciones para un usuario específico
     */
    public static function paraUsuario(Usuario $usuario, array $filtros = [])
    {
        $query = static::activas()
            ->noExpiradas()
            ->where(function ($q) use ($usuario) {
                // La notificación debe cumplir: (usuario_destinatario IS NULL OR usuario_destinatario = usuario_id)
                // Y (rol_destinatario IS NULL OR rol_destinatario = rol_usuario)
                $q->where(function ($subQ) use ($usuario) {
                    $subQ->whereNull('usuario_destinatario')
                        ->orWhere('usuario_destinatario', $usuario->ID_Usuario);
                });
                
                // Filtrar por rol si el usuario tiene grupo
                if ($usuario->grupo) {
                    $rolUsuario = $usuario->grupo->No_Grupo;
                    Log::info('Filtrando notificaciones por rol', [
                        'usuario_id' => $usuario->ID_Usuario,
                        'rol_usuario' => $rolUsuario,
                        'rol_coordinacion' => Usuario::ROL_COORDINACION,
                        'rol_cotizador' => Usuario::ROL_COTIZADOR,
                        'coincide_coordinacion' => $rolUsuario === Usuario::ROL_COORDINACION,
                        'coincide_cotizador' => $rolUsuario === Usuario::ROL_COTIZADOR,
                    ]);
                    
                    $q->where(function ($subQ) use ($rolUsuario) {
                        $subQ->whereNull('rol_destinatario')
                            ->orWhere('rol_destinatario', $rolUsuario);
                    });
                } else {
                    // Si no tiene grupo, solo mostrar notificaciones sin rol específico
                    $q->whereNull('rol_destinatario');
                }
            });

        // Aplicar filtros adicionales
        if (isset($filtros['modulo'])) {
            $query->porModulo($filtros['modulo']);
        }

        if (isset($filtros['tipo'])) {
            $query->where('tipo', $filtros['tipo']);
        }

        if (isset($filtros['prioridad_minima'])) {
            $query->porPrioridad($filtros['prioridad_minima']);
        }

        if (isset($filtros['no_leidas']) && $filtros['no_leidas']) {
            $query->whereDoesntHave('usuarios', function ($q) use ($usuario) {
                $q->where('usuario_id', $usuario->ID_Usuario)
                  ->where('leida', true);
            });
        }

        // Log del SQL generado para debugging
        Log::info('SQL de notificaciones generado', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
        ]);
        
        // Clonar la query para debugging sin afectar la query principal
        $queryDebug = clone $query;
        $resultados = $queryDebug->get();
        Log::info('Notificaciones encontradas antes de ordenar', [
            'total' => $resultados->count(),
            'notificaciones' => $resultados->map(function ($notif) {
                return [
                    'id' => $notif->id,
                    'titulo' => $notif->titulo,
                    'rol_destinatario' => $notif->rol_destinatario,
                    'usuario_destinatario' => $notif->usuario_destinatario,
                    'activa' => $notif->activa,
                    'referencia_tipo' => $notif->referencia_tipo
                ];
            })->toArray()
        ]);

        return $query->orderByDesc('prioridad')
            ->orderByDesc('created_at');
    }
}
