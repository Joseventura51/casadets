<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Comisión calculada sobre la utilidad (ganancia) de cada venta.
 * Regla de negocio: ventas del día domingo aportan al 50%, el resto de días al 35%.
 */
class ComisionUtilidad
{
    public static function calcular(Collection $ventaIdsPagadasYCosteadas): float
    {
        if ($ventaIdsPagadasYCosteadas->isEmpty()) {
            return 0.0;
        }

        $filas = DB::table('venta_detalles as vd')
            ->join('ventas as v', 'vd.venta_id', '=', 'v.id')
            ->leftJoin('productos as p', 'vd.producto_id', '=', 'p.id')
            ->whereIn('vd.venta_id', $ventaIdsPagadasYCosteadas)
            ->selectRaw('v.id as venta_id, v.fecha, COALESCE(v.ajuste, 0) as ajuste,
                         SUM((vd.precio_unitario - COALESCE(p.precio_costo, 0)) * vd.cantidad) as ganancia_lineas')
            ->groupBy('v.id', 'v.fecha', 'v.ajuste')
            ->get();

        $total = 0.0;
        foreach ($filas as $fila) {
            $ganancia = (float) $fila->ganancia_lineas + (float) $fila->ajuste;
            $pct      = Carbon::parse($fila->fecha)->isSunday() ? 0.50 : 0.35;
            $total   += $ganancia * $pct;
        }

        return round($total, 2);
    }
}
