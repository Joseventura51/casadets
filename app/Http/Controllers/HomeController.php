<?php

namespace App\Http\Controllers;

use App\Models\Movimiento;
use App\Models\Producto;
use App\Models\SaldoFavor;
use App\Models\Venta;
use Carbon\Carbon;

class HomeController extends Controller
{
    public function index()
    {
        $hoy    = Carbon::today();
        $inicio = $hoy->copy()->startOfMonth();
        $fin    = $hoy->copy()->endOfMonth();

        // ── KPIs financieros del mes (movimientos como fuente única, estado='activo') ──

        // Ventas cobradas: solo movimientos tipo=ingreso, subtipo=pago_venta, estado=activo
        $cobradoMes = (float) Movimiento::where('subtipo', 'pago_venta')
            ->where('estado', 'activo')
            ->whereBetween('fecha', [$inicio, $fin])
            ->sum('monto');

        // Otros ingresos: tipo=ingreso que NO sea pago_venta, estado=activo
        $otrosIngresosMes = (float) Movimiento::where('tipo', 'ingreso')
            ->where('estado', 'activo')
            ->where(function ($q) {
                $q->whereNull('subtipo')
                  ->orWhere('subtipo', '!=', 'pago_venta');
            })
            ->whereBetween('fecha', [$inicio, $fin])
            ->sum('monto');

        // Salidas del mes (incluye compras registradas como movimientos)
        $salidasMes = (float) Movimiento::where('tipo', 'salida')
            ->where('estado', 'activo')
            ->whereBetween('fecha', [$inicio, $fin])
            ->sum('monto');

        $balanceMes = round($cobradoMes + $otrosIngresosMes - $salidasMes, 2);

        // Compras del mes (desde movimientos subtipo='compra')
        $comprasMes = (float) Movimiento::where('subtipo', 'compra')
            ->where('estado', 'activo')
            ->whereBetween('fecha', [$inicio, $fin])
            ->sum('monto');

        // ── Alertas operativas ───────────────────────────────────────────

        // Deuda pendiente total (ventas sin cobrar)
        $deudaPendiente = (float) Venta::whereIn('estado', ['pendiente', 'parcial'])
            ->whereNull('deleted_at')
            ->selectRaw('SUM(total - pagado) as deuda')
            ->value('deuda') ?? 0;

        // Saldos a favor disponibles
        $saldosDisponiblesMes = (float) SaldoFavor::whereIn('estado', ['disponible', 'parcialmente_usado'])
            ->sum('monto_disponible');

        // Ventas pendientes vencidas (anteriores a hoy)
        $pendientesVencidas = Venta::whereIn('estado', ['pendiente', 'parcial'])
            ->whereDate('fecha', '<', $hoy)
            ->count();

        // Stock bajo (productos activos con stock ≤ 0)
        $stockBajoCount = Producto::where('activo', true)
            ->where('stock_actual', '<=', 0)
            ->count();

        // ── Tablas de home ────────────────────────────────────────────────

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

        $ultimosMovimientos = Movimiento::select(
                'id', 'tipo', 'subtipo', 'origen', 'estado', 'empresa', 'categoria',
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
            'comprasMes',
            'deudaPendiente',
            'saldosDisponiblesMes',
            'pendientesVencidas',
            'stockBajoCount',
            'ventasHoy',
            'ultimosMovimientos'
        ));
    }
}
