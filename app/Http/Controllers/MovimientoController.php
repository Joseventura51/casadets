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
        // Valida que los campos estén completos
        $request->validate([
            'tipo'        => 'required',
            'descripcion' => 'required',
            'monto'       => 'required|numeric',
            'fecha'       => 'required|date',
        ]);

        // Crea el registro en la tabla movimientos
        Movimiento::create($request->all());

        // Redirige según si fue ingreso o salida
        return redirect('/movimientos')->with('success', 'Movimiento registrado');
    }
}