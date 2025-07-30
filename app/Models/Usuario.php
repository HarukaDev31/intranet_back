<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Usuario extends Authenticatable implements JWTSubject
{
    protected $table = 'usuario';
    protected $primaryKey = 'ID_Usuario';
    
    protected $fillable = [
        'No_Usuario',
        'No_Password',
        'Nu_Estado',
        'ID_Empresa',
        'ID_Organizacion',
        'Fe_Creacion'
    ];

    protected $hidden = [
        'No_Password',
    ];

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
} 