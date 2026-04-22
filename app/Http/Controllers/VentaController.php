<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Models\Vendedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VentaController extends Controller
{
    public function index(Request $request)
    {
        $query = Venta::with(['vendedor', 'detalles']);

        if ($request->filled('vendedor_id')) {
            $query->where('vendedor_id', $request->vendedor_id);
        }
        if ($request->filled('tipo')) {
            $query->where('documento_tipo', $request->tipo);
        }
        if ($request->filled('desde')) {
            $query->whereDate('fecha', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $query->whereDate('fecha', '<=', $request->hasta);
        }

        // Orden: facturas primero por correlativo, luego boletas/proformas, después sin doc
        $query->orderByRaw("CASE WHEN documento_tipo = 'factura' THEN 0
                                 WHEN documento_tipo = 'boleta'  THEN 1
                                 WHEN documento_tipo = 'proforma' THEN 2
                                 ELSE 3 END")
              ->orderByRaw('LENGTH(COALESCE(documento_numero, ""))')
              ->orderBy('documento_numero')
              ->orderBy('fecha', 'desc')
              ->orderBy('id', 'desc');

        $ventas = $query->get();
        $vendedores = Vendedor::where('activo', true)->orderBy('nombre')->get();

        return view('casadets.ventas.index', compact('ventas', 'vendedores'));
    }

    public function show(Venta $venta)
    {
        $venta->load(['vendedor', 'detalles', 'compras']);
        return view('casadets.ventas.show', compact('venta'));
    }

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
            'vendedor_id' => 'required|exists:vendedores,id',
            'metodo_pago' => 'required|in:efectivo,tarjeta,yape,plin,transferencia',
            'documento_tipo' => 'nullable|in:boleta,factura,proforma',
            'documento_numero' => [
                'nullable', 'string', 'max:255',
                Rule::unique('ventas')->where(fn($q) => $q->where('documento_tipo', $request->documento_tipo))
                    ->ignore(null),
            ],
            'observaciones' => 'nullable|string',
            'fecha' => 'required|date',
            'productos' => 'required|array|min:1',
            'productos.*.producto' => 'required|string|max:255',
            'productos.*.cantidad' => 'required|numeric|min:0.01',
            'productos.*.precio_unitario' => 'required|numeric|min:0',
        ], [
            'productos.required' => 'Agrega al menos un producto.',
            'productos.min' => 'Agrega al menos un producto.',
            'documento_numero.unique' => 'Ya existe otra venta con ese número de documento del mismo tipo.',
        ]);

        DB::transaction(function () use ($data) {
            $total = 0;
            foreach ($data['productos'] as $p) {
                $total += $p['cantidad'] * $p['precio_unitario'];
            }

            $venta = Venta::create([
                'vendedor_id' => $data['vendedor_id'],
                'total' => $total,
                'metodo_pago' => $data['metodo_pago'],
                'documento_tipo' => $data['documento_tipo'] ?? null,
                'documento_numero' => $data['documento_numero'] ?? null,
                'observaciones' => $data['observaciones'] ?? null,
                'fecha' => $data['fecha'],
            ]);

            foreach ($data['productos'] as $p) {
                $venta->detalles()->create([
                    'producto' => $p['producto'],
                    'cantidad' => $p['cantidad'],
                    'precio_unitario' => $p['precio_unitario'],
                    'subtotal' => $p['cantidad'] * $p['precio_unitario'],
                ]);
            }
        });

        return redirect('/casadets/ventas')->with('success', 'Venta registrada.');
    }

    public function edit(Venta $venta)
    {
        $vendedores = Vendedor::where('activo', true)->orderBy('nombre')->get();
        return view('casadets.ventas.edit', compact('venta', 'vendedores'));
    }

    public function update(Request $request, Venta $venta)
    {
        $data = $request->validate([
            'vendedor_id' => 'required|exists:vendedores,id',
            'metodo_pago' => 'required|in:efectivo,tarjeta,yape,plin,transferencia',
            'documento_tipo' => 'nullable|in:boleta,factura,proforma',
            'documento_numero' => [
                'nullable', 'string', 'max:255',
                Rule::unique('ventas')
                    ->where(fn($q) => $q->where('documento_tipo', $request->documento_tipo))
                    ->ignore($venta->id),
            ],
            'fecha' => 'required|date',
            'total_cobrado' => 'required|numeric|min:0',
            'observaciones' => 'nullable|string',
        ], [
            'documento_numero.unique' => 'Ya existe otra venta con ese número de documento del mismo tipo.',
        ]);

        $totalReal = (float) $venta->total;
        $venta->update([
            'vendedor_id' => $data['vendedor_id'],
            'metodo_pago' => $data['metodo_pago'],
            'documento_tipo' => $data['documento_tipo'] ?? null,
            'documento_numero' => $data['documento_numero'] ?? null,
            'fecha' => $data['fecha'],
            'ajuste' => round($data['total_cobrado'] - $totalReal, 2),
            'observaciones' => $data['observaciones'] ?? null,
        ]);

        return redirect('/casadets/ventas/' . $venta->id)->with('success', 'Venta actualizada.');
    }

    public function destroy(Venta $venta)
    {
        $venta->delete();
        return redirect('/casadets/ventas')->with('success', 'Venta eliminada.');
    }
}
