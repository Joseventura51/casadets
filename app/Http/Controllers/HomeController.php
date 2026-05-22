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
        $hoy    = Carbon::today();
        $inicio = $hoy->copy()->startOfMonth();
        $fin    = $hoy->copy()->endOfMonth();

        // Caché de 5 min — movimientos es la única fuente de verdad financiera
        $cacheKey = 'dashboard_mes_' . $hoy->format('Y_m');

        [$cobradoMes, $otrosIngresosMes, $salidasMes] = Cache::remember(
            $cacheKey,
            300,
            function () use ($inicio, $fin) {
                // Ventas cobradas: movimientos generados por CobranzaService
                $cobrado = (float) Movimiento::where('subtipo', 'pago_venta')
                    ->whereBetween('fecha', [$inicio, $fin])
                    ->sum('monto');

                // Otros ingresos: cualquier ingreso que NO sea pago de venta
                $otros = (float) Movimiento::where('tipo', 'ingreso')
                    ->where(function ($q) {
                        $q->whereNull('subtipo')
                          ->orWhere('subtipo', '!=', 'pago_venta');
                    })
                    ->whereBetween('fecha', [$inicio, $fin])
                    ->sum('monto');

                // Salidas del mes
                $salidas = (float) Movimiento::where('tipo', 'salida')
                    ->whereBetween('fecha', [$inicio, $fin])
                    ->sum('monto');

                return [$cobrado, $otros, $salidas];
            }
        );

        // Balance real = lo que entró - lo que salió (sin doble conteo)
        $balanceMes = round($cobradoMes + $otrosIngresosMes - $salidasMes, 2);

        // Últimas 5 ventas de hoy — solo columnas necesarias para la vista
        $ventasHoy = Venta::with([
                'vendedor:id,nombre',
                'detalles:id,venta_id,producto,cantidad,subtotal',
            ])
            ->select('id', 'vendedor_id', 'fecha', 'total', 'ajuste', 'estado',
                     'metodo_pago', 'documento_tipo', 'documento_numero')
            ->whereDate('fecha', $hoy)
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        // Últimos 5 movimientos — incluye subtipo para mostrar origen
        $ultimosMovimientos = Movimiento::select(
                'id', 'tipo', 'subtipo', 'origen', 'categoria',
                'documento_tipo', 'documento_numero', 'monto', 'fecha'
            )
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        return view('home', compact(
            'cobradoMes',
            'otrosIngresosMes',
            'salidasMes',
            'balanceMes',
            'ventasHoy',
            'ultimosMovimientos'
        ));
    }
}
