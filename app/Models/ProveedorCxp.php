<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProveedorCxp extends Model
{
    protected $table = 'proveedores_cxp';
    protected $fillable = ['razon_social', 'ruc', 'activo'];
    protected $casts = ['activo' => 'boolean'];

    public function cuentas(): HasMany
    {
        return $this->hasMany(CuentaPorPagar::class, 'proveedor_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(PagoProveedor::class, 'proveedor_id');
    }
}