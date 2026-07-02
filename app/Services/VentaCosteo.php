<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VentaCosteo
{
    /**
     * De una colección de IDs de venta, devuelve solo aquellos cuyas líneas
     * están TODAS completamente costeadas (cant_costeada >= cantidad en cada línea).
     */
    public static function idsCompletamenteCosteados(Collection $ventaIds): Collection
    {
        if ($ventaIds->isEmpty()) {
            return collect();
        }

        $sinCostearIds = DB::table('venta_detalles as vd')
            ->whereIn('vd.venta_id', $ventaIds)
            ->leftJoin(
                DB::raw('(SELECT venta_detalle_id, SUM(cantidad) as cant_costeada FROM compra_venta_detalle GROUP BY venta_detalle_id) as cvd_sum'),
                'vd.id', '=', 'cvd_sum.venta_detalle_id'
            )
            ->whereRaw('COALESCE(cvd_sum.cant_costeada, 0) < vd.cantidad')
            ->pluck('vd.venta_id')
            ->unique();

        return $ventaIds->diff($sinCostearIds)->values();
    }
}
