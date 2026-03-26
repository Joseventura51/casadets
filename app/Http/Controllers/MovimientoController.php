<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller; // 👈 ESTA LÍNEA FALTABA
use App\Models\Movimiento;
use Illuminate\Http\Request;

class MovimientoController extends Controller
{
    public function index()
    {
        $movimientos = Movimiento::latest()->get();
        return view('movimientos.index', compact('movimientos'));
    }

    public function create($tipo)
    {
        return view('movimientos.create', compact('tipo'));
    }

    public function store(Request $request)
    {
        Movimiento::create($request->all());
        return redirect('/movimientos')->with('success', 'Registro guardado');
    }
}