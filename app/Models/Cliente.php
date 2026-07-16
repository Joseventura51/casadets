<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use SoftDeletes;

    protected $fillable = ['nombre', 'documento', 'tipo_documento', 'telefono', 'direccion', 'activo'];

    public const TIPOS_DOCUMENTO = [
        '1' => 'DNI',
        '6' => 'RUC',
        '4' => 'Carnet de Extranjería',
        '7' => 'Pasaporte',
        'A' => 'Cédula',
    ];

    protected $casts = ['activo' => 'boolean'];

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }
}
