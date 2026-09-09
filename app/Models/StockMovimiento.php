<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovimiento extends Model
{
    protected $table = 'stock_movimientos';

    protected $fillable = [
        'producto_id',
        'almacen_id',
        'tipo',
        'cantidad',
        'saldo_anterior',
        'saldo_posterior',
        'costo_unitario',
        'precio_unitario',
        'referencia_tipo',
        'referencia_id',
        'usuario_id',
        'fecha',
        'observaciones',
    ];

    protected $casts = [
        'fecha'           => 'date',
        'cantidad'        => 'decimal:2',
        'saldo_anterior'  => 'decimal:2',
        'saldo_posterior' => 'decimal:2',
        'costo_unitario'  => 'decimal:2',
        'precio_unitario' => 'decimal:2',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

}
