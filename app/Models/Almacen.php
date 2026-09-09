<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Almacen extends Model
{
    protected $table = 'almacenes';

    protected $fillable = [
        'codigo',
        'nombre',
        'empresa',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function stockMovimientos(): HasMany
    {
        return $this->hasMany(StockMovimiento::class);
    }

    public function stockZendy(): HasMany
    {
        return $this->stockMovimientos()
            ->whereHas('producto', fn ($q) => $q->where('empresa', 'zendy'));
    }
}