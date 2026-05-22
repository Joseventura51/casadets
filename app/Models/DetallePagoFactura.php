<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetallePagoFactura extends Model
{
    protected $table = 'detalle_pago_factura';

    protected $fillable = [
        'pago_id',
        'venta_id',
        'monto_aplicado',
    ];

    protected $casts = [
        'monto_aplicado' => 'decimal:2',
    ];

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }
}
