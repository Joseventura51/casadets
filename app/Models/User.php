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

    // ── Relaciones ──────────────────────────────────────────────────────────

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class);
    }

    public function vendedores(): BelongsToMany
    {
        return $this->belongsToMany(Vendedor::class, 'usuario_vendedor');
    }

    // ── Helpers de rol ──────────────────────────────────────────────────────

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

    // ── Sistema de permisos dinámico ─────────────────────────────────────────

    /**
     * Verifica si el usuario puede VER/ACCEDER a un módulo.
     * Primero consulta los módulos dinámicos del rol; si el rol aún no tiene
     * módulos configurados (migración pendiente o rol antiguo), cae al mapa
     * estático de respaldo para no romper el sistema.
     */
    public function puedeVer(string $modulo): bool
    {
        $rol = $this->rol;

        if ($rol && !empty($rol->modulos)) {
            return $rol->tieneModulo($modulo);
        }

        // Fallback estático para roles sin módulos configurados aún
        $mapa = [
            'dashboard'      => ['Administrador', 'Supervisor', 'Cajero', 'Vendedor'],
            'caja'           => ['Administrador', 'Supervisor', 'Cajero'],
            'ventas'         => ['Administrador', 'Supervisor', 'Cajero', 'Vendedor'],
            'pendientes'     => ['Administrador', 'Supervisor', 'Cajero', 'Vendedor'],
            'compras'        => ['Administrador', 'Supervisor'],
            'productos'      => ['Administrador', 'Supervisor', 'Cajero'],
            'clientes'       => ['Administrador', 'Supervisor', 'Cajero', 'Vendedor'],
            'vendedores'     => ['Administrador', 'Supervisor'],
            'saldos-favor'   => ['Administrador', 'Supervisor', 'Cajero'],
            'movimientos'    => ['Administrador', 'Supervisor'],
            'zendy'          => ['Administrador', 'Supervisor'],
            'reportes'       => ['Administrador', 'Supervisor', 'Vendedor'],
            'admin.usuarios' => ['Administrador'],
            'admin.roles'    => ['Administrador'],
        ];

        return in_array($rol?->nombre, $mapa[$modulo] ?? []);
    }

    /**
     * Verifica si el usuario puede REALIZAR una acción específica.
     * Formato: 'ventas.crear', 'ventas.editar', 'clientes.eliminar', etc.
     */
    public function puedeHacer(string $permiso): bool
    {
        $rol = $this->rol;

        if ($rol && !empty($rol->permisos)) {
            return $rol->tienePermiso($permiso);
        }

        // Administrador siempre puede todo (fallback para roles sin permisos)
        return $rol?->nombre === 'Administrador';
    }

    // ── Restricción por vendedor ─────────────────────────────────────────────

    /**
     * Indica si este usuario debe ver SOLO los datos de sus vendedores asociados.
     * Se activa cuando tiene al menos 1 vendedor asignado.
     */
    public function debeRestringirPorVendedor(): bool
    {
        return $this->vendedores()->exists();
    }

    public function vendedorIds(): array
    {
        return $this->vendedores()->pluck('vendedores.id')->toArray();
    }
}
