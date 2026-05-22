<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use SoftDeletes;

    protected $fillable = ['nombre', 'documento', 'telefono', 'direccion', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }
}
