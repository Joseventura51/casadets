<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

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

    protected static function booted(): void
    {
        // Compatibilidad: compras, ventas, ajustes y devoluciones antiguas
        // todavía crean movimientos sin almacén. Se consideran operaciones
        // del almacén principal para mantener producto_almacen sincronizado.
        static::created(function (self $movimiento) {
            if ($movimiento->almacen_id !== null) {
                return;
            }
            $movimiento->ajustarAlmacenPrincipal(1);
        });

        static::deleted(function (self $movimiento) {
            if ($movimiento->almacen_id !== null) {
                return;
            }
            $movimiento->ajustarAlmacenPrincipal(-1);
        });
    }

    private function ajustarAlmacenPrincipal(int $factor): void
    {
        $almacen = Almacen::where('codigo', 'PRINCIPAL')->first();
        if (!$almacen) {
            return;
        }

        $saldo = ProductoAlmacen::where('producto_id', $this->producto_id)
            ->where('almacen_id', $almacen->id)
            ->lockForUpdate()
            ->first();

        if (!$saldo) {
            $saldo = ProductoAlmacen::create([
                'producto_id' => $this->producto_id,
                'almacen_id' => $almacen->id,
                'stock_actual' => 0,
            ]);
        }

        $delta = ($this->tipo === 'entrada' ? 1 : -1)
            * (float) $this->cantidad
            * $factor;
        $saldo->update([
            'stock_actual' => round((float) $saldo->stock_actual + $delta, 2),
        ]);
    }
}
