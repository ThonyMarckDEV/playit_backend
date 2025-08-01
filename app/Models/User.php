<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    /**
     * La tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'usuarios';

    /**
     * La clave primaria asociada con la tabla.
     *
     * @var string
     */
    protected $primaryKey = 'idUsuario';

    /**
     * Los atributos que se pueden asignar de manera masiva.
     *
     * @var array<string>
     */
    protected $fillable = [
        'idDatos',
        'idRol',
        'user_code',
        'estado'
    ];

    /**
     * Los atributos que deberían ser ocultados para la serialización.
     *
     * @var array<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Obtener los atributos que deben ser convertidos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

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
        return [
            'rol' => $this->rol()->first()->nombre,
        ];
    }

    /**
     * Relación con los datos personales
     */
    public function datos()
    {
        return $this->belongsTo(Datos::class, 'idDatos', 'idDatos');
    }

    /**
     * Relación con el rol
     */
    public function rol()
    {
        return $this->belongsTo(Rol::class, 'idRol', 'idRol');
    }

     // Sent friend requests
    public function sentFriendRequests()
    {
        return $this->hasMany(SolicitudAmistad::class, 'idUsuario', 'idUsuario');
    }

    // Received friend requests
    public function receivedFriendRequests()
    {
        return $this->hasMany(SolicitudAmistad::class, 'idAmigo', 'idUsuario');
    }

    // Accepted friends (both directions)
    public function friends()
    {
        return $this->belongsToMany(User::class, 'solicitudes_amistad', 'idUsuario', 'idAmigo')
            ->where('status', '1')
            ->union(
                $this->belongsToMany(User::class, 'solicitudes_amistad', 'idAmigo', 'idUsuario')
                    ->where('status', '1')
            );
    }

}