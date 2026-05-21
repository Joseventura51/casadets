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
        $hoy   = Carbon::today()->toDateString();
        $desde = $request->input('desde', $hoy);
        $hasta = $request->input('hasta', $desde);
        if ($hasta < $desde) $hasta = $desde;

        // Todas las ventas del período (pendientes, pagadas, anuladas)
        $ventas = Venta::with(['vendedor', 'detalles'])
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->whereNotIn('estado', ['anulado'])
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $movimientos = Movimiento::whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // Solo las efectivamente cobradas (pagado O con metodo_pago registrado)
        $ventasCobradas = $ventas->filter(
            fn($v) => $v->estado === 'pagado' || !empty($v->metodo_pago)
        );
        $ventasPendientes = $ventas->filter(
            fn($v) => $v->estado !== 'pagado' && empty($v->metodo_pago)
        );

        $totalVentas   = $ventasCobradas->sum(fn($v) => $v->total_cobrado);
        $totalAjustes  = $ventasCobradas->sum('ajuste');
        $totalIngresos = $movimientos->where('tipo', 'ingreso')->sum('monto');
        $totalSalidas  = $movimientos->where('tipo', 'salida')->sum('monto');
        $balance       = $totalVentas + $totalIngresos - $totalSalidas;

        // Desglose por método: solo ventas cobradas, separar métodos combinados
        $ventasPorMetodo = $ventasCobradas->flatMap(function ($v) {
            $metodos = array_filter(array_map('trim', explode(',', $v->metodo_pago ?? '')));
            if (empty($metodos)) return [];
            $n = count($metodos);
            return collect($metodos)->map(fn($m) => [
                'metodo' => $m,
                'monto'  => round($v->total_cobrado / $n, 2),
            ]);
        })->groupBy('metodo')->map(fn($g) => $g->sum('monto'));

        $ventasPorVendedor = $ventasCobradas
            ->groupBy(fn($v) => $v->vendedor->nombre ?? 'Sin vendedor')
            ->map(fn($g) => $g->sum(fn($v) => $v->total_cobrado));

        $esRango = $desde !== $hasta;

        return view('casadets.caja.index', compact(
            'desde', 'hasta', 'hoy', 'esRango',
            'ventas', 'ventasCobradas', 'ventasPendientes',
            'movimientos',
            'totalVentas', 'totalAjustes',
            'totalIngresos', 'totalSalidas', 'balance',
            'ventasPorMetodo', 'ventasPorVendedor'
        ));
    }
}
