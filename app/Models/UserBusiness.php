<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserBusiness extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_business';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'ruc',
        'comercial_capacity',
        'rubric',
        'social_address',
    ];

    /**
     * Relación con User por user_id (dueño directo)
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación con User por id_user_business (legacy, uno a muchos)
     */
    public function users()
    {
        return $this->hasMany(User::class, 'id_user_business');
    }
}
