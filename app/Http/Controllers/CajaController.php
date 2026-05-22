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

        // ── Desglose por método desde pago_metodos (fuente de verdad) ──
        $ventasPorMetodo = $this->calcularMetodosDePago($desde, $hasta, $ventasCobradas);

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

    /**
     * Calcula el desglose de montos por método de pago para el período.
     *
     * Estrategia dual:
     *  1. Pagos procesados via CobranzaService → fuente: pago_metodos (exacto)
     *  2. Ventas directas sin pago registrado → fuente: ventas.metodo_pago (único, sin split)
     */
    private function calcularMetodosDePago(string $desde, string $hasta, $ventasCobradas): \Illuminate\Support\Collection
    {
        // ── A. Desglose exacto desde pago_metodos ─────────────────────
        $pagoIds = Pago::whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->pluck('id');

        $metodosDePagos = collect();
        if ($pagoIds->isNotEmpty()) {
            $metodosDePagos = PagoMetodo::whereIn('pago_id', $pagoIds)
                ->selectRaw('metodo, SUM(monto) as total')
                ->groupBy('metodo')
                ->pluck('total', 'metodo')
                ->map(fn($t) => (float) $t);
        }

        // ── B. Ventas cobradas sin pagos registrados en BD ─────────────
        // (ventas creadas directamente con metodo_pago sin pasar por verificar_pago)
        $ventasConPagoRegistrado = DetallePagoFactura::whereIn('venta_id', $ventasCobradas->pluck('id'))
            ->pluck('venta_id')
            ->unique();

        $ventasSinPago = $ventasCobradas->filter(
            fn($v) => !$ventasConPagoRegistrado->contains($v->id) && !empty($v->metodo_pago)
        );

        $metodosDeVentasDirectas = $ventasSinPago
            ->groupBy('metodo_pago')
            ->map(fn($g) => round($g->sum(fn($v) => $v->total_cobrado), 2));

        // ── Merge: sumar ambas fuentes por método ─────────────────────
        $todos = $metodosDePagos->keys()->merge($metodosDeVentasDirectas->keys())->unique();

        return $todos->mapWithKeys(fn($metodo) => [
            $metodo => round(
                ($metodosDePagos->get($metodo, 0)) + ($metodosDeVentasDirectas->get($metodo, 0)),
                2
            ),
        ])->sortKeys();
    }
}
