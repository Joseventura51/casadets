<?php

namespace App\Http\Controllers;

use App\Models\DetallePagoFactura;
use App\Models\Movimiento;
use App\Models\Pago;
use App\Models\PagoMetodo;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CajaController extends Controller
{
    public function index(Request $request)
    {
        $hoy   = Carbon::today()->toDateString();
        $desde = $request->input('desde', $hoy);
        $hasta = $request->input('hasta', $desde);
        if ($hasta < $desde) $hasta = $desde;

        // ── Movimientos del período (fuente única de verdad financiera) ──
        $movimientos = Movimiento::whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // Ventas del período (solo para la tabla de display y reportes)
        $ventas = Venta::with(['vendedor', 'detalles'])
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->whereNotIn('estado', ['anulado'])
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $ventasCobradas = $ventas->filter(
            fn ($v) => $v->estado === 'pagado' || !empty($v->metodo_pago)
        );
        $ventasPendientes = $ventas->filter(
            fn ($v) => $v->estado !== 'pagado' && empty($v->metodo_pago)
        );

        // ── KPIs desde movimientos (sin doble conteo) ──────────────────
        //
        // totalVentasCobradas: ingresos por pagos de ventas (CobranzaService)
        // totalOtrosIngresos:  cualquier otro ingreso manual
        // totalSalidas:        todos los egresos
        // balance = ventas_cobradas + otros_ingresos - salidas
        //
        $totalVentasCobradas = round(
            $movimientos->where('subtipo', 'pago_venta')->sum('monto'), 2
        );
        $totalOtrosIngresos = round(
            $movimientos->filter(
                fn ($m) => $m->tipo === 'ingreso' && $m->subtipo !== 'pago_venta'
            )->sum('monto'), 2
        );
        $totalSalidas = round(
            $movimientos->where('tipo', 'salida')->sum('monto'), 2
        );
        $balance = round($totalVentasCobradas + $totalOtrosIngresos - $totalSalidas, 2);

        // Ajustes informativos desde la tabla de ventas
        $totalAjustes = $ventasCobradas->sum('ajuste');

        // ── Desglose por método (fuente: pago_metodos) ─────────────────
        $ventasPorMetodo = $this->calcularMetodosDePago($desde, $hasta, $ventasCobradas);

        // ── Por vendedor (desde ventas cobradas — display informativo) ──
        $ventasPorVendedor = $ventasCobradas
            ->groupBy(fn ($v) => $v->vendedor->nombre ?? 'Sin vendedor')
            ->map(fn ($g) => round($g->sum(fn ($v) => $v->total_cobrado), 2));

        $esRango = $desde !== $hasta;

        return view('casadets.caja.index', compact(
            'desde', 'hasta', 'hoy', 'esRango',
            'ventas', 'ventasCobradas', 'ventasPendientes',
            'movimientos',
            'totalVentasCobradas', 'totalOtrosIngresos',
            'totalSalidas', 'totalAjustes', 'balance',
            'ventasPorMetodo', 'ventasPorVendedor'
        ));
    }

    /**
     * Desglose exacto por método de pago para el período.
     *
     * Estrategia dual:
     *  1. Pagos via CobranzaService → fuente: pago_metodos (exacto)
     *  2. Ventas con metodo_pago pero sin Pago registrado → fallback desde ventas.metodo_pago
     *     (solo aplica para datos legados; con la arquitectura nueva esto no debería ocurrir)
     */
    private function calcularMetodosDePago(string $desde, string $hasta, $ventasCobradas): \Illuminate\Support\Collection
    {
        // A. Desglose exacto desde pago_metodos
        $pagoIds = Pago::whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->pluck('id');

        $metodosDePagos = collect();
        if ($pagoIds->isNotEmpty()) {
            $metodosDePagos = PagoMetodo::whereIn('pago_id', $pagoIds)
                ->selectRaw('metodo, SUM(monto) as total')
                ->groupBy('metodo')
                ->pluck('total', 'metodo')
                ->map(fn ($t) => (float) $t);
        }

        // B. Ventas cobradas sin Pago registrado (fallback para datos legados)
        $ventasConPagoRegistrado = DetallePagoFactura::whereIn('venta_id', $ventasCobradas->pluck('id'))
            ->pluck('venta_id')
            ->unique();

        $ventasSinPago = $ventasCobradas->filter(
            fn ($v) => !$ventasConPagoRegistrado->contains($v->id) && !empty($v->metodo_pago)
        );

        $metodosDeVentasDirectas = $ventasSinPago
            ->groupBy('metodo_pago')
            ->map(fn ($g) => round($g->sum(fn ($v) => $v->total_cobrado), 2));

        // Merge: sumar ambas fuentes por método
        $todos = $metodosDePagos->keys()->merge($metodosDeVentasDirectas->keys())->unique();

        return $todos->mapWithKeys(fn ($metodo) => [
            $metodo => round(
                ($metodosDePagos->get($metodo, 0)) + ($metodosDeVentasDirectas->get($metodo, 0)),
                2
            ),
        ])->sortKeys();
    }
}
