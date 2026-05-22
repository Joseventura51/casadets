<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pago extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'cliente_id',
        'monto_total',
        'metodo_pago',
        'estado',
        'fecha',
        'observacion',
    ];

    protected $casts = [
        'fecha'       => 'date',
        'monto_total' => 'decimal:2',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetallePagoFactura::class);
    }

    public function metodos(): HasMany
    {
        return $this->hasMany(PagoMetodo::class);
    }

    public function ventas()
    {
        return $this->belongsToMany(Venta::class, 'detalle_pago_factura')
                    ->withPivot('monto_aplicado')
                    ->withTimestamps();
    }
}
