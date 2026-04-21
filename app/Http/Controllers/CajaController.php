<?php

namespace App\Http\Controllers;

use App\Models\Movimiento;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CajaController extends Controller
{
    public function index(Request $request)
    {
        $fecha = $request->input('fecha', Carbon::today()->toDateString());

        $ventas = Venta::with(['vendedor', 'detalles'])
            ->whereDate('fecha', $fecha)
            ->orderBy('id', 'desc')
            ->get();

        $movimientos = Movimiento::whereDate('fecha', $fecha)
            ->orderBy('id', 'desc')
            ->get();

        // total_cobrado = total + ajuste (lo que realmente se cobró)
        $totalVentas = $ventas->sum(fn($v) => $v->total_cobrado);
        $totalAjustes = $ventas->sum('ajuste');
        $totalIngresos = $movimientos->where('tipo', 'ingreso')->sum('monto');
        $totalSalidas = $movimientos->where('tipo', 'salida')->sum('monto');
        $balance = $totalVentas + $totalIngresos - $totalSalidas;

        $ventasPorMetodo = $ventas->groupBy('metodo_pago')
            ->map(fn($g) => $g->sum(fn($v) => $v->total_cobrado));

        $ventasPorVendedor = $ventas->groupBy(fn($v) => $v->vendedor->nombre ?? 'Sin vendedor')
            ->map(fn($g) => $g->sum(fn($v) => $v->total_cobrado));

        return view('casadets.caja.index', compact(
            'fecha',
            'ventas',
            'movimientos',
            'totalVentas',
            'totalAjustes',
            'totalIngresos',
            'totalSalidas',
            'balance',
            'ventasPorMetodo',
            'ventasPorVendedor'
        ));
    }
}
