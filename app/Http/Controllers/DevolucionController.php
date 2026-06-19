<?php

namespace App\Http\Controllers;

use App\Models\Devolucion;
use App\Models\DevolucionDetalle;
use App\Models\DetallePagoFactura;
use App\Models\Movimiento;
use App\Models\Producto;
use App\Models\SaldoFavor;
use App\Models\Serie;
use App\Models\StockMovimiento;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DevolucionController extends Controller
{
    /* ─── Listado / búsqueda ─────────────────────────────────────── */

    public function index(Request $request)
    {
        // ── Filtro por caja activa ─────────────────────────────────────────
        $cajaId        = session('caja_id');
        $seriesCodigos = $cajaId
            ? Serie::where('caja_id', $cajaId)->pluck('codigo')
            : collect();

        // Closure reutilizable: restringe una query de Venta a las series de la caja
        $aplicarFiltroCaja = function ($query) use ($cajaId, $seriesCodigos) {
            if (!$cajaId) return;
            if ($seriesCodigos->isNotEmpty()) {
                $query->where(function ($q) use ($cajaId, $seriesCodigos) {
                    foreach ($seriesCodigos as $cod) {
                        $q->orWhere('documento_numero', 'like', $cod . '-%');
                    }
                    $q->orWhere(fn ($q2) => $q2->where('caja_id', $cajaId)->whereNull('documento_numero'));
                });
            } else {
                $query->where('caja_id', $cajaId);
            }
        };

        // ── Búsqueda de vales ─────────────────────────────────────────────
        $ventas = collect();

        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $ventasQuery = Venta::with(['cliente', 'detalles'])
                ->where('estado', '!=', 'anulado')
                ->where(function ($query) use ($q) {
                    $query->where('documento_numero', 'like', "%$q%")
                        ->orWhereHas('cliente', fn ($c) =>
                            $c->where('nombre', 'like', "%$q%")
                              ->orWhere('documento', 'like', "%$q%")
                        );
                })
                ->orderByDesc('fecha')
                ->limit(30);

            $aplicarFiltroCaja($ventasQuery);
            $ventas = $ventasQuery->get();
        }

        // ── Devoluciones recientes (filtradas por series de la caja) ───────
        $recientesQuery = Devolucion::with(['venta.cliente', 'user'])
            ->orderByDesc('created_at')
            ->limit(20);

        if ($cajaId) {
            $recientesQuery->whereHas('venta', function ($q) use ($aplicarFiltroCaja) {
                $aplicarFiltroCaja($q);
            });
        }

        $recientes = $recientesQuery->get();

        // ── Vales anulados (filtrados por series de la caja) ──────────────
        $anuladasQuery = Venta::with(['cliente'])
            ->where('estado', 'anulado')
            ->orderByDesc('fecha')
            ->limit(20);

        $aplicarFiltroCaja($anuladasQuery);
        $anuladas = $anuladasQuery->get();

        return view('casadets.devoluciones.index', compact('ventas', 'recientes', 'anuladas'));
    }

    /* ─── Formulario de devolución ───────────────────────────────── */

    public function show(Venta $venta)
    {
        // Eager load incluyendo producto para evitar N+1 en la vista
        $venta->load(['cliente', 'detalles.producto', 'vendedor', 'caja']);

        // Cantidad ya devuelta por producto (de devoluciones anteriores)
        $yaDevuelto = DevolucionDetalle::whereHas('devolucion', fn ($d) => $d->where('venta_id', $venta->id))
            ->selectRaw('venta_detalle_id, SUM(cantidad_devuelta) as total_devuelto')
            ->groupBy('venta_detalle_id')
            ->pluck('total_devuelto', 'venta_detalle_id')
            ->toArray();

        // Eager load ventaDetalle para mostrar nombre de producto en historial sin N+1
        $devoluciones = Devolucion::with(['detalles.ventaDetalle', 'user'])
            ->where('venta_id', $venta->id)
            ->orderByDesc('created_at')
            ->get();

        return view('casadets.devoluciones.show', compact('venta', 'yaDevuelto', 'devoluciones'));
    }

    /* ─── Procesar devolución parcial ────────────────────────────── */

    public function store(Request $request, Venta $venta)
    {
        if ($venta->estado === 'anulado') {
            return back()->with('error', 'Este vale ya está anulado.');
        }

        $request->validate([
            'cantidades'      => 'required|array',
            'cantidades.*'    => 'nullable|numeric|min:0',
            'motivo'          => 'nullable|string|max:255',
        ]);

        $cantidades = $request->input('cantidades', []);

        // Calcular monto a devolver y validar cantidades
        $venta->load(['detalles', 'caja']);

        $yaDevuelto = DevolucionDetalle::whereHas('devolucion', fn ($d) => $d->where('venta_id', $venta->id))
            ->selectRaw('venta_detalle_id, SUM(cantidad_devuelta) as total_devuelto')
            ->groupBy('venta_detalle_id')
            ->pluck('total_devuelto', 'venta_detalle_id')
            ->toArray();

        $lineas = [];
        $montoDevuelto = 0;

        foreach ($venta->detalles as $detalle) {
            $cantDevolver = (float) ($cantidades[$detalle->id] ?? 0);
            if ($cantDevolver <= 0) {
                continue;
            }
            $maxDevolvible = (float) $detalle->cantidad - (float) ($yaDevuelto[$detalle->id] ?? 0);
            if ($cantDevolver > $maxDevolvible) {
                return back()->withErrors([
                    "cantidades.{$detalle->id}" => "La cantidad a devolver ({$cantDevolver}) supera lo disponible ({$maxDevolvible}) para " . ($detalle->getRawOriginal('producto') ?? "detalle #{$detalle->id}") . ".",
                ])->withInput();
            }
            $subtotal = round($cantDevolver * (float) $detalle->precio_unitario, 2);
            $lineas[] = [
                'detalle'          => $detalle,
                'cantidad_devuelta' => $cantDevolver,
                'precio_unitario'  => (float) $detalle->precio_unitario,
                'subtotal'         => $subtotal,
            ];
            $montoDevuelto += $subtotal;
        }

        if (empty($lineas)) {
            return back()->with('error', 'Debe seleccionar al menos un producto con cantidad mayor a 0.');
        }

        $montoDevuelto = round($montoDevuelto, 2);

        DB::transaction(function () use ($venta, $lineas, $montoDevuelto, $request) {
            $cajaId  = session('caja_id');
            $empresa = $venta->caja?->empresa ?? session('empresa', 'casadets');

            // 1. Crear registro de devolución
            $devolucion = Devolucion::create([
                'venta_id'       => $venta->id,
                'user_id'        => auth()->id(),
                'tipo'           => 'parcial',
                'monto_devuelto' => $montoDevuelto,
                'saldo_generado' => 0,
                'motivo'         => $request->input('motivo'),
                'fecha'          => today(),
                'empresa'        => $empresa,
                'caja_id'        => $cajaId,
            ]);

            // 2. Detalle de la devolución + stock
            foreach ($lineas as $linea) {
                DevolucionDetalle::create([
                    'devolucion_id'     => $devolucion->id,
                    'venta_detalle_id'  => $linea['detalle']->id,
                    'producto_id'       => $linea['detalle']->producto_id,
                    'cantidad_devuelta' => $linea['cantidad_devuelta'],
                    'precio_unitario'   => $linea['precio_unitario'],
                    'subtotal'          => $linea['subtotal'],
                ]);

                // Retornar stock al inventario
                if ($linea['detalle']->producto_id) {
                    StockMovimiento::create([
                        'producto_id'     => $linea['detalle']->producto_id,
                        'tipo'            => 'entrada',
                        'cantidad'        => $linea['cantidad_devuelta'],
                        'precio_unitario' => $linea['precio_unitario'],
                        'referencia_tipo' => 'devolucion',
                        'referencia_id'   => $devolucion->id,
                        'fecha'           => today(),
                        'observaciones'   => 'Devolución venta #' . $venta->id,
                    ]);
                    Producto::find($linea['detalle']->producto_id)?->recalcularStock();
                }
            }

            // 3. Reducir el total de la venta via ajuste
            $nuevoAjuste = round((float) $venta->ajuste - $montoDevuelto, 2);
            $venta->update(['ajuste' => $nuevoAjuste]);
            $venta->refresh();

            // 4. Si lo pagado supera el nuevo total, generar saldo a favor
            $nuevoTotalCobrar = $venta->total_a_cobrar;
            $saldoGenerado    = 0;
            if ($nuevoTotalCobrar < (float) $venta->pagado && $venta->cliente_id) {
                $saldoGenerado = round((float) $venta->pagado - max(0, $nuevoTotalCobrar), 2);
                if ($saldoGenerado > 0) {
                    SaldoFavor::create([
                        'cliente_id'       => $venta->cliente_id,
                        'pago_id'          => null,
                        'caja_id'          => $cajaId,
                        'venta_origen_id'  => $venta->id,
                        'monto_original'   => $saldoGenerado,
                        'monto_disponible' => $saldoGenerado,
                        'estado'           => 'disponible',
                        'descripcion'      => 'Devolución parcial - Vale ' . ($venta->documento_numero ?: '#' . $venta->id),
                        'fecha'            => today(),
                    ]);
                    $devolucion->update(['saldo_generado' => $saldoGenerado]);
                }
            }

            // 5. Movimiento en el ledger (salida de caja por devolución)
            if ($nuevoTotalCobrar < (float) $venta->pagado && $cajaId) {
                Movimiento::create([
                    'tipo'            => 'salida',
                    'subtipo'         => 'devolucion',
                    'origen'          => 'auto',
                    'estado'          => 'activo',
                    'empresa'         => $empresa,
                    'caja_id'         => $cajaId,
                    'categoria'       => 'devolucion',
                    'referencia_tipo' => 'devolucion',
                    'referencia_id'   => $devolucion->id,
                    'cliente_id'      => $venta->cliente_id,
                    'user_id'         => auth()->id(),
                    'monto'           => $montoDevuelto,
                    'fecha'           => today(),
                    'observaciones'   => 'Devolución parcial - Vale ' . ($venta->documento_numero ?: '#' . $venta->id),
                ]);
            }

            // 6. Recalcular estado del vale
            $venta->recalcularEstado();
        });

        return redirect("/casadets/devoluciones/{$venta->id}")
            ->with('success', 'Devolución registrada por S/ ' . number_format($montoDevuelto, 2) . '.');
    }

    /* ─── Anular vale completo ───────────────────────────────────── */

    public function anular(Request $request, Venta $venta)
    {
        if ($venta->estado === 'anulado') {
            return back()->with('info', 'Este vale ya está anulado.');
        }

        $request->validate([
            'motivo' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($venta, $request) {
            $cajaId  = session('caja_id');
            $venta->load(['detalles', 'caja']);
            $empresa = $venta->caja?->empresa ?? session('empresa', 'casadets');
            $docRef  = $venta->documento_numero ?: '#' . $venta->id;

            // 1. Crear registro de devolución total
            $devolucion = Devolucion::create([
                'venta_id'       => $venta->id,
                'user_id'        => auth()->id(),
                'tipo'           => 'total',
                'monto_devuelto' => (float) $venta->total_a_cobrar,
                'saldo_generado' => 0,
                'motivo'         => $request->input('motivo') ?: 'Anulación completa',
                'fecha'          => today(),
                'empresa'        => $empresa,
                'caja_id'        => $cajaId,
            ]);

            // 2. Registrar todos los detalles y retornar stock de los que tienen producto vinculado
            foreach ($venta->detalles as $detalle) {
                DevolucionDetalle::create([
                    'devolucion_id'     => $devolucion->id,
                    'venta_detalle_id'  => $detalle->id,
                    'producto_id'       => $detalle->producto_id,
                    'cantidad_devuelta' => (float) $detalle->cantidad,
                    'precio_unitario'   => (float) $detalle->precio_unitario,
                    'subtotal'          => (float) $detalle->subtotal,
                ]);

                if ($detalle->producto_id) {
                    StockMovimiento::create([
                        'producto_id'     => $detalle->producto_id,
                        'tipo'            => 'entrada',
                        'cantidad'        => (float) $detalle->cantidad,
                        'precio_unitario' => (float) $detalle->precio_unitario,
                        'referencia_tipo' => 'devolucion',
                        'referencia_id'   => $devolucion->id,
                        'fecha'           => today(),
                        'observaciones'   => 'Anulación venta #' . $venta->id,
                    ]);
                    Producto::find($detalle->producto_id)?->recalcularStock();
                }
            }

            // 3. Anular movimientos del ledger relacionados a pagos
            $pagoIds = DetallePagoFactura::where('venta_id', $venta->id)->pluck('pago_id');
            if ($pagoIds->isNotEmpty()) {
                Movimiento::where('referencia_tipo', 'pago')
                    ->whereIn('referencia_id', $pagoIds)
                    ->where('estado', 'activo')
                    ->each(function ($m) use ($venta) {
                        $m->update([
                            'estado'        => 'anulado',
                            'observaciones' => trim(($m->observaciones ?? '') . ' [Anulado: venta #' . $venta->id . ' cancelada]'),
                        ]);
                    });
            }

            // 4. Movimiento de salida en el ledger por la anulación
            if ($cajaId) {
                Movimiento::create([
                    'tipo'            => 'salida',
                    'subtipo'         => 'anulacion',
                    'origen'          => 'auto',
                    'estado'          => 'activo',
                    'empresa'         => $empresa,
                    'caja_id'         => $cajaId,
                    'categoria'       => 'devolucion',
                    'referencia_tipo' => 'devolucion',
                    'referencia_id'   => $devolucion->id,
                    'cliente_id'      => $venta->cliente_id,
                    'user_id'         => auth()->id(),
                    'monto'           => (float) $venta->total_a_cobrar,
                    'fecha'           => today(),
                    'observaciones'   => 'Anulación de vale ' . $docRef,
                ]);
            }

            // 5. Si había pagos, crear saldo a favor
            $pagado = (float) $venta->pagado;
            if ($pagado > 0 && $venta->cliente_id) {
                SaldoFavor::create([
                    'cliente_id'       => $venta->cliente_id,
                    'pago_id'          => null,
                    'caja_id'          => $cajaId,
                    'venta_origen_id'  => $venta->id,
                    'monto_original'   => $pagado,
                    'monto_disponible' => $pagado,
                    'estado'           => 'disponible',
                    'descripcion'      => 'Anulación de vale ' . $docRef,
                    'fecha'            => today(),
                ]);
                $devolucion->update(['saldo_generado' => $pagado]);
            }

            // 6. Marcar venta como anulada
            $venta->update(['estado' => 'anulado']);
        });

        return redirect('/casadets/devoluciones')
            ->with('success', 'Vale anulado correctamente.');
    }
}
