<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PagoProveedor extends Model
{
    protected $table = 'pagos_proveedores';
    protected $fillable = ['proveedor_id', 'caja_id', 'user_id', 'fecha', 'monto', 'retencion_aplicada', 'retencion_porcentaje', 'retencion_monto', 'monto_neto', 'moneda', 'metodo_pago', 'referencia', 'observaciones', 'estado'];
    protected $casts = ['fecha' => 'date', 'monto' => 'decimal:2', 'retencion_aplicada' => 'boolean', 'retencion_porcentaje' => 'decimal:2', 'retencion_monto' => 'decimal:2', 'monto_neto' => 'decimal:2'];
    public function proveedor(): BelongsTo { return $this->belongsTo(ProveedorCxp::class, 'proveedor_id'); }
    public function aplicaciones(): HasMany { return $this->hasMany(AplicacionPagoProveedor::class, 'pago_proveedor_id'); }
    public function documentos(): HasMany { return $this->hasMany(DocumentoCxp::class, 'pago_proveedor_id'); }
}