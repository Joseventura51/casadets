<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Almacen extends Model
{
    public const CATALOGO_OFICIAL = [
        'PIMPON' => 'Pimpon',
        'CASA' => 'Casa',
        'IMPACTO' => 'Impacto',
        'ZAPALLAL' => 'Zapallal',
        'LUNA' => 'Luna',
        'PIZARRO' => 'Pizarro',
    ];

    protected $table = 'almacenes';

    protected $fillable = ['codigo', 'nombre', 'empresa', 'activo', 'observaciones'];

    protected $casts = ['activo' => 'boolean'];

    public function scopeOficialesActivos($query)
    {
        return $query
            ->where('empresa', 'zendy')
            ->where('activo', true)
            ->whereIn('codigo', array_keys(self::CATALOGO_OFICIAL));
    }

    public function esOficialActivo(): bool
    {
        return $this->empresa === 'zendy'
            && (bool) $this->activo
            && array_key_exists(strtoupper(trim((string) $this->codigo)), self::CATALOGO_OFICIAL);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductoAlmacen::class);
    }

    public function movimientosStock(): HasMany
    {
        return $this->hasMany(StockMovimiento::class);
    }

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'producto_almacen')
            ->withPivot('stock_actual')
            ->withTimestamps();
    }

    public function salidasOrigen(): HasMany
    {
        return $this->hasMany(SalidaAlmacen::class, 'almacen_origen_id');
    }

    public function salidasDestino(): HasMany
    {
        return $this->hasMany(SalidaAlmacen::class, 'almacen_destino_id');
    }

    public function devolucionesRecepcion(): HasMany
    {
        return $this->hasMany(DevolucionSalida::class, 'almacen_recepcion_id');
    }
}