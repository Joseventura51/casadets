<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Servicio centralizado de restricción por VENDEDOR o CAJA.
 *
 * Reglas:
 *   1. Si el usuario tiene CAJAS asignadas → filtra todo por caja_id (PRIORIDAD).
 *   2. Si el usuario tiene VENDEDORES asignados (y NO cajas) → filtra por vendedor.
 *   3. Admin/Supervisor sin asignaciones → sin restricción.
 *
 * Uso:
 *   VendedorScope::activo()          → bool   (¿aplica alguna restricción?)
 *   VendedorScope::ids()             → array|null   (vendedor IDs)
 *   VendedorScope::cajaIds()         → array|null   (caja IDs)
 *   VendedorScope::modo()            → 'caja'|'vendedor'|'ninguno'
 *   VendedorScope::aplicar($query)   → filtra ventas por vendedor_id o caja_id
 *   VendedorScope::aplicarCompras($query)
 *   VendedorScope::aplicarSaldos($query)
 *   VendedorScope::aplicarMovimientos($query)
 *   VendedorScope::aplicarClientes($query)
 */
class VendedorScope
{
    /** ¿El usuario autenticado debe ver filtrado? */
    public static function activo(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return $user->debeRestringirPorCaja() || $user->debeRestringirPorVendedor();
    }

    /** 'caja', 'vendedor', o 'ninguno' */
    public static function modo(): string
    {
        $user = auth()->user();
        if (!$user) return 'ninguno';
        if ($user->debeRestringirPorCaja()) return 'caja';
        if ($user->debeRestringirPorVendedor()) return 'vendedor';
        return 'ninguno';
    }

    /**
     * IDs de vendedores asignados al usuario autenticado.
     * Retorna null si no hay restricción por vendedor.
     */
    public static function ids(): ?array
    {
        if (static::modo() !== 'vendedor') {
            return null;
        }
        return auth()->user()->vendedorIds();
    }

    /**
     * IDs de cajas asignadas al usuario autenticado.
     * Retorna null si no hay restricción por caja.
     */
    public static function cajaIds(): ?array
    {
        if (static::modo() !== 'caja') {
            return null;
        }
        return auth()->user()->cajaIds();
    }

    // ── Aplicadores por módulo ─────────────────────────────────────────

    /**
     * Ventas / Pendientes — filtra por vendedor_id o caja_id.
     */
    public static function aplicar(Builder $query, string $columna = 'vendedor_id'): Builder
    {
        $cajas = static::cajaIds();
        if ($cajas !== null) {
            $query->whereIn('caja_id', $cajas);
            return $query;
        }

        $ids = static::ids();
        if ($ids !== null) {
            $query->whereIn($columna, $ids);
        }
        return $query;
    }

    /**
     * Compras — si filtra por caja, solo muestra compras de esas cajas.
     * Si filtra por vendedor, muestra compras cuyas ventas asociadas tienen esos vendedores.
     */
    public static function aplicarCompras(Builder $query): Builder
    {
        $cajas = static::cajaIds();
        if ($cajas !== null) {
            $query->whereIn('caja_id', $cajas);
            return $query;
        }

        $ids = static::ids();
        if ($ids !== null) {
            $query->whereHas('detalles', fn ($q) =>
                $q->whereHas('venta', fn ($q2) =>
                    $q2->whereIn('vendedor_id', $ids)
                )
            );
        }
        return $query;
    }

    /**
     * SaldoFavor — si filtra por caja, usa caja_id de la venta origen.
     * Si filtra por vendedor, usa vendedor_id de la venta origen.
     */
    public static function aplicarSaldos(Builder $query): Builder
    {
        $cajas = static::cajaIds();
        if ($cajas !== null) {
            $query->whereHas('ventaOrigen', fn ($q) =>
                $q->whereIn('caja_id', $cajas)
            );
            return $query;
        }

        $ids = static::ids();
        if ($ids !== null) {
            $query->whereHas('ventaOrigen', fn ($q) =>
                $q->whereIn('vendedor_id', $ids)
            );
        }
        return $query;
    }

    /**
     * Movimientos — si filtra por caja, usa caja_id directo.
     * Si filtra por vendedor, usa vendedor_id o referencia a pago de ventas.
     */
    public static function aplicarMovimientos(Builder $query): Builder
    {
        $cajas = static::cajaIds();
        if ($cajas !== null) {
            $query->whereIn('caja_id', $cajas);
            return $query;
        }

        $ids = static::ids();
        if ($ids !== null) {
            $query->where(function (Builder $q) use ($ids) {
                $q->whereIn('vendedor_id', $ids)
                  ->orWhere(function (Builder $q2) use ($ids) {
                      $q2->where('referencia_tipo', 'pago')
                         ->whereExists(function ($sub) use ($ids) {
                             $sub->select(DB::raw(1))
                                 ->from('detalle_pago_factura')
                                 ->join('ventas', 'ventas.id', '=', 'detalle_pago_factura.venta_id')
                                 ->whereColumn('detalle_pago_factura.pago_id', 'movimientos.referencia_id')
                                 ->whereIn('ventas.vendedor_id', $ids)
                                 ->whereNull('ventas.deleted_at');
                         });
                  });
            });
        }
        return $query;
    }

    /**
     * Clientes — si filtra por caja, muestra clientes con ventas en esas cajas.
     * Si filtra por vendedor, muestra clientes con ventas de esos vendedores.
     */
    public static function aplicarClientes(Builder $query): Builder
    {
        $cajas = static::cajaIds();
        if ($cajas !== null) {
            $query->whereHas('ventas', fn ($q) =>
                $q->whereIn('caja_id', $cajas)
            );
            return $query;
        }

        $ids = static::ids();
        if ($ids !== null) {
            $query->whereHas('ventas', fn ($q) =>
                $q->whereIn('vendedor_id', $ids)
            );
        }
        return $query;
    }
}
