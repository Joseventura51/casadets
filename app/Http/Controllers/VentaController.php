<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Models\Vendedor;
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
    /* ─── Listado ─────────────────────────────────────────── */

    public function index()
    {
        $ventas = Venta::with(['vendedor', 'cliente', 'detalles'])
            ->orderByRaw("CASE WHEN documento_tipo='factura' THEN 0
                               WHEN documento_tipo='boleta'  THEN 1
                               WHEN documento_tipo='proforma' THEN 2
                               ELSE 3 END")
            ->orderByRaw('LENGTH(COALESCE(documento_numero,""))')
            ->orderBy('documento_numero')
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $vendedores = Vendedor::where('activo', true)->orderBy('nombre')->get();

        return view('casadets.ventas.index', compact('ventas', 'vendedores'));
    }

    /* ─── Detalle ──────────────────────────────────────────── */

    public function show(Venta $venta)
    {
        $venta->load(['vendedor', 'cliente', 'detalles.compras']);
        return view('casadets.ventas.show', compact('venta'));
    }

    /* ─── Crear ────────────────────────────────────────────── */

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
            'metodo_pago'      => 'required|string|max:100',
            'documento_tipo'   => 'nullable|in:boleta,factura,proforma',
            'documento_numero' => ['nullable','string','max:255',
                Rule::unique('ventas')->where(fn($q) => $q->where('documento_tipo', $request->documento_tipo))],
            'observaciones'    => 'nullable|string',
            'fecha'            => 'required|date',
            'productos'        => 'required|array|min:1',
            'productos.*.producto'       => 'required|string|max:255',
            'productos.*.cantidad'       => 'required|numeric|min:0.01',
            'productos.*.precio_unitario'=> 'required|numeric|min:0',
        ], ['documento_numero.unique' => 'Ya existe otra venta con ese número de documento del mismo tipo.']);

        DB::transaction(function () use ($data) {
            $total = collect($data['productos'])->sum(fn($p) => $p['cantidad'] * $p['precio_unitario']);
            $venta = Venta::create([
                'vendedor_id'      => $data['vendedor_id'],
                'cliente_id'       => $data['cliente_id'] ?? null,
                'total'            => round($total, 2),
                'metodo_pago'      => $data['metodo_pago'],
                'documento_tipo'   => $data['documento_tipo'] ?? null,
                'documento_numero' => $data['documento_numero'] ?? null,
                'observaciones'    => $data['observaciones'] ?? null,
                'fecha'            => $data['fecha'],
            ]);
            foreach ($data['productos'] as $p) {
                $venta->detalles()->create([
                    'producto'       => $p['producto'],
                    'cantidad'       => $p['cantidad'],
                    'precio_unitario'=> $p['precio_unitario'],
                    'subtotal'       => round($p['cantidad'] * $p['precio_unitario'], 2),
                ]);
            }
        });

        return redirect('/casadets/ventas')->with('success', 'Venta registrada.');
    }

    /* ─── Editar (meta + productos) ────────────────────────── */

    public function edit(Venta $venta)
    {
        $venta->load('detalles');
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
            'documento_numero' => ['nullable','string','max:255',
                Rule::unique('ventas')
                    ->where(fn($q) => $q->where('documento_tipo', $request->documento_tipo))
                    ->ignore($venta->id)],
            'fecha'            => 'required|date',
            'observaciones'    => 'nullable|string',
            'estado'           => 'nullable|in:pendiente,pagado,anulado',
            'productos'        => 'required|array|min:1',
            'productos.*.producto'       => 'required|string|max:255',
            'productos.*.cantidad'       => 'required|numeric|min:0.01',
            'productos.*.precio_unitario'=> 'required|numeric|min:0',
        ], ['documento_numero.unique' => 'Ya existe otra venta con ese número de documento del mismo tipo.']);

        DB::transaction(function () use ($data, $venta) {
            // Recalculate total from edited products
            $nuevoTotal = round(collect($data['productos'])
                ->sum(fn($p) => $p['cantidad'] * $p['precio_unitario']), 2);

            // Keep existing total_cobrado, adjust ajuste accordingly
            $totalCobradoActual = (float) $venta->total_cobrado;
            $nuevoAjuste = round($totalCobradoActual - $nuevoTotal, 2);

            $venta->update([
                'vendedor_id'      => $data['vendedor_id'],
                'cliente_id'       => $data['cliente_id'] ?? null,
                'documento_tipo'   => $data['documento_tipo'] ?? null,
                'documento_numero' => $data['documento_numero'] ?? null,
                'fecha'            => $data['fecha'],
                'observaciones'    => $data['observaciones'] ?? null,
                'estado'           => $data['estado'] ?? $venta->estado ?? 'pendiente',
                'total'            => $nuevoTotal,
                'ajuste'           => $nuevoAjuste,
            ]);

            // Rebuild detalles
            $venta->detalles()->delete();
            foreach ($data['productos'] as $p) {
                $venta->detalles()->create([
                    'producto'       => $p['producto'],
                    'cantidad'       => $p['cantidad'],
                    'precio_unitario'=> $p['precio_unitario'],
                    'subtotal'       => round($p['cantidad'] * $p['precio_unitario'], 2),
                ]);
            }
        });

        return redirect('/casadets/ventas/' . $venta->id)->with('success', 'Venta actualizada.');
    }

    /* ─── Verificar pago ───────────────────────────────────── */

    public function pago(Venta $venta)
    {
        $venta->load(['vendedor', 'detalles']);
        return view('casadets.ventas.verificar_pago', compact('venta'));
    }

    public function updatePago(Request $request, Venta $venta)
    {
        $data = $request->validate([
            'pagos'           => 'required|array|min:1',
            'pagos.*.metodo'  => 'required|in:ninguno,efectivo,tarjeta,yape,plin,transferencia',
            'pagos.*.monto'   => 'required|numeric|min:0',
            'estado_manual'   => 'nullable|in:pendiente,pagado,anulado',
        ]);

        $pagosReales  = collect($data['pagos'])->filter(fn($p) => $p['metodo'] !== 'ninguno');
        $metodosPago  = $pagosReales->pluck('metodo')->unique()->implode(',') ?: null;
        $totalCobrado = round($pagosReales->sum(fn($p) => (float) $p['monto']), 2);
        $ajuste       = $metodosPago ? round($totalCobrado - (float) $venta->total, 2) : 0;

        // Estado: si el usuario lo eligió manualmente lo respetamos;
        // si no, se calcula automáticamente según si el monto cubre el total.
        if (!empty($data['estado_manual'])) {
            $estado = $data['estado_manual'];
        } elseif (!$metodosPago) {
            $estado = 'pendiente';
        } elseif ($totalCobrado >= (float) $venta->total - 0.005) {
            $estado = 'pagado';
        } else {
            $estado = 'pendiente';
        }

        $venta->update([
            'metodo_pago' => $metodosPago,
            'ajuste'      => $ajuste,
            'estado'      => $estado,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'venta_id' => $venta->id, 'estado' => $estado]);
        }

        return redirect('/casadets/ventas/' . $venta->id)->with('success', 'Pago verificado correctamente.');
    }

    /* ─── Estado rápido ────────────────────────────────────── */

    public function updateEstado(Request $request, Venta $venta)
    {
        $data = $request->validate([
            'estado' => 'required|in:pendiente,pagado,anulado',
        ]);
        $venta->update(['estado' => $data['estado']]);
        return back();
    }

    /* ─── Eliminar ─────────────────────────────────────────── */

    public function destroy(Venta $venta)
    {
        $venta->delete();
        return redirect('/casadets/ventas')->with('success', 'Venta eliminada.');
    }

    /* ─── Exportar Excel ───────────────────────────────────── */

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

        $headers = ['Fecha', 'Documento', 'Nro. Doc.', 'Cliente', 'Vendedor', 'Productos', 'Método Pago', 'Total', 'Ajuste', 'Total Cobrado', 'Estado'];
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1D4ED8']]],
        ];
        $sheet->getStyle('A1:K1')->applyFromArray($headerStyle);

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
            $sheet->setCellValue("I{$row}", (float) $v->ajuste);
            $sheet->setCellValue("J{$row}", (float) $v->total_cobrado);
            $sheet->setCellValue("K{$row}", ucfirst($v->estado ?? 'pendiente'));

            if (($v->estado ?? '') === 'pagado') {
                $sheet->getStyle("A{$row}:K{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D1FAE5');
            } elseif (($v->estado ?? '') === 'anulado') {
                $sheet->getStyle("A{$row}:K{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEE2E2');
            }

            $row++;
        }

        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $sheet->getStyle('H2:J' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');

        $filename = 'ventas_' . now()->format('Y-m-d') . '.xlsx';

        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'ventas_');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
