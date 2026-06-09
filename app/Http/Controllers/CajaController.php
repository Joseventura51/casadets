<?php

namespace App\Http\Controllers;

use App\Models\CajaSesion;
use App\Models\Compra;
use App\Models\DetallePagoFactura;
use App\Models\Movimiento;
use App\Models\Pago;
use App\Models\PagoMetodo;
use App\Models\Venta;
use App\Services\VendedorScope;
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

        // ── Sesión de caja del día actual ──────────────────────────────
        $sesionHoy = CajaSesion::where('empresa', $empresa)
            ->whereDate('fecha', $hoy)
            ->first();

        // ── Movimientos del período (fuente única financiera) ──────────
        $movimientos = Movimiento::with('cliente:id,nombre')
            ->where('empresa', $empresa)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // Ventas del período (para la tabla de display)
        $ventasQuery = Venta::with(['vendedor', 'detalles'])
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->whereNotIn('estado', ['anulado'])
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc');
        VendedorScope::aplicar($ventasQuery);
        $ventas = $ventasQuery->get();

        $ventasCobradas   = $ventas->where('estado', 'pagado');
        $ventasPendientes = $ventas->whereIn('estado', ['pendiente', 'parcial']);

        // ── KPIs desde movimientos activos ──────────────────────────────
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

        // ── Desglose por método de pago ──────────────────────────────────
        $ventasPorMetodo = $this->calcularMetodosDePago($desde, $hasta, $ventasCobradas);

        // ── Efectivo actual en caja ──────────────────────────────────────
        // apertura + cobrado_efectivo + ingresos_manuales_efectivo
        //          - compras_efectivo - salidas_manuales_efectivo
        $comprasEnEfectivo = round(
            Compra::where('metodo_pago', 'efectivo')
                ->whereDate('fecha', '>=', $desde)
                ->whereDate('fecha', '<=', $hasta)
                ->sum('monto_total'),
            2
        );

        $ingresosManualEfectivo = round(
            $movActivos->filter(
                fn ($m) => $m->tipo === 'ingreso'
                    && $m->subtipo === 'manual'
                    && $m->metodo_pago === 'efectivo'
            )->sum('monto'),
            2
        );

        $salidasManualEfectivo = round(
            $movActivos->filter(
                fn ($m) => $m->tipo === 'salida'
                    && $m->subtipo === 'manual'
                    && $m->metodo_pago === 'efectivo'
            )->sum('monto'),
            2
        );

        $efectivoEntradas = round(
            ($sesionHoy?->monto_apertura ?? 0)
            + $ventasPorMetodo->get('efectivo', 0)
            + $ingresosManualEfectivo,
            2
        );

        $efectivoSalidas = round($comprasEnEfectivo + $salidasManualEfectivo, 2);

        $efectivoEnCaja = round($efectivoEntradas - $efectivoSalidas, 2);

        // ── Por vendedor ─────────────────────────────────────────────────
        $ventasPorVendedor = $ventasCobradas
            ->groupBy(fn ($v) => $v->vendedor->nombre ?? 'Sin vendedor')
            ->map(fn ($g) => round($g->sum(fn ($v) => $v->total_cobrado), 2));

        $esRango = $desde !== $hasta;

        return view('casadets.caja.index', compact(
            'desde', 'hasta', 'hoy', 'esRango', 'empresa',
            'sesionHoy',
            'ventas', 'ventasCobradas', 'ventasPendientes',
            'movimientos', 'movActivos',
            'totalVentasCobradas', 'totalOtrosIngresos', 'totalCompras',
            'totalSalidas', 'balance',
            'ventasPorMetodo', 'ventasPorVendedor',
            'comprasEnEfectivo', 'ingresosManualEfectivo', 'salidasManualEfectivo',
            'efectivoEntradas', 'efectivoSalidas', 'efectivoEnCaja'
        ));
    }

    public function apertura(Request $request)
    {
        $request->validate([
            'empresa'         => 'required|string',
            'monto_apertura'  => 'required|numeric|min:0',
            'observaciones'   => 'nullable|string|max:500',
        ]);

        $hoy = Carbon::today()->toDateString();

        $sesion = CajaSesion::where('empresa', $request->empresa)
            ->whereDate('fecha', $hoy)
            ->first();

        if ($sesion) {
            $sesion->update([
                'monto_apertura' => $request->monto_apertura,
                'estado'         => 'abierta',
                'observaciones'  => $request->observaciones,
            ]);
        } else {
            CajaSesion::create([
                'empresa'        => $request->empresa,
                'fecha'          => $hoy,
                'monto_apertura' => $request->monto_apertura,
                'estado'         => 'abierta',
                'observaciones'  => $request->observaciones,
            ]);
        }

        return redirect("/casadets/caja?empresa={$request->empresa}")
            ->with('success', 'Apertura de caja registrada.');
    }

    public function cierre(Request $request)
    {
        $request->validate([
            'empresa'       => 'required|string',
            'monto_cierre'  => 'required|numeric|min:0',
        ]);

        $hoy = Carbon::today()->toDateString();

        $sesion = CajaSesion::where('empresa', $request->empresa)
            ->whereDate('fecha', $hoy)
            ->first();

        if (!$sesion) {
            return back()->withErrors(['No hay apertura registrada para hoy.']);
        }

        $sesion->update([
            'monto_cierre' => $request->monto_cierre,
            'estado'       => 'cerrada',
        ]);

        return redirect("/casadets/caja?empresa={$request->empresa}")
            ->with('success', 'Caja cerrada correctamente.');
    }

    /**
     * Desglose exacto por método de pago para el período.
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
