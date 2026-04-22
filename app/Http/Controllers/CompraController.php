<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompraController extends Controller
{
    public function index(Request $request)
    {
        $query = Compra::with('ventas')->orderBy('fecha', 'desc')->orderBy('id', 'desc');

        if ($request->filled('empresa')) {
            $query->where('empresa', 'like', '%' . $request->empresa . '%');
        }
        if ($request->filled('desde')) {
            $query->whereDate('fecha', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $query->whereDate('fecha', '<=', $request->hasta);
        }

        $compras = $query->get();
        return view('casadets.compras.index', compact('compras'));
    }

    public function create()
    {
        $ventas = Venta::with('vendedor')->orderBy('fecha', 'desc')->orderBy('id', 'desc')->limit(200)->get();
        return view('casadets.compras.create', compact('ventas'));
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);
        DB::transaction(function () use ($data, $request) {
            $compra = Compra::create($data);
            if ($request->filled('ventas')) {
                $compra->ventas()->sync($request->input('ventas', []));
            }
        });
        return redirect('/casadets/compras')->with('success', 'Compra registrada.');
    }

    public function show(Compra $compra)
    {
        $compra->load(['ventas.vendedor']);
        return view('casadets.compras.show', compact('compra'));
    }

    public function edit(Compra $compra)
    {
        $compra->load('ventas');
        $ventas = Venta::with('vendedor')->orderBy('fecha', 'desc')->orderBy('id', 'desc')->limit(200)->get();
        $vinculadas = $compra->ventas->pluck('id')->toArray();
        return view('casadets.compras.edit', compact('compra', 'ventas', 'vinculadas'));
    }

    public function update(Request $request, Compra $compra)
    {
        $data = $this->validar($request);
        DB::transaction(function () use ($data, $request, $compra) {
            $compra->update($data);
            $compra->ventas()->sync($request->input('ventas', []));
        });
        return redirect('/casadets/compras')->with('success', 'Compra actualizada.');
    }

    public function destroy(Compra $compra)
    {
        $compra->delete();
        return redirect('/casadets/compras')->with('success', 'Compra eliminada.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'empresa' => 'required|string|max:255',
            'documento_tipo' => 'nullable|string|max:50',
            'documento_numero' => 'nullable|string|max:100',
            'fecha' => 'required|date',
            'producto' => 'nullable|string|max:255',
            'cantidad' => 'required|numeric|min:0',
            'monto_unitario' => 'required|numeric|min:0',
            'monto_total' => 'required|numeric|min:0',
            'observaciones' => 'nullable|string',
        ]);
    }
}
