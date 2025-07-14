<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudAmistad extends Model
{
    protected $table = 'solicitudes_amistad';
    protected $primaryKey = 'idSolicitudAmistad';
    public $incrementing = true;

    protected $fillable = ['idUsuario', 'idAmigo', 'status'];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'idUsuario', 'idUsuario');
    }

    public function amigo()
    {
        return $this->belongsTo(User::class, 'idAmigo', 'idUsuario');
    }
}