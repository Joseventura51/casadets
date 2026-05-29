<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rol extends Model
{
    protected $table = 'roles';

    protected $fillable = ['nombre', 'descripcion', 'modulos', 'permisos'];

    protected $casts = [
        'modulos'  => 'array',
        'permisos' => 'array',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function tieneModulo(string $modulo): bool
    {
        return in_array($modulo, $this->modulos ?? []);
    }

    public function tienePermiso(string $permiso): bool
    {
        return in_array($permiso, $this->permisos ?? []);
    }
}
