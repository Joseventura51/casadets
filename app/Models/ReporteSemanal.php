<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReporteSemanal extends Model
{
    protected $table = 'reportes_semanales';

    protected $fillable = [
        'periodo_inicio',
        'periodo_fin',
        'total_ventas',
        'cantidad_ventas',
        'total_compras',
        'cantidad_compras',
        'total_costo',
        'utilidad',
        'margen',
        'comision_utilidad',
        'ventas_pendientes',
        'cerrado_por_id',
    ];

    protected $casts = [
        'periodo_inicio'    => 'date',
        'periodo_fin'       => 'date',
        'total_ventas'      => 'decimal:2',
        'total_compras'     => 'decimal:2',
        'total_costo'       => 'decimal:2',
        'utilidad'          => 'decimal:2',
        'margen'            => 'decimal:2',
        'comision_utilidad' => 'decimal:2',
    ];

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por_id');
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'reporte_semanal_id');
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class, 'reporte_semanal_id');
    }
}
