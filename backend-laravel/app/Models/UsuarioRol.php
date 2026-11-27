<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuarioRol extends Model
{
    protected $table = 'usuario_roles';

    public $timestamps = false;

    public $incrementing = false;

    protected $fillable = ['rol'];
}
