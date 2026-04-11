<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Movimiento extends Model
{
    protected $fillable = [
    'tipo',
    'Categoria',
    'monto',
    'fecha',
    'observaciones',
];
}
 