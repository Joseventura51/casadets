<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Servicio centralizado de restricción por vendedor.
 *
 * Cuando un usuario tiene vendedores asociados, SOLO puede ver información
 * relacionada con esos vendedores. Este servicio provee los métodos para
 * aplicar ese filtro en cada módulo del sistema.
 *
 * Uso:
 *   VendedorScope::activo()          → bool   (¿aplica restricción?)
 *   VendedorScope::ids()             → array|null
 *   VendedorScope::aplicar($query)   → filtra por vendedor_id directo
 *   VendedorScope::aplicarCompras($query)
 *   VendedorScope::aplicarSaldos($query)
 *   VendedorScope::aplicarMovimientos($query)
 */
class VendedorScope
{
    /** ¿El usuario autenticado debe ver solo sus vendedores? */
    public static function activo(): bool
    {
        $user = auth()->user();
        return $user && $user->debeRestringirPorVendedor();
    }

    /**
     * IDs de vendedores asignados al usuario autenticado.
     * Retorna null si no hay restricción (admin/supervisor sin restricción).
     */
    public static function ids(): ?array
    {
        if (!static::activo()) {
            return null;
        }
        return auth()->user()->vendedorIds();
    }

    /**
     * Filtra una query con columna directa vendedor_id (Ventas, pendientes, etc.)
     */
    public static function aplicar(Builder $query, string $columna = 'vendedor_id'): Builder
    {
        $ids = static::ids();
        if ($ids !== null) {
            $query->whereIn($columna, $ids);
        }
        return $query;
    }

    /**
     * Filtra Compras por vendedor de las ventas asociadas.
     * Una compra se muestra si al menos uno de sus detalles apunta
     * a una venta cuyo vendedor está asignado al usuario.
     */
    public static function aplicarCompras(Builder $query): Builder
    {
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
     * Filtra SaldoFavor por el vendedor de la venta origen.
     */
    public static function aplicarSaldos(Builder $query): Builder
    {
        $ids = static::ids();
        if ($ids !== null) {
            $query->whereHas('ventaOrigen', fn ($q) =>
                $q->whereIn('vendedor_id', $ids)
            );
        }
        return $query;
    }

    /**
     * Filtra Movimientos por:
     *   1. vendedor_id directo (salidas manuales), O
     *   2. referencia a un pago vinculado a ventas del vendedor (cobranzas).
     */
    public static function aplicarMovimientos(Builder $query): Builder
    {
        $ids = static::ids();
        if ($ids !== null) {
            $query->where(function (Builder $q) use ($ids) {
                // Salidas manuales con vendedor asignado
                $q->whereIn('vendedor_id', $ids)
                  // Cobranzas (ingresos tipo pago) vinculadas a ventas del vendedor
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
}
