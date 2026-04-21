<?php

namespace App\Http\Controllers;

use App\Models\Movimiento;
use App\Models\Venta;
use Carbon\Carbon;

class HomeController extends Controller
{
    public function index()
    {
        $hoy = Carbon::today();

        $totalVentasMes = Venta::whereMonth('fecha', $hoy->month)
            ->whereYear('fecha', $hoy->year)
            ->sum('total');

        $totalIngresosMes = Movimiento::where('tipo', 'ingreso')
            ->whereMonth('fecha', $hoy->month)
            ->whereYear('fecha', $hoy->year)
            ->sum('monto');

        $totalSalidasMes = Movimiento::where('tipo', 'salida')
            ->whereMonth('fecha', $hoy->month)
            ->whereYear('fecha', $hoy->year)
            ->sum('monto');

        $balanceMes = $totalVentasMes + $totalIngresosMes - $totalSalidasMes;

        $ventasHoy = Venta::with(['vendedor', 'detalles'])
            ->whereDate('fecha', $hoy)
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        $ultimosMovimientos = Movimiento::orderBy('fecha', 'desc')
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
