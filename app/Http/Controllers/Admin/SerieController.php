<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\Serie;
use Illuminate\Http\Request;

class SerieController extends Controller
{
    public function index()
    {
        $series = Serie::with('caja')->orderBy('codigo')->get();
        return view('admin.series.index', compact('series'));
    }

    public function create()
    {
        $cajas = Caja::where('activa', true)->orderBy('codigo')->get();
        return view('admin.series.create', compact('cajas'));
    }

    public function store(Request $request)
    {
        $tipo = $request->input('tipo_documento');
        $data = $request->validate([
            'codigo'              => [
                'required', 'string', 'max:20',
                \Illuminate\Validation\Rule::unique('series')->where(
                    fn ($q) => $q->where('tipo_documento', $tipo)
                ),
            ],
            'tipo_documento'      => 'required|in:boleta,factura,proforma,nota_credito',
            'correlativo_actual'  => 'required|integer|min:0',
            'caja_id'             => 'nullable|exists:cajas,id',
            'activa'              => 'boolean',
        ], [
            'codigo.unique' => 'Ya existe una serie con ese código para el mismo tipo de documento.',
        ]);

        $serie = Serie::create([
            'codigo'             => strtoupper(trim($data['codigo'])),
            'tipo_documento'     => $data['tipo_documento'],
            'correlativo_actual' => $data['correlativo_actual'],
            'caja_id'            => $data['caja_id'] ?? null,
            'activa'             => $request->boolean('activa', true),
        ]);

        return redirect('/admin/series')->with('success', "Serie {$serie->codigo} creada.");
    }

    public function edit(Serie $serie)
    {
        $cajas = Caja::where('activa', true)->orderBy('codigo')->get();
        return view('admin.series.edit', compact('serie', 'cajas'));
    }

    public function update(Request $request, Serie $serie)
    {
        $tipo = $request->input('tipo_documento');
        $data = $request->validate([
            'codigo'             => [
                'required', 'string', 'max:20',
                \Illuminate\Validation\Rule::unique('series')
                    ->where(fn ($q) => $q->where('tipo_documento', $tipo))
                    ->ignore($serie->id),
            ],
            'tipo_documento'     => 'required|in:boleta,factura,proforma,nota_credito',
            'correlativo_actual' => 'required|integer|min:0',
            'caja_id'            => 'nullable|exists:cajas,id',
            'activa'             => 'boolean',
        ], [
            'codigo.unique' => 'Ya existe una serie con ese código para el mismo tipo de documento.',
        ]);

        $serie->update([
            'codigo'             => strtoupper(trim($data['codigo'])),
            'tipo_documento'     => $data['tipo_documento'],
            'correlativo_actual' => $data['correlativo_actual'],
            'caja_id'            => $data['caja_id'] ?? null,
            'activa'             => $request->boolean('activa'),
        ]);

        return redirect('/admin/series')->with('success', "Serie {$serie->codigo} actualizada.");
    }

    public function destroy(Serie $serie)
    {
        $serie->delete();
        return redirect('/admin/series')->with('success', "Serie {$serie->codigo} eliminada.");
    }

    /**
     * API: series asignadas a una caja (para ventas manuales)
     */
    public function porCaja(Caja $caja)
    {
        $series = Serie::where('caja_id', $caja->id)
            ->where('activa', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'tipo_documento', 'correlativo_actual']);

        return response()->json($series);
    }
}
