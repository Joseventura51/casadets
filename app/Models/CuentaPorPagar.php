<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CuentaPorPagar extends Model
{
    protected $table = 'cuentas_por_pagar';
    protected $fillable = [
        'proveedor_id', 'tipo_documento', 'serie', 'correlativo',
        'documento_numero', 'fecha_emision', 'fecha_vencimiento',
        'moneda', 'total', 'condicion_pago', 'origen', 'observaciones',
    ];
    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
        'total' => 'decimal:2',
    ];

    public function proveedor(): BelongsTo { return $this->belongsTo(ProveedorCxp::class, 'proveedor_id'); }
    public function letras(): HasMany { return $this->hasMany(LetraProveedor::class, 'cuenta_por_pagar_id'); }
    public function aplicaciones(): HasMany { return $this->hasMany(AplicacionPagoProveedor::class, 'cuenta_por_pagar_id'); }
    public function documentos(): HasMany { return $this->hasMany(DocumentoCxp::class, 'cuenta_por_pagar_id'); }

    public function getPagadoAttribute(): float
    {
        return (float) $this->aplicaciones()->sum('monto_aplicado');
    }

    public function getSaldoAttribute(): float
    {
        return max(0, round((float) $this->total - $this->pagado, 2));
    }

    public function getEstadoCalculadoAttribute(): string
    {
        if ($this->saldo <= 0) return 'pagada';
        if ($this->pagado > 0) return $this->fecha_vencimiento?->isPast() ? 'vencida_parcial' : 'parcial';
        return $this->fecha_vencimiento?->isPast() ? 'vencida' : 'pendiente';
    }
}