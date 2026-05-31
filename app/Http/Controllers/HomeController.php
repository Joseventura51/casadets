<?php

namespace App\Http\Controllers;

use App\Models\Movimiento;
use App\Models\Producto;
use App\Models\SaldoFavor;
use App\Models\Venta;
use App\Services\VendedorScope;
use Carbon\Carbon;

class HomeController extends Controller
{
    public function index()
    {
        $hoy    = Carbon::today();
        $inicio = $hoy->copy()->startOfMonth();
        $fin    = $hoy->copy()->endOfMonth();

        // ── KPIs financieros del mes (movimientos como fuente única, estado='activo') ──

        $cobradoQuery = Movimiento::where('subtipo', 'pago_venta')
            ->where('estado', 'activo')
            ->whereBetween('fecha', [$inicio, $fin]);
        VendedorScope::aplicarMovimientos($cobradoQuery);
        $cobradoMes = (float) $cobradoQuery->sum('monto');

        $otrosIngresosQuery = Movimiento::where('tipo', 'ingreso')
            ->where('estado', 'activo')
            ->where(function ($q) {
                $q->whereNull('subtipo')
                  ->orWhere('subtipo', '!=', 'pago_venta');
            })
            ->whereBetween('fecha', [$inicio, $fin]);
        VendedorScope::aplicarMovimientos($otrosIngresosQuery);
        $otrosIngresosMes = (float) $otrosIngresosQuery->sum('monto');

        $salidasQuery = Movimiento::where('tipo', 'salida')
            ->where('estado', 'activo')
            ->whereBetween('fecha', [$inicio, $fin]);
        VendedorScope::aplicarMovimientos($salidasQuery);
        $salidasMes = (float) $salidasQuery->sum('monto');

        $balanceMes = round($cobradoMes + $otrosIngresosMes - $salidasMes, 2);

        $comprasQuery = Movimiento::where('subtipo', 'compra')
            ->where('estado', 'activo')
            ->whereBetween('fecha', [$inicio, $fin]);
        VendedorScope::aplicarMovimientos($comprasQuery);
        $comprasMes = (float) $comprasQuery->sum('monto');

        // ── Alertas operativas ───────────────────────────────────────────

        // Deuda pendiente total — excluye referencias fiscales (no generan deuda)
        $deudaQuery = Venta::whereIn('estado', ['pendiente', 'parcial'])
            ->where('es_referencia_fiscal', false)
            ->whereNull('deleted_at')
            ->selectRaw('SUM(total - pagado) as deuda');
        VendedorScope::aplicar($deudaQuery);
        $deudaPendiente = (float) ($deudaQuery->value('deuda') ?? 0);

        // Saldos a favor disponibles
        $saldosDisponiblesMes = (float) SaldoFavor::whereIn('estado', ['disponible', 'parcialmente_usado'])
            ->sum('monto_disponible');

        // Ventas pendientes vencidas (anteriores a hoy) — excluye referencias fiscales
        $pendientesQuery = Venta::whereIn('estado', ['pendiente', 'parcial'])
            ->where('es_referencia_fiscal', false)
            ->whereDate('fecha', '<', $hoy);
        VendedorScope::aplicar($pendientesQuery);
        $pendientesVencidas = $pendientesQuery->count();

        // Stock bajo (productos activos con stock ≤ 0)
        $stockBajoCount = Producto::where('activo', true)
            ->where('stock_actual', '<=', 0)
            ->count();

        // ── Tablas de home ────────────────────────────────────────────────

        $ventasHoyQuery = Venta::with([
                'vendedor:id,nombre',
                'detalles:id,venta_id,producto,cantidad,subtotal',
            ])
            ->select('id', 'vendedor_id', 'fecha', 'total', 'ajuste', 'estado',
                     'metodo_pago', 'documento_tipo', 'documento_numero')
            ->whereDate('fecha', $hoy)
            ->orderBy('id', 'desc')
            ->limit(5);
        VendedorScope::aplicar($ventasHoyQuery);
        $ventasHoy = $ventasHoyQuery->get();

        $ultimosMovimientosQuery = Movimiento::select(
                'id', 'tipo', 'subtipo', 'origen', 'estado', 'empresa', 'categoria',
                'documento_tipo', 'documento_numero', 'monto', 'fecha'
            )
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->limit(5);
        VendedorScope::aplicarMovimientos($ultimosMovimientosQuery);
        $ultimosMovimientos = $ultimosMovimientosQuery->get();

        // ── Datos para gráfica (Chart.js) — cobrado por día del mes ──────

        $chartQuery = Movimiento::where('subtipo', 'pago_venta')
            ->where('estado', 'activo')
            ->whereBetween('fecha', [$inicio, $fin])
            ->selectRaw("strftime('%d', fecha) as dia, ROUND(SUM(monto), 2) as total")
            ->groupBy('dia')
            ->orderBy('dia');
        VendedorScope::aplicarMovimientos($chartQuery);
        $cobranzaDiariaRaw = $chartQuery->pluck('total', 'dia');

        $diasEnMes   = $hoy->copy()->daysInMonth;
        $chartLabels = [];
        $cobranzaDiaria = [];
        for ($d = 1; $d <= $diasEnMes; $d++) {
            $key = str_pad($d, 2, '0', STR_PAD_LEFT);
            $chartLabels[]    = $key;
            $cobranzaDiaria[] = (float) ($cobranzaDiariaRaw[$key] ?? 0);
        }

        return view('/home', compact(
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
            'ultimosMovimientos',
            'chartLabels',
            'cobranzaDiaria'
        ));
    }
}
