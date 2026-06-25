<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\CompraLinea;
use App\Models\Movimiento;
use App\Models\Producto;
use App\Models\StockMovimiento;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Services\CajaService;
use App\Services\ConciliacionService;
use App\Services\VendedorScope;
use Illuminate\Support\Facades\DB;

class CompraController extends Controller
{
    public function __construct(private readonly ConciliacionService $conciliacion) {}

    public function index(Request $request)
    {
        $desde = $request->input('desde', today()->toDateString());
        $hasta = $request->input('hasta', $desde);
        if ($hasta < $desde) $hasta = $desde;

        $query = Compra::with([
                'lineas:id,compra_id,producto,cantidad,monto_total',
                'detalles:id,venta_id',
                'detalles.venta:id,documento_tipo,documento_numero',
            ])
            ->select('id', 'empresa', 'documento_tipo', 'documento_numero', 'fecha', 'metodo_pago', 'monto_total', 'observaciones')
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc');

        // Restricción por vendedor asignado al usuario
        VendedorScope::aplicarCompras($query);

        if (session('caja_id')) {
            $query->where('caja_id', session('caja_id'));
        }

        if ($request->filled('empresa')) {
            $query->where('empresa', 'like', '%' . $request->empresa . '%');
        }
        $query->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta);

        $totalFiltrado = (clone $query)->sum('monto_total');
        $compras = $query->paginate(9999)->withQueryString();

        $cajaAbierta = CajaService::cajaAbierta();

