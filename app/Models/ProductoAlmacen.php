<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoAlmacen extends Model
{
    protected $table = 'producto_almacen';

    protected $fillable = ['producto_id', 'almacen_id', 'stock_actual'];

    protected $casts = ['stock_actual' => 'decimal:2'];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }
}