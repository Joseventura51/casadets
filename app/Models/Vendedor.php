<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendedor extends Model
{
    use SoftDeletes;

    protected $table = 'vendedores';

    protected $fillable = [
        'nombre',
        'telefono',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }
}
