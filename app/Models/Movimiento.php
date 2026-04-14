<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Movimiento extends Model
{
    protected $fillable = [
        'tipo',
        'categoria',
        'documento_tipo',
        'documento_numero',
        'monto',
        'fecha',
        'observaciones',
    ];
}
