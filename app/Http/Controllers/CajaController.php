<?php

namespace App\Http\Controllers;

use App\Models\Movimiento;
use App\Models\Venta;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CajaController extends Controller
{
    public function index(Request $request)
    {
        $fecha = $request->input('fecha', Carbon::today()->toDateString());

        $ventas = Venta::with('vendedor')
            ->whereDate('fecha', $fecha)
            ->orderBy('id', 'desc')
            ->get();

        $movimientos = Movimiento::whereDate('fecha', $fecha)
            ->orderBy('id', 'desc')
            ->get();

        $totalVentas = $ventas->sum('monto');
        $totalIngresos = $movimientos->where('tipo', 'ingreso')->sum('monto');
        $totalSalidas = $movimientos->where('tipo', 'salida')->sum('monto');
        $balance = $totalVentas + $totalIngresos - $totalSalidas;

        $ventasPorMetodo = $ventas->groupBy('metodo_pago')->map(fn($g) => $g->sum('monto'));
        $ventasPorVendedor = $ventas->groupBy(fn($v) => $v->vendedor->nombre ?? 'Sin vendedor')
            ->map(fn($g) => $g->sum('monto'));

        return view('casadets.caja.index', compact(
            'fecha',
            'ventas',
            'movimientos',
            'totalVentas',
            'totalIngresos',
            'totalSalidas',
            'balance',
            'ventasPorMetodo',
            'ventasPorVendedor'
        ));
    }
}
