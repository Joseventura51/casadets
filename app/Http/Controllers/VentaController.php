<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Models\Vendedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VentaController extends Controller
{
    public function index(Request $request)
    {
        $query = Venta::with(['vendedor', 'detalles'])
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc');

        if ($request->filled('vendedor_id')) {
            $query->where('vendedor_id', $request->vendedor_id);
        }

        if ($request->filled('desde')) {
            $query->whereDate('fecha', '>=', $request->desde);
        }

        if ($request->filled('hasta')) {
            $query->whereDate('fecha', '<=', $request->hasta);
        }

        $ventas = $query->get();
        $vendedores = Vendedor::where('activo', true)->orderBy('nombre')->get();

        return view('casadets.ventas.index', compact('ventas', 'vendedores'));
    }

    public function show(Venta $venta)
    {
        $venta->load(['vendedor', 'detalles']);
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
            'documento_numero' => 'nullable|string|max:255',
            'observaciones' => 'nullable|string',
            'fecha' => 'required|date',
            'productos' => 'required|array|min:1',
            'productos.*.producto' => 'required|string|max:255',
            'productos.*.cantidad' => 'required|numeric|min:0.01',
            'productos.*.precio_unitario' => 'required|numeric|min:0',
        ], [
            'productos.required' => 'Agrega al menos un producto.',
            'productos.min' => 'Agrega al menos un producto.',
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

    public function destroy(Venta $venta)
    {
        $venta->delete();
        return redirect('/casadets/ventas')->with('success', 'Venta eliminada.');
    }
}
