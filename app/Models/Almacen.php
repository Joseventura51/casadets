<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Almacen extends Model
{
    protected $table = 'almacenes';

    protected $fillable = ['codigo', 'nombre', 'empresa', 'activo', 'observaciones'];

    protected $casts = ['activo' => 'boolean'];

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