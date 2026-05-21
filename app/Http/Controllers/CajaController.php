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
        $hoy  = Carbon::today()->toDateString();
        $desde = $request->input('desde', $hoy);
        $hasta = $request->input('hasta', $desde);
        if ($hasta < $desde) $hasta = $desde;

        $ventas = Venta::with(['vendedor', 'detalles'])
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->where('estado', 'pagado')
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $movimientos = Movimiento::whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $totalVentas    = $ventas->sum(fn($v) => $v->total_cobrado);
        $totalAjustes   = $ventas->sum('ajuste');
        $totalIngresos  = $movimientos->where('tipo', 'ingreso')->sum('monto');
        $totalSalidas   = $movimientos->where('tipo', 'salida')->sum('monto');
        $balance        = $totalVentas + $totalIngresos - $totalSalidas;

        $ventasPorMetodo = $ventas->flatMap(function ($v) {
            $metodos = array_filter(explode(',', $v->metodo_pago ?? ''));
            $n = count($metodos) ?: 1;
            return collect($metodos)->map(fn($m) => [
                'metodo' => trim($m),
                'monto'  => round($v->total_cobrado / $n, 2),
            ]);
        })->groupBy('metodo')->map(fn($g) => $g->sum('monto'));

        $ventasPorVendedor = $ventas
            ->groupBy(fn($v) => $v->vendedor->nombre ?? 'Sin vendedor')
            ->map(fn($g) => $g->sum(fn($v) => $v->total_cobrado));

        $esRango = $desde !== $hasta;

        return view('casadets.caja.index', compact(
            'desde', 'hasta', 'hoy', 'esRango',
            'ventas', 'movimientos',
            'totalVentas', 'totalAjustes',
            'totalIngresos', 'totalSalidas', 'balance',
            'ventasPorMetodo', 'ventasPorVendedor'
        ));
    }
}
