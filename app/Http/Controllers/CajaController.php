<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Models\CajaSesion;
use App\Models\Compra;
use App\Models\DetallePagoFactura;
use App\Models\Movimiento;
use App\Models\Pago;
use App\Models\PagoMetodo;
use App\Models\Serie;
use App\Models\Venta;
use App\Services\CajaService;
use App\Services\ReporteCajaService;
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
        if ($hasta < $desde) $hasta = $desde;

        // ── Cajas disponibles para el usuario (todas las empresas) ──────────
        $cajasDisponibles = CajaService::cajasUsuario()->values();

        // ── Caja seleccionada ───────────────────────────────────────────────
        $cajaId = $request->input('caja_id', session('caja_id'));

        // Si hay parámetro en URL, actualizar sesión
        if ($request->has('caja_id') && $cajaId) {
            session(['caja_id' => $cajaId]);
        }

        // Auto-seleccionar si solo hay una caja disponible
        if (!$cajaId && $cajasDisponibles->count() === 1) {
            $cajaId = $cajasDisponibles->first()->id;
            session(['caja_id' => $cajaId]);
        }

        $cajaSeleccionada = $cajaId ? Caja::find($cajaId) : null;

        // Derivar empresa desde la caja seleccionada, o usar el primero disponible como fallback
        $empresa = $cajaSeleccionada?->empresa
            ?? $cajasDisponibles->first()?->empresa
            ?? $request->input('empresa', 'casadets');

        // ── Sesión de caja del día actual ──────────────────────────────────
        $sesionHoy = null;
        if ($cajaSeleccionada) {
            $sesionHoy = CajaSesion::where('caja_id', $cajaSeleccionada->id)
                ->whereDate('fecha', $hoy)
                ->latest()
                ->first();
        } else {
            // Fallback histórico por empresa
            $sesionHoy = CajaSesion::where('empresa', $empresa)
                ->whereNull('caja_id')
                ->whereDate('fecha', $hoy)
                ->first();
        }

        // ── Historial de sesiones del día (multi-apertura/cierre) ──────────
        $sesionesHoy = collect();
        if ($cajaSeleccionada) {
            $sesionesHoy = CajaSesion::where('caja_id', $cajaSeleccionada->id)
                ->whereDate('fecha', $hoy)
                ->orderBy('id')
                ->get();
        }

        // ── Base query de movimientos ────────────────────────────────────
        $movQuery = Movimiento::with('cliente:id,nombre')
            ->where('empresa', $empresa)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc');

        if ($cajaSeleccionada) {
            $movQuery->where('caja_id', $cajaSeleccionada->id);
        }

        $movimientos = $movQuery->get();

        // Ventas del período — filtradas por las series asignadas a la caja
        $ventasQuery = Venta::with(['vendedor', 'detalles'])
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->whereNotIn('estado', ['anulado'])
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc');

        if ($cajaSeleccionada) {
            $seriesCodigos = Serie::where('caja_id', $cajaSeleccionada->id)->pluck('codigo');
            if ($seriesCodigos->isNotEmpty()) {
                $ventasQuery->where(function ($q) use ($cajaSeleccionada, $seriesCodigos) {
                    foreach ($seriesCodigos as $cod) {
                        $q->orWhere('documento_numero', 'like', $cod . '-%');
                    }
                    $q->orWhere(fn ($q2) => $q2->where('caja_id', $cajaSeleccionada->id)->whereNull('documento_numero'));
                });
            } else {
                $ventasQuery->where('caja_id', $cajaSeleccionada->id);
            }
        }

        VendedorScope::aplicar($ventasQuery);
        $ventas = $ventasQuery->get();

        $ventasCobradas   = $ventas->where('estado', 'pagado');
        $ventasPendientes = $ventas->whereIn('estado', ['pendiente', 'parcial']);

        // ── KPIs desde movimientos activos ──────────────────────────────────
        $movActivos = $movimientos->where('estado', 'activo');

        $totalVentasCobradas = round($movActivos->where('subtipo', 'pago_venta')->sum('monto'), 2);
        $totalOtrosIngresos  = round($movActivos->filter(
            fn ($m) => $m->tipo === 'ingreso' && $m->subtipo !== 'pago_venta'
        )->sum('monto'), 2);
        $totalCompras = round($movActivos->where('subtipo', 'compra')->sum('monto'), 2);
        $totalSalidas = round($movActivos->where('tipo', 'salida')->sum('monto'), 2);
        $balance      = round($totalVentasCobradas + $totalOtrosIngresos - $totalSalidas, 2);

        // ── Desglose por método de pago ─────────────────────────────────────
        $ventasPorMetodo = $this->calcularMetodosDePago($desde, $hasta, $ventasCobradas, $cajaSeleccionada?->id, $movActivos);

        // ── Efectivo actual en caja ─────────────────────────────────────────
        $comprasQuery = Compra::where('metodo_pago', 'efectivo')
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta);
        if ($cajaSeleccionada) {
            $comprasQuery->where('caja_id', $cajaSeleccionada->id);
        }
        $comprasEnEfectivo = round($comprasQuery->sum('monto_total'), 2);

        $ingresosManualEfectivo = round($movActivos->filter(
            fn ($m) => $m->tipo === 'ingreso' && $m->subtipo === 'manual' && $m->metodo_pago === 'efectivo'
        )->sum('monto'), 2);

        $salidasManualEfectivo = round($movActivos->filter(
            fn ($m) => $m->tipo === 'salida' && $m->subtipo === 'manual' && $m->metodo_pago === 'efectivo'
        )->sum('monto'), 2);

        // Suma monto_apertura de todas las sesiones del día.
        // Si no hay caja seleccionada pero existe sesión de fallback, usarla igual.
        $montoAperturaDia = $sesionesHoy->isNotEmpty()
            ? $sesionesHoy->sum('monto_apertura')
            : ($sesionHoy?->monto_apertura ?? 0);

        $efectivoEntradas = round($montoAperturaDia + $ventasPorMetodo->get('efectivo', 0) + $ingresosManualEfectivo, 2);
        $efectivoSalidas  = round($comprasEnEfectivo + $salidasManualEfectivo, 2);
        $efectivoEnCaja   = round($efectivoEntradas - $efectivoSalidas, 2);

        // ── Por vendedor ─────────────────────────────────────────────────────
        $ventasPorVendedor = $ventasCobradas
            ->groupBy(fn ($v) => $v->vendedor->nombre ?? 'Sin vendedor')
            ->map(fn ($g) => round($g->sum(fn ($v) => $v->total_cobrado), 2));

        $esRango = $desde !== $hasta;

        return view('casadets.caja.index', compact(
            'desde', 'hasta', 'hoy', 'esRango', 'empresa',
            'cajasDisponibles', 'cajaSeleccionada',
            'sesionHoy', 'sesionesHoy',
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
            'caja_id'         => 'nullable|integer|exists:cajas,id',
            'empresa'         => 'required|string',
            'monto_apertura'  => 'required|numeric|min:0',
            'observaciones'   => 'nullable|string|max:500',
        ]);

        $cajaId = $request->caja_id;

        if ($cajaId) {
            $caja = Caja::findOrFail($cajaId);
            try {
                CajaService::abrirCaja($caja, (float) $request->monto_apertura, $request->observaciones);
            } catch (\RuntimeException $e) {
                return back()->with('error', $e->getMessage());
            }
            session(['caja_id' => $caja->id]);
            return redirect("/casadets/caja?empresa={$request->empresa}&caja_id={$caja->id}")
                ->with('success', "Apertura de {$caja->codigo} registrada.");
        }

        // Fallback sin caja específica (compatibilidad histórica)
        $hoy = Carbon::today()->toDateString();
        $sesion = CajaSesion::where('empresa', $request->empresa)
            ->whereNull('caja_id')
            ->whereDate('fecha', $hoy)
            ->first();

        if ($sesion && $sesion->estado === 'abierta') {
            return back()->with('error', 'La caja ya se encuentra abierta. Debe cerrarla antes de realizar una nueva apertura.');
        }

        CajaSesion::create([
            'empresa'        => $request->empresa,
            'fecha'          => $hoy,
            'monto_apertura' => $request->monto_apertura,
            'estado'         => 'abierta',
            'observaciones'  => $request->observaciones,
        ]);

        return redirect("/casadets/caja?empresa={$request->empresa}")
            ->with('success', 'Apertura de caja registrada.');
    }

    public function cierre(Request $request)
    {
        $request->validate([
            'caja_id'      => 'nullable|integer|exists:cajas,id',
            'empresa'      => 'required|string',
            'monto_cierre' => 'required|numeric|min:0',
        ]);

        $cajaId = $request->caja_id;

        if ($cajaId) {
            $caja = Caja::findOrFail($cajaId);
            try {
                $sesion = CajaService::cerrarCaja($caja, (float) $request->monto_cierre);
            } catch (\RuntimeException $e) {
                return back()->with('error', $e->getMessage());
            }
            try {
                ReporteCajaService::generar($sesion);
            } catch (\Throwable $e) {
                \Log::error('ReporteCaja::generar falló', [
                    'sesion_id' => $sesion->id,
                    'error'     => $e->getMessage(),
                    'file'      => basename($e->getFile()) . ':' . $e->getLine(),
                ]);
            }
            return redirect("/casadets/caja?empresa={$request->empresa}&caja_id={$caja->id}")
                ->with('success', "Caja {$caja->codigo} cerrada correctamente. Se generó el reporte Excel.");
        }

        // Fallback sin caja
        $hoy    = Carbon::today()->toDateString();
        $sesion = CajaSesion::where('empresa', $request->empresa)
            ->whereNull('caja_id')
            ->whereDate('fecha', $hoy)
            ->where('estado', 'abierta')
            ->first();

        if (!$sesion) {
            return back()->withErrors(['No hay apertura registrada para hoy.']);
        }

        $sesion->update(['monto_cierre' => $request->monto_cierre, 'estado' => 'cerrada']);
        $sesion->refresh();
        try {
            ReporteCajaService::generar($sesion);
        } catch (\Throwable $e) {
            \Log::error('ReporteCaja::generar falló (fallback)', [
                'sesion_id' => $sesion->id,
                'error'     => $e->getMessage(),
                'file'      => basename($e->getFile()) . ':' . $e->getLine(),
            ]);
        }

        return redirect("/casadets/caja?empresa={$request->empresa}")
            ->with('success', 'Caja cerrada correctamente. Se generó el reporte Excel.');
    }

    private function calcularMetodosDePago(string $desde, string $hasta, $ventasCobradas, ?int $cajaId = null, $movActivos = null): \Illuminate\Support\Collection
    {
        // Fuente primaria: movimientos activos de pago_venta con referencia_tipo='pago'.
        // Esto es consistente con el KPI "Ventas cobradas" y cubre pagos parciales.
        if ($movActivos !== null) {
            $pagoIdsDeMovimientos = $movActivos
                ->where('subtipo', 'pago_venta')
                ->where('referencia_tipo', 'pago')
                ->pluck('referencia_id')
                ->filter()
                ->unique();

            if ($pagoIdsDeMovimientos->isNotEmpty()) {
                return PagoMetodo::whereIn('pago_id', $pagoIdsDeMovimientos)
                    ->selectRaw('metodo, SUM(monto) as total')
                    ->groupBy('metodo')
                    ->pluck('total', 'metodo')
                    ->map(fn ($t) => round((float) $t, 2))
                    ->sortKeys();
            }
        }

        // Fallback: pagos vinculados a ventas totalmente cobradas (comportamiento original)
        $ventaIds = $ventasCobradas->pluck('id');
        $pagoIds = $ventaIds->isNotEmpty()
            ? DetallePagoFactura::whereIn('venta_id', $ventaIds)->pluck('pago_id')->unique()
            : collect();

        $metodosDePagos = collect();
        if ($pagoIds->isNotEmpty()) {
            $metodosDePagos = PagoMetodo::whereIn('pago_id', $pagoIds)
                ->selectRaw('metodo, SUM(monto) as total')
                ->groupBy('metodo')
                ->pluck('total', 'metodo')
                ->map(fn ($t) => (float) $t);
        }

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