        return view('casadets.compras.index', compact('compras', 'desde', 'hasta', 'cajaAbierta', 'totalFiltrado'));
    }

    public function create()
    {
        $facturas = $this->facturasDisponibles();
        $compra   = new Compra();
        $detallesSeleccionados = [];
        return view('casadets.compras.create', compact('facturas', 'compra', 'detallesSeleccionados'));
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);

        DB::transaction(function () use ($data, $request) {
            $lineas = $data['lineas'] ?? [];
            $total  = collect($lineas)->sum('monto_total');

            $compra = Compra::create(array_merge(
                collect($data)->except('lineas')->toArray(),
                ['monto_total' => $total]
            ));

            $lineasCreadas = [];
            foreach ($lineas as $idx => $l) {
                $producto = $this->resolverProducto($l['producto'] ?? null, $l['monto_unitario']);

                $linea = $compra->lineas()->create(array_merge($l, [
                    'producto_id' => $producto?->id,
                ]));
                $lineasCreadas[(int) $idx] = $linea->id;

                if ($producto) {
                    StockMovimiento::create([
                        'producto_id'    => $producto->id,
                        'tipo'           => 'entrada',
                        'cantidad'       => $l['cantidad'],
                        'costo_unitario' => $l['monto_unitario'],
                        'referencia_tipo'=> 'compra',
                        'referencia_id'  => $compra->id,
                        'fecha'          => $data['fecha'],
                    ]);

                    $producto->update(['precio_costo' => $l['monto_unitario']]);
                    $producto->recalcularStock();
                }
            }

            // ── Movimiento de salida en el ledger ─────────────────────
            $metodoLabel = $compra->metodo_pago ? ' [' . ucfirst($compra->metodo_pago) . ']' : '';
            Movimiento::create([
                'tipo'             => 'salida',
                'subtipo'          => 'compra',
                'origen'           => 'auto',
                'estado'           => 'activo',
                'empresa'          => 'casadets',
                'caja_id'          => session('caja_id'),
                'categoria'        => 'Compra — ' . $compra->empresa,
                'referencia_tipo'  => 'compra',
                'referencia_id'    => $compra->id,
                'documento_tipo'   => $compra->documento_tipo,
                'documento_numero' => $compra->documento_numero,
                'monto'            => $total,
                'fecha'            => $data['fecha'],
                'observaciones'    => $compra->empresa . $metodoLabel . ($compra->observaciones ? ' — ' . $compra->observaciones : ''),
            ]);

            $syncData = $this->buildSyncData($request, $lineasCreadas);
            $this->conciliacion->sincronizar($compra, $syncData, $compra->id);
        });

        return redirect('/casadets/compras')->with('success', 'Compra registrada.');
    }

    public function show(Compra $compra)
    {
        $compra->load(['lineas.producto', 'detalles.venta.vendedor', 'auditorias.usuario']);
        return view('casadets.compras.show', compact('compra'));
    }

    public function edit(Compra $compra)
    {
        $compra->load(['lineas', 'detalles.venta']);
        $facturas = $this->facturasDisponibles();
        $detallesSeleccionados = $compra->detalles->pluck('id')->toArray();

        $ventasYa = $compra->detalles->pluck('venta')->filter()->unique('id');
        foreach ($ventasYa as $v) {
            if (!$facturas->contains('id', $v->id)) {
                $facturas->push($v);
            }
        }

        return view('casadets.compras.edit', compact('compra', 'facturas', 'detallesSeleccionados'));
    }

    public function update(Request $request, Compra $compra)
    {
        $data = $this->validar($request);

        DB::transaction(function () use ($data, $request, $compra) {
            $lineas = $data['lineas'] ?? [];
            $total  = collect($lineas)->sum('monto_total');

            $compra->update(array_merge(
                collect($data)->except('lineas')->toArray(),
                ['monto_total' => $total]
            ));

            $productoIdsAntes = $compra->lineas()->pluck('producto_id')->filter()->unique();

            StockMovimiento::where('referencia_tipo', 'compra')
                ->where('referencia_id', $compra->id)
                ->delete();

            $compra->lineas()->delete();

            $lineasCreadas = [];
            foreach ($lineas as $idx => $l) {
                $producto = $this->resolverProducto($l['producto'] ?? null, $l['monto_unitario']);

                $linea = $compra->lineas()->create(array_merge($l, [
                    'producto_id' => $producto?->id,
                ]));
                $lineasCreadas[(int) $idx] = $linea->id;

                if ($producto) {
                    StockMovimiento::create([
                        'producto_id'    => $producto->id,
                        'tipo'           => 'entrada',
                        'cantidad'       => $l['cantidad'],
                        'costo_unitario' => $l['monto_unitario'],
                        'referencia_tipo'=> 'compra',
                        'referencia_id'  => $compra->id,
                        'fecha'          => $data['fecha'],
                    ]);

                    $producto->update(['precio_costo' => $l['monto_unitario']]);
                }
            }

            $productoIdsAhora = $compra->lineas()->pluck('producto_id')->filter()->unique();
            foreach ($productoIdsAntes->merge($productoIdsAhora)->unique() as $pid) {
                Producto::find($pid)?->recalcularStock();
            }

            // ── Actualizar movimiento existente del ledger ───────────────
            // Corrección de compra = actualización del egreso registrado
            $movExistente = Movimiento::where('referencia_tipo', 'compra')
                ->where('referencia_id', $compra->id)
                ->where('estado', 'activo')
                ->first();

            $metodoLabel = $compra->metodo_pago ? ' [' . ucfirst($compra->metodo_pago) . ']' : '';
            if ($movExistente) {
                $movExistente->update([
                    'monto'            => $total,
                    'fecha'            => $data['fecha'],
                    'documento_tipo'   => $compra->documento_tipo,
                    'documento_numero' => $compra->documento_numero,
                    'categoria'        => 'Compra — ' . $compra->empresa,
                    'observaciones'    => $compra->empresa . $metodoLabel . ($compra->observaciones ? ' — ' . $compra->observaciones : ''),
                ]);
            } else {
                Movimiento::create([
                    'tipo'             => 'salida',
                    'subtipo'          => 'compra',
                    'origen'           => 'auto',
                    'estado'           => 'activo',
                    'empresa'          => 'casadets',
                    'caja_id'          => session('caja_id'),
                    'categoria'        => 'Compra — ' . $compra->empresa,
                    'referencia_tipo'  => 'compra',
                    'referencia_id'    => $compra->id,
                    'documento_tipo'   => $compra->documento_tipo,
                    'documento_numero' => $compra->documento_numero,
                    'monto'            => $total,
                    'fecha'            => $data['fecha'],
                    'observaciones'    => $compra->empresa . $metodoLabel . ($compra->observaciones ? ' — ' . $compra->observaciones : ''),
                ]);
            }

            $syncData = $this->buildSyncData($request, $lineasCreadas);
            $this->conciliacion->sincronizar($compra, $syncData, $compra->id);
        });

        return redirect('/casadets/compras/' . $compra->id)->with('success', 'Compra actualizada.');
    }

    public function destroy(Compra $compra)
    {
        DB::transaction(function () use ($compra) {
            $productoIds = $compra->lineas()->pluck('producto_id')->filter()->unique();

            StockMovimiento::where('referencia_tipo', 'compra')
                ->where('referencia_id', $compra->id)
                ->delete();

            // Anular movimiento del ledger (NO borrar — ledger inmutable)
            Movimiento::where('referencia_tipo', 'compra')
                ->where('referencia_id', $compra->id)
                ->where('estado', 'activo')
                ->each(function ($m) use ($compra) {
                    $m->update([
                        'estado'       => 'anulado',
                        'observaciones' => trim(($m->observaciones ?? '') . ' [Anulado: compra #' . $compra->id . ' eliminada]'),
                    ]);
                });

            $compra->delete();

            foreach ($productoIds as $pid) {
                Producto::find($pid)?->recalcularStock();
            }
        });

        return redirect('/casadets/compras')->with('success', 'Compra eliminada.');
    }

    public function detallesVenta(Venta $venta)
    {
        $venta->load(['detalles:id,venta_id,producto_id,producto,cantidad,precio_unitario,subtotal', 'vendedor:id,nombre']);
        return response()->json([
            'venta' => [
                'id'        => $venta->id,
                'fecha'     => $venta->fecha->format('d/m/Y'),
                'documento' => trim(ucfirst((string) $venta->documento_tipo) . ' ' . (string) $venta->documento_numero),
                'vendedor'  => $venta->vendedor->nombre ?? '—',
            ],
            'detalles' => $venta->detalles->map(fn($d) => [
                'id'              => $d->id,
                'producto_id'     => $d->producto_id,
                'producto'        => $d->producto,
                'cantidad'        => (float) $d->cantidad,
                'precio_unitario' => (float) $d->precio_unitario,
                'subtotal'        => (float) $d->subtotal,
            ]),
        ]);
    }

    /* ── Helpers ───────────────────────────────────────────────────── */

    /**
     * FASE 3: Construye el array de sync para la pivot compra_venta_detalle.
     * Resuelve compra_linea_id desde el índice del formulario y congela
     * costo_unitario / costo_total. La validación de saldos se delega
     * a ConciliacionService::sincronizar().
     */
    private function buildSyncData(Request $request, array $lineasCreadas = []): array
    {
        $ids           = $request->input('detalles', []);
        $cantidades    = $request->input('detalles_cantidad', []);
        $detallesLinea = $request->input('detalles_linea', []);
        $sync          = [];

        foreach ($ids as $rawId) {
            $id       = (int) $rawId;
            $cantidad = (float) ($cantidades[$rawId] ?? 1);

            $lineaIdx = $detallesLinea[$rawId] ?? null;
            $lineaId  = ($lineaIdx !== null && $lineaIdx !== '' && isset($lineasCreadas[(int) $lineaIdx]))
                ? $lineasCreadas[(int) $lineaIdx]
                : null;

            $costoUnitario = null;
            $costoTotal    = null;
            if ($lineaId) {
                $linea = CompraLinea::find($lineaId);
                if ($linea) {
                    $costoUnitario = (float) $linea->monto_unitario;
                    $costoTotal    = round($costoUnitario * $cantidad, 4);
                }
            }

            $sync[$id] = [
                'cantidad'        => $cantidad,
                'compra_linea_id' => $lineaId,
                'costo_unitario'  => $costoUnitario,
                'costo_total'     => $costoTotal,
            ];
        }

        return $sync;
    }

    private function facturasDisponibles()
    {
        return Venta::with(['vendedor:id,nombre', 'detalles:id,venta_id,producto,cantidad,precio_unitario,subtotal'])
            ->select('id', 'vendedor_id', 'fecha', 'documento_tipo', 'documento_numero', 'total')
            ->whereIn('documento_tipo', ['factura', 'boleta', 'proforma'])
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->limit(300)
            ->get();
    }

    private function resolverProducto(?string $nombre, float $costoUnitario): ?Producto
    {
        if (empty(trim($nombre ?? ''))) {
            return null;
        }
        return Producto::firstOrCreate(
            ['nombre' => trim($nombre)],
            ['precio_costo' => $costoUnitario]
        );
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'empresa'                 => 'required|string|max:255',
            'documento_tipo'          => 'nullable|string|max:50',
            'documento_numero'        => 'nullable|string|max:100',
            'fecha'                   => 'required|date',
            'metodo_pago'             => 'nullable|string|in:efectivo,transferencia',
            'observaciones'           => 'nullable|string',
            'lineas'                  => 'required|array|min:1',
            'lineas.*.producto'       => 'nullable|string|max:255',
            'lineas.*.cantidad'       => 'required|numeric|min:0',
            'lineas.*.monto_unitario' => 'required|numeric|min:0',
            'lineas.*.monto_total'    => 'required|numeric|min:0',
        ]);
    }
}
