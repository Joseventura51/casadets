<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AplicacionPagoProveedor extends Model
{
    protected $table = 'aplicaciones_pago_proveedor';
    protected $fillable = ['pago_proveedor_id', 'cuenta_por_pagar_id', 'letra_proveedor_id', 'monto_aplicado'];
    protected $casts = ['monto_aplicado' => 'decimal:2'];
    public function pago(): BelongsTo { return $this->belongsTo(PagoProveedor::class, 'pago_proveedor_id'); }
    public function cuenta(): BelongsTo { return $this->belongsTo(CuentaPorPagar::class, 'cuenta_por_pagar_id'); }
    public function letra(): BelongsTo { return $this->belongsTo(LetraProveedor::class, 'letra_proveedor_id'); }
}