<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LetraProveedor extends Model
{
    protected $table = 'letras_proveedor';
    protected $fillable = ['cuenta_por_pagar_id', 'numero', 'fecha_emision', 'fecha_vencimiento', 'monto', 'observaciones'];
    protected $casts = ['fecha_emision' => 'date', 'fecha_vencimiento' => 'date', 'monto' => 'decimal:2'];
    public function cuenta(): BelongsTo { return $this->belongsTo(CuentaPorPagar::class, 'cuenta_por_pagar_id'); }
}