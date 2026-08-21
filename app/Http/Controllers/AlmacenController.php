<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AlmacenController extends Controller
{
    public function index()
    {
        $almacenes = Almacen::withCount(['stocks', 'salidasOrigen', 'salidasDestino'])
            ->orderBy('activo', 'desc')
            ->orderBy('nombre')
            ->get();

        return view('admin.almacenes.index', compact('almacenes'));
    }

    public function create()
    {
        return view('admin.almacenes.form', ['almacen' => new Almacen()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:50', 'unique:almacenes,codigo'],
            'nombre' => ['required', 'string', 'max:150'],
            'empresa' => ['required', 'string', 'max:150'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);
        Almacen::create($data + ['activo' => true]);
        return redirect('/admin/almacenes')->with('success', 'Almacén creado.');
    }

    public function edit(Almacen $almacen)
    {
        return view('admin.almacenes.form', compact('almacen'));
    }

    public function update(Request $request, Almacen $almacen)
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:50', Rule::unique('almacenes', 'codigo')->ignore($almacen->id)],
            'nombre' => ['required', 'string', 'max:150'],
            'empresa' => ['required', 'string', 'max:150'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);
        $almacen->update($data);
        return redirect('/admin/almacenes')->with('success', 'Almacén actualizado.');
    }

    public function toggle(Almacen $almacen)
    {
        if ($almacen->activo && ($almacen->salidasOrigen()->exists() || $almacen->salidasDestino()->exists())) {
            return back()->with('error', 'No se puede desactivar un almacén que ya tiene salidas. Puede conservarlo inactivo para el historial.');
        }
        $almacen->update(['activo' => !$almacen->activo]);
        return back()->with('success', 'Estado del almacén actualizado.');
    }
}