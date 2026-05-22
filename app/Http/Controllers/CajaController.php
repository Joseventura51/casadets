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
        $hoy    = Carbon::today()->toDateString();
        $desde  = $request->input('desde', $hoy);
        $hasta  = $request->input('hasta', $desde);
        $empresa = $request->input('empresa', 'casadets');
        if ($hasta < $desde) $hasta = $desde;

        // ── Movimientos del período (fuente única financiera) ──────────
        // Solo estado='activo' para KPIs — anulados no afectan balance
        $movimientos = Movimiento::with('cliente:id,nombre')
            ->where('empresa', $empresa)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // Ventas del período (para la tabla de display)
        $ventas = Venta::with(['vendedor', 'detalles'])
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->whereNotIn('estado', ['anulado'])
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // BUG #2 CORREGIDO: solo estado='pagado' determina si está cobrada
        // Ya NO se usa !empty($v->metodo_pago) que daba falsos positivos
        $ventasCobradas   = $ventas->where('estado', 'pagado');
        $ventasPendientes = $ventas->whereIn('estado', ['pendiente', 'parcial']);

        // ── KPIs desde movimientos activos (sin doble conteo) ──────────
        $movActivos = $movimientos->where('estado', 'activo');

        $totalVentasCobradas = round(
            $movActivos->where('subtipo', 'pago_venta')->sum('monto'), 2
        );
        $totalOtrosIngresos = round(
            $movActivos->filter(
                fn ($m) => $m->tipo === 'ingreso' && $m->subtipo !== 'pago_venta'
            )->sum('monto'), 2
        );
        $totalCompras = round(
            $movActivos->where('subtipo', 'compra')->sum('monto'), 2
        );
        $totalSalidas = round(
            $movActivos->where('tipo', 'salida')->sum('monto'), 2
        );
        $balance = round($totalVentasCobradas + $totalOtrosIngresos - $totalSalidas, 2);

        // ── Desglose por método de pago (fuente: pago_metodos) ──────────
        $ventasPorMetodo = $this->calcularMetodosDePago($desde, $hasta, $ventasCobradas);

        // ── Por vendedor (display informativo) ──────────────────────────
        $ventasPorVendedor = $ventasCobradas
            ->groupBy(fn ($v) => $v->vendedor->nombre ?? 'Sin vendedor')
            ->map(fn ($g) => round($g->sum(fn ($v) => $v->total_cobrado), 2));

        $esRango = $desde !== $hasta;

        return view('casadets.caja.index', compact(
            'desde', 'hasta', 'hoy', 'esRango', 'empresa',
            'ventas', 'ventasCobradas', 'ventasPendientes',
            'movimientos', 'movActivos',
            'totalVentasCobradas', 'totalOtrosIngresos', 'totalCompras',
            'totalSalidas', 'balance',
            'ventasPorMetodo', 'ventasPorVendedor'
        ));
    }

    /**
     * Desglose exacto por método de pago para el período.
     * Fuente: pago_metodos (exacto, desde CobranzaService).
     */
    private function calcularMetodosDePago(string $desde, string $hasta, $ventasCobradas): \Illuminate\Support\Collection
    {
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

        // Fallback para ventas sin Pago registrado (datos legados)
        $ventasConPagoRegistrado = DetallePagoFactura::whereIn('venta_id', $ventasCobradas->pluck('id'))
            ->pluck('venta_id')
            ->unique();

        $ventasSinPago = $ventasCobradas->filter(
            fn ($v) => !$ventasConPagoRegistrado->contains($v->id) && !empty($v->metodo_pago)
        );

        $metodosDeVentasDirectas = $ventasSinPago
            ->groupBy('metodo_pago')
            ->map(fn ($g) => round($g->sum(fn ($v) => $v->total_cobrado), 2));

        $todos = $metodosDePagos->keys()->merge($metodosDeVentasDirectas->keys())->unique();

        return $todos->mapWithKeys(fn ($metodo) => [
            $metodo => round(
                ($metodosDePagos->get($metodo, 0)) + ($metodosDeVentasDirectas->get($metodo, 0)),
                2
            ),
        ])->sortKeys();
    }
}
