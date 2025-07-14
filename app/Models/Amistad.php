<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Amistad extends Model
{
    protected $table = 'amistades';
    protected $primaryKey = 'idAmistad';
    public $incrementing = true;
    public $timestamps = true;

    protected $fillable = [
        'idUsuario',
        'idAmigo',
    ];

    /**
     * Relación con el usuario (primer usuario).
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'idUsuario', 'idUsuario');
    }

    /**
     * Relación con el amigo (segundo usuario).
     */
    public function amigo()
    {
        return $this->belongsTo(User::class, 'idAmigo', 'idUsuario');
    }
}