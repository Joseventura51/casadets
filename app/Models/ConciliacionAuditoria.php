<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConciliacionAuditoria extends Model
{
    public $timestamps = false;

    protected $table = 'conciliacion_auditorias';

    protected $fillable = [
        'compra_id',
        'venta_detalle_id',
        'accion',
        'cantidad_anterior',
        'cantidad_nueva',
        'costo_unitario_anterior',
        'costo_unitario_nuevo',
        'costo_total_anterior',
        'costo_total_nuevo',
        'compra_linea_id_anterior',
        'compra_linea_id_nuevo',
        'producto_nombre',
        'usuario_id',
        'ip',
        'created_at',
    ];

    protected $casts = [
        'created_at'              => 'datetime',
        'cantidad_anterior'       => 'float',
        'cantidad_nueva'          => 'float',
        'costo_unitario_anterior' => 'float',
        'costo_unitario_nuevo'    => 'float',
        'costo_total_anterior'    => 'float',
        'costo_total_nuevo'       => 'float',
    ];

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function ventaDetalle(): BelongsTo
    {
        return $this->belongsTo(VentaDetalle::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'usuario_id');
    }

    public function accionLabel(): string
    {
        return match ($this->accion) {
            'crear'      => 'Asignada',
            'actualizar' => 'Modificada',
            'eliminar'   => 'Eliminada',
            default      => $this->accion,
        };
    }

    public function accionBadge(): string
    {
        return match ($this->accion) {
            'crear'      => 'success',
            'actualizar' => 'warning',
            'eliminar'   => 'danger',
            default      => 'secondary',
        };
    }
}
