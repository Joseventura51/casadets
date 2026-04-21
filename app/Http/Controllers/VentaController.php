<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Models\Vendedor;
use Illuminate\Http\Request;

class VentaController extends Controller
{
    public function index(Request $request)
    {
        $query = Venta::with('vendedor')->orderBy('fecha', 'desc')->orderBy('id', 'desc');

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
            'producto' => 'required|string|max:255',
            'monto' => 'required|numeric|min:0.01',
            'metodo_pago' => 'required|in:efectivo,tarjeta,yape,plin,transferencia',
            'documento_tipo' => 'nullable|in:boleta,factura,proforma',
            'documento_numero' => 'nullable|string|max:255',
            'observaciones' => 'nullable|string',
            'fecha' => 'required|date',
        ]);

        Venta::create($data);

        return redirect('/casadets/ventas')->with('success', 'Venta registrada.');
    }

    public function destroy(Venta $venta)
    {
        $venta->delete();
        return redirect('/casadets/ventas')->with('success', 'Venta eliminada.');
    }
}
