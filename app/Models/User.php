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

    public function cajasPermitidas(): BelongsToMany
    {
        return $this->belongsToMany(Caja::class, 'usuario_caja', 'user_id', 'caja_id')
                    ->withPivot('principal')
                    ->withTimestamps();
    }

    public function cajaPrincipal(): ?Caja
    {
        return $this->cajasPermitidas()->wherePivot('principal', true)->first()
            ?? $this->cajasPermitidas()->first();
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

        // Fallback estático para roles sin permisos configurados aún
        $defaults = [
            'ventas.crear'         => ['Administrador', 'Supervisor', 'Cajero', 'Vendedor'],
            'ventas.editar'        => ['Administrador', 'Supervisor', 'Cajero'],
            'ventas.eliminar'      => ['Administrador', 'Supervisor'],
            'ventas.pago'          => ['Administrador', 'Supervisor', 'Cajero'],
            'ventas.anular'        => ['Administrador', 'Supervisor'],
            'ventas.importar'      => ['Administrador', 'Supervisor', 'Cajero'],
            'productos.crear'      => ['Administrador', 'Supervisor'],
            'productos.editar'     => ['Administrador', 'Supervisor'],
            'productos.eliminar'   => ['Administrador', 'Supervisor'],
            'compras.crear'        => ['Administrador', 'Supervisor'],
            'compras.editar'       => ['Administrador', 'Supervisor'],
            'compras.eliminar'     => ['Administrador', 'Supervisor'],
            'clientes.crear'       => ['Administrador', 'Supervisor', 'Cajero'],
            'clientes.editar'      => ['Administrador', 'Supervisor', 'Cajero'],
            'clientes.eliminar'    => ['Administrador', 'Supervisor'],
            'vendedores.crear'     => ['Administrador', 'Supervisor'],
            'vendedores.editar'    => ['Administrador', 'Supervisor'],
            'movimientos.crear'    => ['Administrador', 'Supervisor', 'Cajero'],
            'movimientos.editar'   => ['Administrador', 'Supervisor'],
            'movimientos.anular'   => ['Administrador', 'Supervisor'],
            'caja.apertura'        => ['Administrador', 'Supervisor', 'Cajero'],
            'caja.cierre'          => ['Administrador', 'Supervisor', 'Cajero'],
            'saldos.usar'          => ['Administrador', 'Supervisor', 'Cajero'],
            'reportes.ver'         => ['Administrador', 'Supervisor', 'Vendedor'],
        ];

        return in_array($rol?->nombre, $defaults[$permiso] ?? ['Administrador']);
    }

    // ── Restricción por vendedor / caja ─────────────────────────────────────

    /**
     * ¿El usuario tiene CAJAS asignadas?
     * Si tiene cajas, el filtro por caja tiene PRIORIDAD y el de vendedor se ignora.
     */
    public function debeRestringirPorCaja(): bool
    {
        return $this->cajasPermitidas()->exists();
    }

    /**
     * ¿El usuario debe ver SOLO datos de sus vendedores asociados?
     * Se activa cuando tiene vendedores PERO NO tiene cajas asignadas.
     */
    public function debeRestringirPorVendedor(): bool
    {
        return $this->vendedores()->exists() && !$this->debeRestringirPorCaja();
    }

    public function vendedorIds(): array
    {
        return $this->vendedores()->pluck('vendedores.id')->toArray();
    }

    public function cajaIds(): array
    {
        return $this->cajasPermitidas()->pluck('cajas.id')->toArray();
    }
}
