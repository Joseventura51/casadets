<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Caja extends Model
{
    protected $fillable = [
        'codigo',
        'nombre',
        'empresa',
        'activa',
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];

    public function sesiones(): HasMany
    {
        return $this->hasMany(CajaSesion::class);
    }

    public function series(): HasMany
    {
        return $this->hasMany(Serie::class);
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function usuariosAsignados(): HasMany
    {
        return $this->hasMany(\App\Models\User::class, 'usuario_caja', 'caja_id');
    }

    public function sesionAbiertaHoy(): ?CajaSesion
    {
        return $this->sesiones()
            ->whereDate('fecha', now()->toDateString())
            ->where('estado', 'abierta')
            ->first();
    }

    public function estaAbiertaHoy(): bool
    {
        return $this->sesionAbiertaHoy() !== null;
    }
}
