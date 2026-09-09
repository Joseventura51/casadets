<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AlmacenController extends Controller
{
    private const ALMACENES_ZENDY = [
        'PIMPON' => 'Pimpon',
        'CASA' => 'Casa',
        'IMPACTO' => 'Impacto',
        'ZAPALLAL' => 'Zapallal',
        'LUNA' => 'Luna',
        'PIZARRO' => 'Pizarro',
    ];

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
        $request->merge([
            'codigo' => strtoupper(trim((string) $request->input('codigo'))),
        ]);

        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:50', Rule::in(array_keys(self::ALMACENES_ZENDY)), 'unique:almacenes,codigo'],
            'empresa' => ['required', Rule::in(['zendy'])],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);

        Almacen::create([
            'codigo' => $data['codigo'],
            'nombre' => self::ALMACENES_ZENDY[$data['codigo']],
            'empresa' => 'zendy',
            'activo' => true,
            'observaciones' => $data['observaciones'] ?? null,
        ]);

        return redirect('/admin/almacenes')->with('success', 'Almacén creado.');
    }

    public function edit(Almacen $almacen)
    {
        return view('admin.almacenes.form', compact('almacen'));
    }

    public function update(Request $request, Almacen $almacen)
    {
        if (strtoupper($almacen->codigo) === 'PRINCIPAL') {
            return back()->with('error', 'El almacén PRINCIPAL es legado y no puede renombrarse ni modificarse.');
        }

        $request->merge([
            'codigo' => strtoupper(trim((string) $request->input('codigo'))),
        ]);

        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:50', Rule::in(array_keys(self::ALMACENES_ZENDY)), Rule::unique('almacenes', 'codigo')->ignore($almacen->id)],
            'empresa' => ['required', Rule::in(['zendy'])],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['codigo'] !== strtoupper($almacen->codigo)) {
            return back()->withInput()->withErrors([
                'codigo' => 'El código estable de un almacén no puede cambiarse.',
            ]);
        }

        $almacen->update([
            'nombre' => self::ALMACENES_ZENDY[$data['codigo']],
            'empresa' => 'zendy',
            'observaciones' => $data['observaciones'] ?? null,
        ]);

        return redirect('/admin/almacenes')->with('success', 'Almacén actualizado.');
    }

    public function toggle(Almacen $almacen)
    {
        if (strtoupper($almacen->codigo) === 'PRINCIPAL') {
            return back()->with('error', 'El almacén PRINCIPAL es legado y no puede modificarse.');
        }

        if ($almacen->activo && ($almacen->salidasOrigen()->exists() || $almacen->salidasDestino()->exists())) {
            return back()->with('error', 'No se puede desactivar un almacén que ya tiene salidas. Puede conservarlo inactivo para el historial.');
        }
        $almacen->update(['activo' => !$almacen->activo]);
        return back()->with('success', 'Estado del almacén actualizado.');
    }
}