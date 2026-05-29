<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'rol_id',
        'activo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'activo'            => 'boolean',
        ];
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class);
    }

    public function vendedores(): BelongsToMany
    {
        return $this->belongsToMany(Vendedor::class, 'usuario_vendedor');
    }

    public function esAdmin(): bool
    {
        return $this->rol?->nombre === 'Administrador';
    }

    public function esSupervisor(): bool
    {
        return $this->rol?->nombre === 'Supervisor';
    }

    public function esCajero(): bool
    {
        return $this->rol?->nombre === 'Cajero';
    }

    public function esVendedor(): bool
    {
        return $this->rol?->nombre === 'Vendedor';
    }

    public function puedeVer(string $modulo): bool
    {
        $rol = $this->rol?->nombre;

        $permisos = [
            'dashboard'     => ['Administrador', 'Supervisor', 'Cajero', 'Vendedor'],
            'caja'          => ['Administrador', 'Supervisor', 'Cajero'],
            'ventas'        => ['Administrador', 'Supervisor', 'Cajero', 'Vendedor'],
            'pendientes'    => ['Administrador', 'Supervisor', 'Cajero', 'Vendedor'],
            'compras'       => ['Administrador', 'Supervisor'],
            'productos'     => ['Administrador', 'Supervisor', 'Cajero'],
            'clientes'      => ['Administrador', 'Supervisor', 'Cajero', 'Vendedor'],
            'vendedores'    => ['Administrador', 'Supervisor'],
            'saldos-favor'  => ['Administrador', 'Supervisor', 'Cajero'],
            'movimientos'   => ['Administrador', 'Supervisor'],
            'zendy'         => ['Administrador', 'Supervisor'],
            'reportes'      => ['Administrador', 'Supervisor', 'Vendedor'],
            'admin.usuarios'=> ['Administrador'],
        ];

        return in_array($rol, $permisos[$modulo] ?? []);
    }

    public function vendedorIds(): array
    {
        return $this->vendedores()->pluck('vendedores.id')->toArray();
    }
}
