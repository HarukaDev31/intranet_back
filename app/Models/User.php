<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'lastname',
        'email',
        'password',
        'whatsapp',
        'photo_url',
        'goals',
        'age',
        'country',
        'id_user_business',
        'api_token',
        'dni',
        'birth_date',
        'pais_id',
        'provincia_id',
        'departamento_id',
        'distrito_id',
        'goals'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'birth_date' => 'date',
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
     * Relación con UserBusiness
     */
    public function userBusiness()
    {
        return $this->belongsTo(UserBusiness::class, 'id_user_business');
    }

    /**
     * Relación con Pais
     */
    public function pais()
    {
    return $this->belongsTo(Pais::class, 'pais_id', 'ID_Pais');
    }

    /**
     * Relación con Departamento
     */
    public function departamento()
    {
        return $this->belongsTo(Departamento::class, 'departamento_id', 'ID_Departamento');
    }

    /**
     * Relación con Provincia
     */
    public function provincia()
    {
        return $this->belongsTo(Provincia::class, 'provincia_id', 'ID_Provincia');
    }

    /**
     * Relación con Distrito
     */
    public function distrito()
    {
        return $this->belongsTo(Distrito::class, 'distrito_id', 'ID_Distrito');
    }
    

    /**
     * Obtener el nombre completo del usuario
     */
    public function getFullNameAttribute()
    {
        return trim($this->name . ' ' . $this->lastname);
    }
}
