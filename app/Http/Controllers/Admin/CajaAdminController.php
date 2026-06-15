<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use Illuminate\Http\Request;

class CajaAdminController extends Controller
{
    public function index()
    {
        $cajas = Caja::withCount(['series', 'sesiones'])->orderBy('empresa')->orderBy('codigo')->get();
        return view('admin.cajas.index', compact('cajas'));
    }

    public function create()
    {
        return view('admin.cajas.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'codigo'  => 'required|string|max:20|unique:cajas,codigo',
            'nombre'  => 'required|string|max:100',
            'empresa' => 'required|in:casadets,zendy',
            'activa'  => 'boolean',
        ]);

        $caja = Caja::create([
            'codigo'  => strtoupper(trim($data['codigo'])),
            'nombre'  => $data['nombre'],
            'empresa' => $data['empresa'],
            'activa'  => $request->boolean('activa', true),
        ]);

        return redirect('/admin/cajas')->with('success', "Caja {$caja->codigo} — {$caja->nombre} creada.");
    }

    public function edit(Caja $caja)
    {
        $caja->load('series');
        return view('admin.cajas.edit', compact('caja'));
    }

    public function update(Request $request, Caja $caja)
    {
        $data = $request->validate([
            'codigo'  => 'required|string|max:20|unique:cajas,codigo,' . $caja->id,
            'nombre'  => 'required|string|max:100',
            'empresa' => 'required|in:casadets,zendy',
            'activa'  => 'boolean',
        ]);

        $caja->update([
            'codigo'  => strtoupper(trim($data['codigo'])),
            'nombre'  => $data['nombre'],
            'empresa' => $data['empresa'],
            'activa'  => $request->boolean('activa'),
        ]);

        return redirect('/admin/cajas')->with('success', "Caja {$caja->codigo} actualizada.");
    }

    public function toggleActiva(Caja $caja)
    {
        $caja->update(['activa' => !$caja->activa]);
        $msg = $caja->activa ? 'activada' : 'desactivada';
        return back()->with('success', "Caja {$caja->codigo} {$msg}.");
    }
}
