<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AjustePrecioSupuesto extends Model
{
    protected $table = 'ajustes_precio_supuesto';

    protected $fillable = [
        'compra_supuesta_id',
        'compra_real_id',
        'diferencia_total',
        'aplicado',
        'reporte_semanal_id',
    ];

    protected $casts = [
        'diferencia_total' => 'decimal:2',
        'aplicado'         => 'boolean',
    ];

    public function compraSupuesta(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'compra_supuesta_id');
    }

    public function compraReal(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'compra_real_id');
    }

    public function reporteSemanal(): BelongsTo
    {
        return $this->belongsTo(ReporteSemanal::class);
    }

    /**
     * El ajuste que este registro aporta a la utilidad del cierre.
     * Es el NEGATIVO de la diferencia: si real fue más caro → resta utilidad.
     */
    public function getAporteUtilidadAttribute(): float
    {
        return -(float) $this->diferencia_total;
    }
}
