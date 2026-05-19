<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $fillable = ['nombre', 'documento', 'telefono', 'direccion', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }
}
