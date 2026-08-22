<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PagoProveedor extends Model
{
    protected $table = 'pagos_proveedores';
    protected $fillable = ['proveedor_id', 'caja_id', 'user_id', 'fecha', 'monto', 'moneda', 'metodo_pago', 'referencia', 'observaciones', 'estado'];
    protected $casts = ['fecha' => 'date', 'monto' => 'decimal:2'];
    public function proveedor(): BelongsTo { return $this->belongsTo(ProveedorCxp::class, 'proveedor_id'); }
    public function aplicaciones(): HasMany { return $this->hasMany(AplicacionPagoProveedor::class, 'pago_proveedor_id'); }
}