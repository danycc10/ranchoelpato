<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AjusteSistema extends Model
{
    protected $table = 'ajustes_sistema';

    protected $fillable = ['clave', 'valor'];
}
