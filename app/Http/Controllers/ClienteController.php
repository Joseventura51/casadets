<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Services\VendedorScope;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $authUser = auth()->user();

        $query = Cliente::withCount('ventas')->orderBy('nombre');

        // Restricción por vendedor o caja
        if ($authUser) {
            VendedorScope::aplicarClientes($query);
        }

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
        abort_if(!auth()->user()->puedeHacer('clientes.crear'), 403, 'Sin permiso para crear clientes.');
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
        abort_if(!auth()->user()->puedeHacer('clientes.editar'), 403, 'Sin permiso para editar clientes.');
        $data = $this->validar($request, $cliente);
        $cliente->update($data);
        return redirect('/casadets/clientes')->with('success', 'Cliente actualizado.');
    }

    public function destroy(Cliente $cliente)
    {
        abort_if(!auth()->user()->puedeHacer('clientes.eliminar'), 403, 'Sin permiso para eliminar clientes.');
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
