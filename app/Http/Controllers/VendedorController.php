<?php

namespace App\Http\Controllers;

use App\Models\Vendedor;
use Illuminate\Http\Request;

class VendedorController extends Controller
{
    public function index()
    {
        $vendedores = Vendedor::orderBy('nombre')->get();
        return view('casadets.vendedores.index', compact('vendedores'));
    }

    public function create()
    {
        return view('casadets.vendedores.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:50',
            'activo' => 'nullable|boolean',
        ]);

        $data['activo'] = $request->has('activo');

        Vendedor::create($data);

        return redirect('/casadets/vendedores')->with('success', 'Vendedor registrado.');
    }

    public function edit(Vendedor $vendedor)
    {
        return view('casadets.vendedores.edit', compact('vendedor'));
    }

    public function update(Request $request, Vendedor $vendedor)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:50',
            'activo' => 'nullable|boolean',
        ]);

        $data['activo'] = $request->has('activo');

        $vendedor->update($data);

        return redirect('/casadets/vendedores')->with('success', 'Vendedor actualizado.');
    }

    public function destroy(Vendedor $vendedor)
    {
        $vendedor->delete();
        return redirect('/casadets/vendedores')->with('success', 'Vendedor eliminado.');
    }
}
