<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Usuario extends Authenticatable implements JWTSubject
{
    protected $table = 'usuario';
    protected $primaryKey = 'ID_Usuario';
    const ROL_COTIZADOR = 'Cotizador';
    const ROL_COORDINACION = 'Coordinación';
    const ROL_ALMACEN_CHINA = 'ContenedorAlmacen';
    const ROL_ADMINISTRACION = 'Administracion';
    const ROL_DOCUMENTACION = 'Documentacion';
    const ROL_CATALOGO_CHINA = 'CatalogoChina';
    protected $fillable = [
        'No_Usuario',
        'No_Password',
        'Nu_Estado',
        'ID_Empresa',
        'ID_Organizacion',
        'ID_Grupo',
        'Fe_Creacion'
    ];

    protected $hidden = [
        'No_Password',
    ];
    const ID_JEFE_VENTAS = 28791;

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Relación con Empresa
     */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'ID_Empresa', 'ID_Empresa');
    }

    /**
     * Relación con Organizacion
     */
    public function organizacion()
    {
        return $this->belongsTo(Organizacion::class, 'ID_Organizacion', 'ID_Organizacion');
    }

    /**
     * Relación directa con Grupo
     */
    public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'ID_Grupo', 'ID_Grupo');
    }

    /**
     * Relación con GrupoUsuario
     */
    public function gruposUsuario()
    {
        return $this->hasMany(GrupoUsuario::class, 'ID_Usuario', 'ID_Usuario');
    }

    /**
     * Relación con Almacen
     */
    public function almacenes()
    {
        return $this->hasManyThrough(
            Almacen::class,
            Organizacion::class,
            'ID_Organizacion',
            'ID_Organizacion',
            'ID_Organizacion'
        );
    }

    /**
     * Obtener todos los grupos del usuario (incluyendo la relación many-to-many)
     */
    public function getAllGrupos()
    {
        $grupos = collect();

        // Agregar el grupo directo si existe
        if ($this->grupo) {
            $grupos->push($this->grupo);
        }

        // Agregar grupos de la relación many-to-many
        $gruposManyToMany = $this->gruposUsuario()->with('grupo')->get()->pluck('grupo');
        $grupos = $grupos->merge($gruposManyToMany);

        return $grupos->unique('ID_Grupo');
    }

    /**
     * Verificar si el usuario pertenece a un grupo específico
     */
    public function perteneceAGrupo($grupoId)
    {
        // Verificar grupo directo
        if ($this->ID_Grupo == $grupoId) {
            return true;
        }

        // Verificar grupos many-to-many
        return $this->gruposUsuario()->where('ID_Grupo', $grupoId)->exists();
    }

    /**
     * Obtener el nombre del grupo principal del usuario
     */
    public function getNombreGrupo()
    {
        return $this->grupo ? $this->grupo->No_Grupo : 'Sin grupo';
    }

    /**
     * Obtener el id del usuario
     */
    public function getIdUsuario()
    {
        return $this->ID_Usuario;
    }

    /**
     * Obtener la descripción del grupo principal del usuario
     */
    public function getDescripcionGrupoPrincipalAttribute()
    {
        return $this->grupo ? $this->grupo->No_Grupo_Descripcion : 'Sin descripción';
    }

    /**
     * Obtener el tipo de privilegio de acceso del grupo principal
     */
    public function getTipoPrivilegioAccesoAttribute()
    {
        return $this->grupo ? $this->grupo->Nu_Tipo_Privilegio_Acceso : null;
    }
}
