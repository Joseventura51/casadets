<?php

namespace App\Http\Controllers;

use App\Models\AjustePrecioSupuesto;
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

        $soloSupuestos = $request->boolean('solo_supuestos');
        $sinReconciliar = $request->boolean('sin_reconciliar');

        $query = Compra::with([
                'lineas:id,compra_id,producto,cantidad,monto_total',
                'detalles:id,venta_id',
                'detalles.venta:id,documento_tipo,documento_numero',
                'ajusteSupuesto:id,compra_supuesta_id,compra_real_id,diferencia_total,aplicado',
            ])
            ->select('id', 'empresa', 'documento_tipo', 'documento_numero', 'fecha', 'metodo_pago', 'monto_total', 'observaciones', 'es_supuesto')
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc');

        if ($soloSupuestos) {
            $query->where('es_supuesto', true);
        }
        if ($sinReconciliar) {
            $query->where('es_supuesto', true)
                  ->whereDoesntHave('ajusteSupuesto', fn($q) => $q->whereNotNull('compra_real_id'));
        }

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

        return view('casadets.compras.index', compact('compras', 'desde', 'hasta', 'cajaAbierta', 'totalFiltrado', 'soloSupuestos', 'sinReconciliar'));
    }

    public function create()
    {
        $facturas = $this->facturasDisponibles();
        $compra   = new Compra();
        $detallesSeleccionados = [];
        $tiposGasto = Compra::TIPOS_GASTO;
        return view('casadets.compras.create', compact('facturas', 'compra', 'detallesSeleccionados', 'tiposGasto'));
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);

        DB::transaction(function () use ($data, $request) {
            $lineas = $data['lineas'] ?? [];
            $total  = collect($lineas)->sum('monto_total');

            $tipoGasto        = $request->input('tipo_gasto') ?: null;
            $ventaAsignadaId  = $request->filled('venta_asignada_id') ? (int) $request->venta_asignada_id : null;
            $esGastoOperativo = in_array($tipoGasto, array_keys(Compra::TIPOS_GASTO));

            $compra = Compra::create(array_merge(
                collect($data)->except('lineas')->toArray(),
                [
                    'monto_total'       => $total,
                    'caja_id'           => session('caja_id'),
                    'es_supuesto'       => $esGastoOperativo ? false : (bool) $request->boolean('es_supuesto'),
                    'tipo_gasto'        => $tipoGasto,
                    'venta_asignada_id' => $ventaAsignadaId,
                ]
            ));

            $lineasCreadas = [];
            foreach ($lineas as $idx => $l) {
                // Para gastos operativos (movilidad/pago_maestro) no hay movimiento de stock
                $producto = $esGastoOperativo
                    ? null
                    : $this->resolverProducto($l['producto'] ?? null, $l['monto_unitario']);

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
                'metodo_pago'      => $compra->metodo_pago,
                'documento_tipo'   => $compra->documento_tipo,
                'documento_numero' => $compra->documento_numero,
                'monto'            => $total,
                'fecha'            => $data['fecha'],
                'observaciones'    => $compra->empresa . $metodoLabel . ($compra->observaciones ? ' — ' . $compra->observaciones : ''),
            ]);

            // Para gastos operativos (movilidad/pago_maestro): no hay conciliación ni stock
            if ($esGastoOperativo) return;

            $syncData  = $this->buildSyncData($request, $lineasCreadas);

            $this->conciliacion->sincronizar($compra, $syncData, $compra->id);

            // Si es vale supuesto, crear registro de ajuste pendiente de reconciliar
            if ($compra->es_supuesto) {
                AjustePrecioSupuesto::create(['compra_supuesta_id' => $compra->id]);
            }
        });

        return redirect('/casadets/compras')->with('success', 'Compra registrada.');
    }

    public function show(Compra $compra)
    {
        $compra->load(['lineas.producto', 'detalles.venta.vendedor', 'auditorias.usuario', 'ajusteSupuesto.compraReal', 'ventaAsignada']);
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

        $tiposGasto = Compra::TIPOS_GASTO;
        return view('casadets.compras.edit', compact('compra', 'facturas', 'detallesSeleccionados', 'tiposGasto'));
    }

    public function update(Request $request, Compra $compra)
    {
        $data = $this->validar($request);

        DB::transaction(function () use ($data, $request, $compra) {
            $lineas = $data['lineas'] ?? [];
            $total  = collect($lineas)->sum('monto_total');
            $tipoGasto        = $request->input('tipo_gasto') ?: null;
            $ventaAsignadaId  = $request->filled('venta_asignada_id') ? (int) $request->venta_asignada_id : null;
            $esGastoOperativo = in_array($tipoGasto, array_keys(Compra::TIPOS_GASTO));
            $esSupuesto       = $esGastoOperativo ? false : (bool) $request->boolean('es_supuesto');

            $compra->update(array_merge(
                collect($data)->except('lineas')->toArray(),
                [
                    'monto_total'       => $total,
                    'es_supuesto'       => $esSupuesto,
                    'tipo_gasto'        => $tipoGasto,
                    'venta_asignada_id' => $ventaAsignadaId,
                ]
            ));

            $productoIdsAntes = $compra->lineas()->pluck('producto_id')->filter()->unique();

            StockMovimiento::where('referencia_tipo', 'compra')
                ->where('referencia_id', $compra->id)
                ->delete();

            $compra->lineas()->delete();

            $lineasCreadas = [];
            foreach ($lineas as $idx => $l) {
                $producto = $esGastoOperativo
                    ? null
                    : $this->resolverProducto($l['producto'] ?? null, $l['monto_unitario']);

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
                    'metodo_pago'      => $compra->metodo_pago,
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
                    'metodo_pago'      => $compra->metodo_pago,
                    'documento_tipo'   => $compra->documento_tipo,
                    'documento_numero' => $compra->documento_numero,
                    'monto'            => $total,
                    'fecha'            => $data['fecha'],
                    'observaciones'    => $compra->empresa . $metodoLabel . ($compra->observaciones ? ' — ' . $compra->observaciones : ''),
                ]);
            }

            // Para gastos operativos no hay conciliación
            if ($esGastoOperativo) return;

            $syncData  = $this->buildSyncData($request, $lineasCreadas);

            $this->conciliacion->sincronizar($compra, $syncData, $compra->id);

            // Gestionar registro de ajuste si cambió es_supuesto
            $tieneAjuste = $compra->ajusteSupuesto()->exists();
            if ($esSupuesto && !$tieneAjuste) {
                AjustePrecioSupuesto::create(['compra_supuesta_id' => $compra->id]);
            } elseif (!$esSupuesto && $tieneAjuste) {
                $compra->ajusteSupuesto()->whereNull('compra_real_id')->delete();
            }
        });

        return redirect('/casadets/compras/' . $compra->id)->with('success', 'Compra actualizada.');
    }

    public function reconciliarForm(Compra $compra)
    {
        abort_if(!$compra->es_supuesto, 404, 'Esta compra no es un vale supuesto.');
        $compra->load(['lineas', 'ajusteSupuesto.compraReal']);

        if ($compra->ajusteSupuesto?->compra_real_id) {
            return redirect('/casadets/compras/' . $compra->id)
                ->with('info', 'Este vale ya fue reconciliado con la compra real.');
        }

        return view('casadets.compras.reconciliar', compact('compra'));
    }

    public function reconciliar(Request $request, Compra $compra)
    {
        abort_if(!$compra->es_supuesto, 404, 'Esta compra no es un vale supuesto.');
        $compra->load(['lineas', 'ajusteSupuesto']);

        if ($compra->ajusteSupuesto?->compra_real_id) {
            return redirect('/casadets/compras/' . $compra->id)
                ->with('info', 'Este vale ya fue reconciliado.');
        }

        $request->validate([
            'empresa'            => 'required|string|max:255',
            'documento_tipo'     => 'nullable|string|max:50',
            'documento_numero'   => 'nullable|string|max:100',
            'fecha'              => 'required|date',
            'metodo_pago'        => 'nullable|string|in:efectivo,transferencia',
            'observaciones'      => 'nullable|string',
            'precios_reales'     => 'required|array',
            'precios_reales.*'   => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($request, $compra) {
            $totalReal = 0;
            $lineasProcesadas = [];

            foreach ($compra->lineas as $linea) {
                $precioReal   = (float) ($request->input("precios_reales.{$linea->id}") ?? $linea->monto_unitario);
                $montoTotal   = round($precioReal * (float) $linea->cantidad, 2);
                $totalReal   += $montoTotal;
                $lineasProcesadas[$linea->id] = [
                    'precio_real' => $precioReal,
                    'monto_total' => $montoTotal,
                ];
            }

            // Crear compra real (sin StockMovimiento — bienes ya están en inventario)
            $compraReal = Compra::create([
                'empresa'          => $request->empresa,
                'caja_id'          => session('caja_id'),
                'documento_tipo'   => $request->documento_tipo,
                'documento_numero' => $request->documento_numero,
                'fecha'            => $request->fecha,
                'monto_total'      => round($totalReal, 2),
                'metodo_pago'      => $request->metodo_pago,
                'observaciones'    => $request->observaciones,
                'es_supuesto'      => false,
            ]);

            foreach ($compra->lineas as $linea) {
                $compraReal->lineas()->create([
                    'producto_id'    => $linea->producto_id,
                    'producto'       => $linea->producto,
                    'cantidad'       => $linea->cantidad,
                    'monto_unitario' => $lineasProcesadas[$linea->id]['precio_real'],
                    'monto_total'    => $lineasProcesadas[$linea->id]['monto_total'],
                ]);

                // Actualizar precio_costo del producto al precio real
                if ($linea->producto_id) {
                    Producto::where('id', $linea->producto_id)
                        ->update(['precio_costo' => $lineasProcesadas[$linea->id]['precio_real']]);
                }
            }

            // Movimiento de salida (pago real al proveedor)
            $metodoLabel = $compraReal->metodo_pago ? ' [' . ucfirst($compraReal->metodo_pago) . ']' : '';
            Movimiento::create([
                'tipo'             => 'salida',
                'subtipo'          => 'compra',
                'origen'           => 'auto',
                'estado'           => 'activo',
                'empresa'          => 'casadets',
                'caja_id'          => session('caja_id'),
                'categoria'        => 'Reconciliación vale — ' . $compraReal->empresa,
                'referencia_tipo'  => 'compra',
                'referencia_id'    => $compraReal->id,
                'metodo_pago'      => $compraReal->metodo_pago,
                'documento_tipo'   => $compraReal->documento_tipo,
                'documento_numero' => $compraReal->documento_numero,
                'monto'            => round($totalReal, 2),
                'fecha'            => $request->fecha,
                'observaciones'    => 'Reconciliación de vale #' . $compra->id . $metodoLabel
                    . ($compraReal->observaciones ? ' — ' . $compraReal->observaciones : ''),
            ]);

            // Diferencia: positivo = real fue más caro; negativo = real fue más barato
            $diferencia = round($totalReal - (float) $compra->monto_total, 2);

            $ajuste = $compra->ajusteSupuesto
                ?? AjustePrecioSupuesto::firstOrNew(['compra_supuesta_id' => $compra->id]);
            $ajuste->compra_real_id   = $compraReal->id;
            $ajuste->diferencia_total = $diferencia;
            $ajuste->aplicado         = false;
            $ajuste->save();
        });

        return redirect('/casadets/compras/' . $compra->id)
            ->with('success', 'Vale reconciliado correctamente. La diferencia de precio quedará registrada para el próximo cierre de utilidad.');
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

    public function detallesVenta(Request $request, Venta $venta)
    {
        $excluirCompraId = (int) $request->input('excluir', 0);

        $venta->load(['detalles:id,venta_id,producto_id,producto,cantidad,precio_unitario,subtotal', 'vendedor:id,nombre']);
        return response()->json([
            'venta' => [
                'id'        => $venta->id,
                'fecha'     => $venta->fecha->format('d/m/Y'),
                'documento' => trim(ucfirst((string) $venta->documento_tipo) . ' ' . (string) $venta->documento_numero),
                'vendedor'  => $venta->vendedor->nombre ?? '—',
            ],
            'detalles' => $venta->detalles->map(function ($d) use ($excluirCompraId) {
                $cubierta = (float) DB::table('compra_venta_detalle')
                    ->where('venta_detalle_id', $d->id)
                    ->when($excluirCompraId > 0, fn($q) => $q->where('compra_id', '!=', $excluirCompraId))
                    ->sum('cantidad');

                if ($cubierta <= 0)                        $estado = 'sin_costear';
                elseif ($cubierta >= (float) $d->cantidad) $estado = 'costeada';
                else                                       $estado = 'parcial';

                return [
                    'id'               => $d->id,
                    'producto_id'      => $d->producto_id,
                    'producto'         => $d->producto,
                    'cantidad'         => (float) $d->cantidad,
                    'precio_unitario'  => (float) $d->precio_unitario,
                    'subtotal'         => (float) $d->subtotal,
                    'estado_costeo'    => $estado,
                    'cantidad_cubierta'=> (float) $cubierta,
                ];
            }),
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
            ->where('estado', '!=', 'anulado')
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
