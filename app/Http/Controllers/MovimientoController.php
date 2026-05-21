<?php

namespace App\Http\Controllers;

use App\Models\Movimiento;
use Illuminate\Http\Request;

class MovimientoController extends Controller
{
    public function index(Request $request)
    {
        $query = Movimiento::select(
            'id', 'tipo', 'categoria', 'documento_tipo',
            'documento_numero', 'monto', 'fecha', 'observaciones'
        )->orderBy('fecha', 'desc')->orderBy('id', 'desc');

        // Filtros server-side (aprovechan los índices en tipo y fecha)
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        if ($request->filled('desde')) {
            $query->whereDate('fecha', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $query->whereDate('fecha', '<=', $request->hasta);
        }

        $movimientos = $query->paginate(50)->withQueryString();

        return view('movimientos.index', compact('movimientos'));
    }

    public function create($tipo)
    {
        return view('movimientos.create', compact('tipo'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'tipo'             => 'required|in:ingreso,salida',
            'categoria'        => 'required|string|max:255',
            'documento_tipo'   => 'required|in:factura,proforma',
            'documento_numero' => 'required|string|max:255',
            'monto'            => 'required|numeric|min:0.01',
            'fecha'            => 'required|date',
            'observaciones'    => 'nullable|string',
        ]);

        Movimiento::create($request->only([
            'tipo', 'categoria', 'documento_tipo',
            'documento_numero', 'monto', 'fecha', 'observaciones',
        ]));

        return redirect('/movimientos')->with('success', 'Movimiento registrado');
    }
}
