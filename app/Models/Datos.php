<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Datos extends Model
{
    protected $table = 'datos';
    protected $primaryKey = 'idDatos';
    public $incrementing = true;

    protected $fillable = ['nombre', 'apellidos', 'email', 'perfil'];

    public function user()
    {
        return $this->hasOne(User::class, 'idDatos', 'idDatos');
    }
}