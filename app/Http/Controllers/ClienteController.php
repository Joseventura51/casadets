<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $query = Cliente::withCount('ventas')
            ->orderBy('nombre');

        if ($request->filled('buscar')) {
            $term = '%' . $request->buscar . '%';
            $query->where(function ($q) use ($term) {
                $q->where('nombre', 'like', $term)
                  ->orWhere('documento', 'like', $term);
            });
        }

        $clientes = $query->paginate(50)->withQueryString();

        return view('casadets.clientes.index', compact('clientes'));
    }

    public function create()
    {
        return view('casadets.clientes.create');
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);
        Cliente::create($data);
        return redirect('/casadets/clientes')->with('success', 'Cliente registrado.');
    }

    public function edit(Cliente $cliente)
    {
        return view('casadets.clientes.edit', compact('cliente'));
    }

    public function update(Request $request, Cliente $cliente)
    {
        $data = $this->validar($request, $cliente);
        $cliente->update($data);
        return redirect('/casadets/clientes')->with('success', 'Cliente actualizado.');
    }

    public function destroy(Cliente $cliente)
    {
        $cliente->delete();
        return redirect('/casadets/clientes')->with('success', 'Cliente eliminado.');
    }

    private function validar(Request $request, ?Cliente $cliente = null): array
    {
        return $request->validate([
            'nombre'    => 'required|string|max:255',
            'documento' => 'nullable|string|max:20',
            'telefono'  => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
            'activo'    => 'boolean',
        ]);
    }
}
