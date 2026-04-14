<?php

namespace App\Http\Controllers;

use App\Models\Movimiento;
use Illuminate\Http\Request;

class MovimientoController extends Controller
{
    // Muestra la lista de todos los movimientos
    public function index()
    {
        $movimientos = Movimiento::orderBy('fecha', 'desc')->get();
        return view('movimientos.index', compact('movimientos'));
    }

    // Muestra el formulario para crear un ingreso o salida
    // El {tipo} viene de la URL: /movimientos/create/ingreso
    public function create($tipo)
    {
        return view('movimientos.create', compact('tipo'));
    }

    // Guarda el movimiento en la base de datos
    public function store(Request $request)
    {
        $request->validate([
            'tipo' => 'required|in:ingreso,salida',
            'categoria' => 'required|string|max:255',
            'documento_tipo' => 'required|in:factura,proforma',
            'documento_numero' => 'required|string|max:255',
            'monto' => 'required|numeric|min:0.01',
            'fecha' => 'required|date',
            'observaciones' => 'nullable|string',
        ]);

        Movimiento::create($request->only([
            'tipo',
            'categoria',
            'documento_tipo',
            'documento_numero',
            'monto',
            'fecha',
            'observaciones',
        ]));

        return redirect('/movimientos')->with('success', 'Movimiento registrado');
    }
}