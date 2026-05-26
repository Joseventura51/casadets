<?php

namespace App\Http\Controllers;

use App\Models\DetallePagoFactura;
use App\Models\Movimiento;
use App\Models\Producto;
use App\Models\StockMovimiento;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\Vendedor;
use App\Services\CobranzaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class VentaController extends Controller
{
    /* ─── Listado ──────────────────────────────────────────────── */

    public function index(Request $request)
    {
        $desde = $request->input('desde', today()->toDateString());
        $hasta = $request->input('hasta', $desde);
        if ($hasta < $desde) $hasta = $desde;

        $query = Venta::with([
                'vendedor:id,nombre',
                'cliente:id,nombre,documento',
                'detalles:id,venta_id,producto_id,producto,cantidad,precio_unitario,subtotal',
            ])
            ->select('id', 'vendedor_id', 'cliente_id', 'fecha', 'estado',
                     'total', 'metodo_pago',
                     'documento_tipo', 'documento_numero', 'observaciones');

        if (!$request->boolean('todas')) {
            $query->whereDate('fecha', '>=', $desde)
                  ->whereDate('fecha', '<=', $hasta);
        }

        $ventas = $query
            ->orderByRaw("CASE WHEN documento_tipo='factura'  THEN 0
                               WHEN documento_tipo='boleta'   THEN 1
                               WHEN documento_tipo='proforma' THEN 2
                               ELSE 3 END")
            ->orderByRaw('LENGTH(COALESCE(documento_numero,""))')
            ->orderBy('documento_numero')
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $vendedores = Vendedor::select('id', 'nombre')
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $todas = $request->boolean('todas');

        return view('casadets.ventas.index', compact('ventas', 'vendedores', 'desde', 'hasta', 'todas'));
    }

    /* ─── Detalle ──────────────────────────────────────────────── */

    public function show(Venta $venta)
    {
        $venta->load(['vendedor', 'cliente', 'detalles.compras', 'detalles.producto']);
        return view('casadets.ventas.show', compact('venta'));
    }

    /* ─── Crear ─────────────────────────────────────────────────── */

    public function create()
    {
        $vendedores = Vendedor::where('activo', true)->orderBy('nombre')->get();
        if ($vendedores->isEmpty()) {
            return redirect('/casadets/vendedores/create')
                ->with('error', 'Primero registra al menos un vendedor.');
        }
        $clientes = \App\Models\Cliente::where('activo', true)->orderBy('nombre')->get();
        return view('casadets.ventas.create', compact('vendedores', 'clientes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vendedor_id'      => 'required|exists:vendedores,id',
            'cliente_id'       => 'nullable|exists:clientes,id',
            'metodo_pago'      => 'nullable|string|max:100',
            'documento_tipo'   => 'nullable|in:boleta,factura,proforma',
            'documento_numero' => ['nullable', 'string', 'max:255',
                Rule::unique('ventas')->where(fn ($q) => $q->where('documento_tipo', $request->documento_tipo))],
            'observaciones'    => 'nullable|string',
            'fecha'            => 'required|date',
            'productos'        => 'required|array|min:1',
            'productos.*.producto'        => 'required|string|max:255',
            'productos.*.cantidad'        => 'required|numeric|min:0.01',
            'productos.*.precio_unitario' => 'required|numeric|min:0',
        ], ['documento_numero.unique' => 'Ya existe otra venta con ese número de documento del mismo tipo.']);

        DB::transaction(function () use ($data) {
            // Total exacto con bcmath
            $total = (float) collect($data['productos'])->reduce(
                fn ($carry, $p) => bcadd($carry, bcmul((string) $p['cantidad'], (string) $p['precio_unitario'], 4), 2),
                '0'
            );

            // Venta creada en estado pendiente — metodo_pago NULL hasta que CobranzaService lo asigne
            $venta = Venta::create([
                'vendedor_id'      => $data['vendedor_id'],
                'cliente_id'       => $data['cliente_id'] ?? null,
                'total'            => $total,
                'documento_tipo'   => $data['documento_tipo'] ?? null,
                'documento_numero' => $data['documento_numero'] ?? null,
                'observaciones'    => $data['observaciones'] ?? null,
                'fecha'            => $data['fecha'],
            ]);

            foreach ($data['productos'] as $p) {
                $producto = $this->resolverProducto($p['producto'], $p['precio_unitario']);

                $venta->detalles()->create([
                    'producto_id'     => $producto->id,
                    'producto'        => $p['producto'],
                    'cantidad'        => $p['cantidad'],
                    'precio_unitario' => $p['precio_unitario'],
                    'subtotal'        => (float) bcmul((string) $p['cantidad'], (string) $p['precio_unitario'], 2),
                ]);

                StockMovimiento::create([
                    'producto_id'     => $producto->id,
                    'tipo'            => 'salida',
                    'cantidad'        => $p['cantidad'],
                    'precio_unitario' => $p['precio_unitario'],
                    'referencia_tipo' => 'venta',
                    'referencia_id'   => $venta->id,
                    'fecha'           => $data['fecha'],
                ]);
            }

            $this->recalcularStockVenta($venta);

            // Auto-pago si se indicó método inmediato (no crédito)
            $metodo = $data['metodo_pago'] ?? null;
            if (!empty($metodo) && $metodo !== 'ninguno') {
                app(CobranzaService::class)->registrarPago($venta, [
                    ['metodo' => $metodo, 'monto' => $total],
                ]);
            }
        });

        return redirect('/casadets/ventas')->with('success', 'Venta registrada.');
    }

    /* ─── Editar ────────────────────────────────────────────────── */

    public function edit(Venta $venta)
    {
        $venta->load('detalles.producto');
        $vendedores = Vendedor::where('activo', true)->orderBy('nombre')->get();
        $clientes   = \App\Models\Cliente::where('activo', true)->orderBy('nombre')->get();
        return view('casadets.ventas.edit', compact('venta', 'vendedores', 'clientes'));
    }

    public function update(Request $request, Venta $venta)
    {
        $data = $request->validate([
            'vendedor_id'      => 'required|exists:vendedores,id',
            'cliente_id'       => 'nullable|exists:clientes,id',
            'documento_tipo'   => 'nullable|in:boleta,factura,proforma',
            'documento_numero' => ['nullable', 'string', 'max:255',
                Rule::unique('ventas')
                    ->where(fn($q) => $q->where('documento_tipo', $request->documento_tipo))
                    ->ignore($venta->id)],
            'fecha'            => 'required|date',
            'observaciones'    => 'nullable|string',
            'productos'        => 'required|array|min:1',
            'productos.*.id'             => 'nullable|integer',
            'productos.*.producto'       => 'required|string|max:255',
            'productos.*.cantidad'       => 'required|numeric|min:0.01',
            'productos.*.precio_unitario'=> 'required|numeric|min:0',
        ], ['documento_numero.unique' => 'Ya existe otra venta con ese número de documento del mismo tipo.']);

        DB::transaction(function () use ($data, $venta) {
            $nuevoTotal = (float) collect($data['productos'])->reduce(
                fn ($carry, $p) => bcadd($carry, bcmul((string) $p['cantidad'], (string) $p['precio_unitario'], 4), 2),
                '0'
            );

            $venta->update([
                'vendedor_id'      => $data['vendedor_id'],
                'cliente_id'       => $data['cliente_id'] ?? null,
                'documento_tipo'   => $data['documento_tipo'] ?? null,
                'documento_numero' => $data['documento_numero'] ?? null,
                'fecha'            => $data['fecha'],
                'observaciones'    => $data['observaciones'] ?? null,
                'total'            => $nuevoTotal,
            ]);

            // ── Upsert de detalles preservando IDs ────────────────────────
            $idsEnBD     = $venta->detalles()->pluck('id')->toArray();
            $idsEnviados = collect($data['productos'])
                ->pluck('id')->filter()->map(fn($id) => (int) $id)->toArray();

            $aEliminar = array_diff($idsEnBD, $idsEnviados);
            if (!empty($aEliminar)) {
                $venta->detalles()->whereIn('id', $aEliminar)->delete();
            }

            // Limpiar y recrear stock movimientos de esta venta
            StockMovimiento::where('referencia_tipo', 'venta')
                ->where('referencia_id', $venta->id)
                ->delete();

            foreach ($data['productos'] as $p) {
                $detId    = !empty($p['id']) ? (int) $p['id'] : null;
                $producto = $this->resolverProducto($p['producto'], $p['precio_unitario']);

                $detalleData = [
                    'producto_id'     => $producto->id,
                    'producto'        => $p['producto'],
                    'cantidad'        => $p['cantidad'],
                    'precio_unitario' => $p['precio_unitario'],
                    'subtotal'        => (float) bcmul((string) $p['cantidad'], (string) $p['precio_unitario'], 2),
                ];

                if ($detId && in_array($detId, $idsEnBD)) {
                    VentaDetalle::where('id', $detId)->update($detalleData);
                } else {
                    $venta->detalles()->create($detalleData);
                }

                StockMovimiento::create([
                    'producto_id'     => $producto->id,
                    'tipo'            => 'salida',
                    'cantidad'        => $p['cantidad'],
                    'precio_unitario' => $p['precio_unitario'],
                    'referencia_tipo' => 'venta',
                    'referencia_id'   => $venta->id,
                    'fecha'           => $data['fecha'],
                ]);
            }

            $this->recalcularStockVenta($venta);

            $venta->refresh();
            $venta->recalcularEstado();
        });

        return redirect('/casadets/ventas/' . $venta->id)->with('success', 'Venta actualizada.');
    }

    /* ─── Pendientes ────────────────────────────────────────────── */

    public function pendientes(Request $request)
    {
        $hasta = $request->input('hasta', today()->toDateString());
        $desde = $request->input('desde', $hasta);
        if ($hasta < $desde) $hasta = $desde;

        $query = Venta::with([
                'vendedor:id,nombre',
                'cliente:id,nombre,documento',
                'detalles:id,venta_id,producto,cantidad,precio_unitario,subtotal',
            ])
            ->select('id', 'vendedor_id', 'cliente_id', 'fecha', 'estado',
                     'total', 'pagado', 'metodo_pago', 'documento_tipo', 'documento_numero')
            ->whereIn('estado', ['pendiente', 'parcial'])
            ->whereDate('fecha', '<=', today());

        if ($request->filled('vendedor_id')) $query->where('vendedor_id', $request->vendedor_id);
        $query->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta);

        $ventas     = $query->orderBy('fecha', 'asc')->get();
        $vendedores = \App\Models\Vendedor::select('id', 'nombre')->orderBy('nombre')->get();

        return view('casadets.ventas.pendientes', compact('ventas', 'vendedores', 'desde', 'hasta'));
    }

    /* ─── Verificar pago ─────────────────────────────────────────── */

    public function pago(Venta $venta)
    {
        $venta->load([
            'vendedor:id,nombre',
            'detalles:id,venta_id,producto,cantidad,precio_unitario,subtotal',
            'cliente:id,nombre,documento',
        ]);
        $cobranza          = app(CobranzaService::class);
        $historial         = $cobranza->historialPagos($venta);
        $saldoFavor        = $venta->cliente_id ? $cobranza->saldoFavorDisponible($venta->cliente_id) : 0;
        $saldosDisponibles = $venta->cliente_id ? $cobranza->saldosDisponibles($venta->cliente_id) : collect();
        return view('casadets.ventas.verificar_pago', compact('venta', 'historial', 'saldoFavor', 'saldosDisponibles'));
    }

    public function updatePago(Request $request, Venta $venta)
    {
        $data = $request->validate([
            'pagos'          => 'required|array|min:1',
            'pagos.*.metodo' => 'required|in:ninguno,efectivo,tarjeta,yape,plin,transferencia',
            'pagos.*.monto'  => 'required|numeric|min:0',
            'estado_manual'  => 'nullable|in:pendiente,pagado,anulado',
        ]);

        $result = app(CobranzaService::class)->registrarPago(
            $venta,
            $data['pagos'],
            $data['estado_manual'] ?? null
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success'         => true,
                'venta_id'        => $venta->id,
                'estado'          => $result['estado'],
                'saldo_favor'     => $result['saldo_favor'],
                'saldo_pendiente' => $result['saldo_pendiente'],
                'msg_saldo_favor' => $result['saldo_favor'] > 0
                    ? 'Se generó un saldo a favor de S/ ' . number_format($result['saldo_favor'], 2)
                    : null,
            ]);
        }

        $msg = 'Pago registrado correctamente.';
        if ($result['saldo_favor'] > 0) {
            $msg .= ' Se generó un saldo a favor de S/ ' . number_format($result['saldo_favor'], 2) . '.';
        }
        return redirect('/casadets/ventas/' . $venta->id)->with('success', $msg);
    }

    /* ─── Cambio de estado (solo anulado) ───────────────────────── */

    /**
     * BUG #4 CORREGIDO: updateEstado SOLO permite marcar como 'anulado'.
     * Ningún estado financiero (pagado/parcial) puede setearse aquí.
     * Esos estados solo los gestiona CobranzaService.
     *
     * Si la venta tenía pagos registrados, sus movimientos se marcan
     * como 'anulado' en el ledger para mantener integridad financiera.
     */
    public function updateEstado(Request $request, Venta $venta)
    {
        $request->validate([
            'estado' => 'required|in:anulado',
        ]);

        if ($venta->estado === 'anulado') {
            return back()->with('info', 'La venta ya está anulada.');
        }

        DB::transaction(function () use ($venta) {
            // Si tenía cobros, anular sus movimientos en el ledger
            if (in_array($venta->estado, ['pagado', 'parcial'])) {
                $pagoIds = DetallePagoFactura::where('venta_id', $venta->id)
                    ->pluck('pago_id');

                if ($pagoIds->isNotEmpty()) {
                    Movimiento::where('referencia_tipo', 'pago')
                        ->whereIn('referencia_id', $pagoIds)
                        ->where('estado', 'activo')
                        ->each(function ($m) use ($venta) {
                            $m->update([
                                'estado'       => 'anulado',
                                'observaciones' => trim(($m->observaciones ?? '') . ' [Anulado: venta #' . $venta->id . ' cancelada]'),
                            ]);
                        });
                }
            }

            $venta->update(['estado' => 'anulado']);
        });

        return back()->with('success', 'Venta marcada como anulada. El ledger fue actualizado.');
    }

    /* ─── Eliminar (soft delete con reversa financiera) ─────────── */

    /**
     * FIX: Los movimientos del ledger NO se borran — son inmutables.
     * Se marcan como estado='anulado' para excluirlos del balance.
     * Stock se limpia para mantener inventario exacto.
     */
    public function destroy(Venta $venta)
    {
        DB::transaction(function () use ($venta) {
            // 1. Anular movimientos del ledger (NO borrar)
            $pagoIds = DetallePagoFactura::where('venta_id', $venta->id)
                ->pluck('pago_id');

            if ($pagoIds->isNotEmpty()) {
                Movimiento::where('referencia_tipo', 'pago')
                    ->whereIn('referencia_id', $pagoIds)
                    ->where('estado', 'activo')
                    ->each(function ($m) use ($venta) {
                        $m->update([
                            'estado'       => 'anulado',
                            'observaciones' => trim(($m->observaciones ?? '') . ' [Anulado: venta #' . $venta->id . ' eliminada]'),
                        ]);
                    });
            }

            // 2. Limpiar movimientos de stock (para exactitud de inventario)
            $productoIds = $venta->detalles()->pluck('producto_id')->filter()->unique();
            StockMovimiento::where('referencia_tipo', 'venta')
                ->where('referencia_id', $venta->id)
                ->delete();

            // 3. Recalcular stock de productos afectados
            foreach ($productoIds as $pid) {
                Producto::find($pid)?->recalcularStock();
            }

            // 4. Soft delete — historial financiero se preserva en pagos/movimientos
            $venta->delete();
        });

        return redirect('/casadets/ventas')->with('success', 'Venta eliminada. Los movimientos financieros fueron anulados en el ledger.');
    }

    /* ─── Exportar Excel ─────────────────────────────────────────── */

    public function export(Request $request)
    {
        $query = Venta::with(['vendedor', 'cliente', 'detalles']);

        if ($request->filled('vendedor_id')) $query->where('vendedor_id', $request->vendedor_id);
        if ($request->filled('tipo'))        $query->where('documento_tipo', $request->tipo);
        if ($request->filled('estado'))      $query->where('estado', $request->estado);
        if ($request->filled('desde'))       $query->whereDate('fecha', '>=', $request->desde);
        if ($request->filled('hasta'))       $query->whereDate('fecha', '<=', $request->hasta);
        if ($request->filled('cliente_id'))  $query->where('cliente_id', $request->cliente_id);

        $query->orderByRaw("CASE WHEN documento_tipo='factura' THEN 0
                                 WHEN documento_tipo='boleta'  THEN 1
                                 WHEN documento_tipo='proforma' THEN 2
                                 ELSE 3 END")
              ->orderByRaw('LENGTH(COALESCE(documento_numero,""))')
              ->orderBy('documento_numero')
              ->orderBy('fecha', 'desc');

        $ventas = $query->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ventas');

        $headers = ['Fecha', 'Documento', 'Nro. Doc.', 'Cliente', 'Vendedor', 'Productos', 'Método Pago', 'Total', 'Total Cobrado', 'Estado'];
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders'   => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1D4ED8']]],
        ];
        $sheet->getStyle('A1:J1')->applyFromArray($headerStyle);

        $row = 2;
        foreach ($ventas as $v) {
            $productos = $v->detalles->map(fn($d) => $d->producto . ' x' . rtrim(rtrim(number_format($d->cantidad, 2), '0'), '.'))->implode(', ');
            $sheet->setCellValue("A{$row}", $v->fecha->format('d/m/Y'));
            $sheet->setCellValue("B{$row}", ucfirst($v->documento_tipo ?? ''));
            $sheet->setCellValue("C{$row}", $v->documento_numero ?? '');
            $sheet->setCellValue("D{$row}", $v->cliente->nombre ?? '');
            $sheet->setCellValue("E{$row}", $v->vendedor->nombre ?? '');
            $sheet->setCellValue("F{$row}", $productos);
            $sheet->setCellValue("G{$row}", $v->metodo_pago ?? '');
            $sheet->setCellValue("H{$row}", (float) $v->total);
            $sheet->setCellValue("I{$row}", (float) $v->total_cobrado);
            $sheet->setCellValue("J{$row}", ucfirst($v->estado ?? 'pendiente'));

            $metodos   = array_map('trim', explode(',', strtolower($v->metodo_pago ?? '')));
            $esEfectivo = in_array('efectivo', $metodos);

            if (($v->estado ?? '') === 'anulado') {
                $sheet->getStyle("A{$row}:J{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEE2E2');
            } elseif ($esEfectivo) {
                $sheet->getStyle("A{$row}:J{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF08A');
            } elseif (($v->estado ?? '') === 'pagado') {
                $sheet->getStyle("A{$row}:J{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D1FAE5');
            }
            $row++;
        }

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getStyle('H2:I' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');

        $filename = 'ventas_' . now()->format('Y-m-d') . '.xlsx';
        $writer   = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'ventas_');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /* ─── Helpers privados ───────────────────────────────────────── */

    /**
     * Encuentra o crea un Producto por nombre.
     * Actualiza precio_venta si el nuevo es mayor.
     */
    private function resolverProducto(string $nombre, float $precioVenta): Producto
    {
        $producto = Producto::firstOrCreate(
            ['nombre' => trim($nombre)],
            ['precio_venta' => $precioVenta]
        );

        if ($precioVenta > (float) $producto->precio_venta) {
            $producto->update(['precio_venta' => $precioVenta]);
        }

        return $producto;
    }

    private function recalcularStockVenta(Venta $venta): void
    {
        $productoIds = $venta->detalles()->pluck('producto_id')->filter()->unique();
        foreach ($productoIds as $pid) {
            Producto::find($pid)?->recalcularStock();
        }
    }
}
