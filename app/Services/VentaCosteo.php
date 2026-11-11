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

        $ultimaRegularizacion = DB::table('regularizaciones_costo')
            ->selectRaw('compra_venta_detalle_id, MAX(id) as regularizacion_id')
            ->groupBy('compra_venta_detalle_id');

        $costosPorDetalle = DB::table('compra_venta_detalle as cvd')
            ->leftJoinSub($ultimaRegularizacion, 'ultima', function ($join) {
                $join->on('ultima.compra_venta_detalle_id', '=', 'cvd.id');
            })
            ->leftJoin('regularizaciones_costo as rc', 'rc.id', '=', 'ultima.regularizacion_id')
            ->selectRaw('cvd.venta_detalle_id,
                SUM(cvd.cantidad) as cant_costeada,
                SUM(CASE WHEN COALESCE(rc.costo_nuevo, cvd.costo_unitario) IS NULL THEN cvd.cantidad ELSE 0 END) as cant_sin_costo')
            ->groupBy('cvd.venta_detalle_id');

        $sinCostearIds = DB::table('venta_detalles as vd')
            ->whereIn('vd.venta_id', $ventaIds)
            ->leftJoinSub($costosPorDetalle, 'costosPorDetalle', function ($join) {
                $join->on('vd.id', '=', 'costosPorDetalle.venta_detalle_id');
            })
            ->where(function ($query) {
                $query->whereRaw('COALESCE(costosPorDetalle.cant_costeada, 0) < vd.cantidad')
                    ->orWhereRaw('COALESCE(costosPorDetalle.cant_costeada, 0) > vd.cantidad')
                    ->orWhereRaw('COALESCE(costosPorDetalle.cant_sin_costo, 0) > 0');
            })
            ->pluck('vd.venta_id')
            ->unique();

        return $ventaIds->diff($sinCostearIds)->values();
    }
}
