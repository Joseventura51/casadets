<?php

namespace App\Http\Controllers;

use App\Models\Movimiento;
use App\Models\Venta;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class HomeController extends Controller
{
    public function index()
    {
        $hoy   = Carbon::today();
        $inicio = $hoy->copy()->startOfMonth();
        $fin    = $hoy->copy()->endOfMonth();

        // Caché de 5 min para los totales del mes (consultas pesadas de agregación)
        $cacheKey = 'dashboard_mes_' . $hoy->format('Y_m');

        [$totalVentasMes, $totalIngresosMes, $totalSalidasMes] = Cache::remember(
            $cacheKey,
            300,
            function () use ($inicio, $fin) {
                // whereBetween usa el índice en `fecha`; whereMonth/whereYear NO lo usa
                $ventas = (float) Venta::whereBetween('fecha', [$inicio, $fin])
                    ->selectRaw('COALESCE(SUM(total + ajuste), 0) as t')
                    ->value('t');

                $ingresos = (float) Movimiento::where('tipo', 'ingreso')
                    ->whereBetween('fecha', [$inicio, $fin])
                    ->sum('monto');

                $salidas = (float) Movimiento::where('tipo', 'salida')
                    ->whereBetween('fecha', [$inicio, $fin])
                    ->sum('monto');

                return [$ventas, $ingresos, $salidas];
            }
        );

        $balanceMes = $totalVentasMes + $totalIngresosMes - $totalSalidasMes;

        // Últimas 5 ventas de hoy — solo columnas necesarias para la vista
        $ventasHoy = Venta::with([
                'vendedor:id,nombre',
                'detalles:id,venta_id,producto,cantidad,subtotal',
            ])
            ->select('id', 'vendedor_id', 'fecha', 'total', 'ajuste', 'estado', 'metodo_pago', 'documento_tipo', 'documento_numero')
            ->whereDate('fecha', $hoy)
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        // Últimos 5 movimientos — solo columnas necesarias
        $ultimosMovimientos = Movimiento::select('id', 'tipo', 'categoria', 'documento_tipo', 'documento_numero', 'monto', 'fecha')
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        return view('home', compact(
            'totalVentasMes',
            'totalIngresosMes',
            'totalSalidasMes',
            'balanceMes',
            'ventasHoy',
            'ultimosMovimientos'
        ));
    }
}
