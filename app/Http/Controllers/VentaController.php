<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Models\Vendedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VentaController extends Controller
{
    /* ─── Listado ─────────────────────────────────────────── */

    public function index(Request $request)
    {
        $query = Venta::with(['vendedor', 'detalles']);

        if ($request->filled('vendedor_id')) $query->where('vendedor_id', $request->vendedor_id);
        if ($request->filled('tipo'))        $query->where('documento_tipo', $request->tipo);
        if ($request->filled('estado'))      $query->where('estado', $request->estado);
        if ($request->filled('desde'))       $query->whereDate('fecha', '>=', $request->desde);
        if ($request->filled('hasta'))       $query->whereDate('fecha', '<=', $request->hasta);

        $query->orderByRaw("CASE WHEN documento_tipo='factura' THEN 0
                                 WHEN documento_tipo='boleta'  THEN 1
                                 WHEN documento_tipo='proforma' THEN 2
                                 ELSE 3 END")
              ->orderByRaw('LENGTH(COALESCE(documento_numero,""))')
              ->orderBy('documento_numero')
              ->orderBy('fecha', 'desc')
              ->orderBy('id', 'desc');

        $ventas    = $query->get();
        $vendedores = Vendedor::where('activo', true)->orderBy('nombre')->get();

        return view('casadets.ventas.index', compact('ventas', 'vendedores'));
    }

    /* ─── Detalle ──────────────────────────────────────────── */

    public function show(Venta $venta)
    {
        $venta->load(['vendedor', 'detalles.compras.detalles', 'detalles.compras']);
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
        return view('casadets.ventas.create', compact('vendedores'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vendedor_id'      => 'required|exists:vendedores,id',
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
        return view('casadets.ventas.edit', compact('venta', 'vendedores'));
    }

    public function update(Request $request, Venta $venta)
    {
        $data = $request->validate([
            'vendedor_id'      => 'required|exists:vendedores,id',
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
            'pagos.*.metodo'  => 'required|in:efectivo,tarjeta,yape,plin,transferencia',
            'pagos.*.monto'   => 'required|numeric|min:0',
        ]);

        $metodosPago  = collect($data['pagos'])->pluck('metodo')->unique()->implode(',');
        $totalCobrado = round(collect($data['pagos'])->sum(fn($p) => (float) $p['monto']), 2);
        $ajuste       = round($totalCobrado - (float) $venta->total, 2);

        $venta->update([
            'metodo_pago' => $metodosPago,
            'ajuste'      => $ajuste,
        ]);

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
}
