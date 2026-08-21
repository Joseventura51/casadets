<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\Serie;
use App\Models\Almacen;
use Illuminate\Http\Request;

class SerieController extends Controller
{
    public function index(Request $request)
    {
        $busqueda = trim((string) $request->input('buscar', ''));
        $tipoDocumento = $request->input('tipo_documento');
        $cajaId = $request->input('caja_id');
        $estado = $request->input('estado');

        $query = Serie::with('caja')->orderBy('codigo');

        if ($busqueda !== '') {
            $termino = mb_strtolower($busqueda, 'UTF-8');
            $query->where(function ($q) use ($termino) {
                $q->whereRaw('LOWER(series.codigo) LIKE ?', ["%{$termino}%"])
                    ->orWhereHas('caja', function ($cajaQuery) use ($termino) {
                        $cajaQuery->whereRaw('LOWER(codigo) LIKE ?', ["%{$termino}%"])
                            ->orWhereRaw('LOWER(nombre) LIKE ?', ["%{$termino}%"]);
                    });
            });
        }

        if (in_array($tipoDocumento, ['boleta', 'factura', 'proforma', 'nota_credito', 'salida_almacen'], true)) {
            $query->where('tipo_documento', $tipoDocumento);
        }

        if ($cajaId !== null && $cajaId !== '') {
            $query->where('caja_id', $cajaId);
        }

        if ($estado === 'activa' || $estado === 'inactiva') {
            $query->where('activa', $estado === 'activa');
        }

        $series = $query->get();
        $cajas = Caja::orderBy('codigo')->get();

        return view('admin.series.index', compact(
            'series',
            'cajas',
            'busqueda',
            'tipoDocumento',
            'cajaId',
            'estado'
        ));
    }

    public function create()
    {
        $cajas = Caja::where('activa', true)->orderBy('codigo')->get();
        $almacenes = Almacen::where('activo', true)->orderBy('nombre')->get();
        return view('admin.series.create', compact('cajas', 'almacenes'));
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
            'tipo_documento'      => 'required|in:boleta,factura,proforma,nota_credito,salida_almacen',
            'correlativo_actual'  => 'required|integer|min:0',
            'caja_id'             => 'nullable|exists:cajas,id',
            'almacen_id'          => 'nullable|exists:almacenes,id',
            'activa'              => 'boolean',
        ], [
            'codigo.unique' => 'Ya existe una serie con ese código para el mismo tipo de documento.',
        ]);
        if ($data['tipo_documento'] === 'salida_almacen' && empty($data['almacen_id'])) {
            return back()->withInput()->withErrors(['almacen_id' => 'Las series de salida de almacén deben tener un almacén asignado.']);
        }

        $serie = Serie::create([
            'codigo'             => strtoupper(trim($data['codigo'])),
            'tipo_documento'     => $data['tipo_documento'],
            'correlativo_actual' => $data['correlativo_actual'],
            'caja_id'            => $data['caja_id'] ?? null,
            'almacen_id'         => $data['almacen_id'] ?? null,
            'activa'             => $request->boolean('activa', true),
        ]);

        return redirect('/admin/series')->with('success', "Serie {$serie->codigo} creada.");
    }

    public function edit(Serie $serie)
    {
        $cajas = Caja::where('activa', true)->orderBy('codigo')->get();
        $almacenes = Almacen::where('activo', true)->orderBy('nombre')->get();
        return view('admin.series.edit', compact('serie', 'cajas', 'almacenes'));
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
            'tipo_documento'     => 'required|in:boleta,factura,proforma,nota_credito,salida_almacen',
            'correlativo_actual' => 'required|integer|min:0',
            'caja_id'            => 'nullable|exists:cajas,id',
            'almacen_id'         => 'nullable|exists:almacenes,id',
            'activa'             => 'boolean',
        ], [
            'codigo.unique' => 'Ya existe una serie con ese código para el mismo tipo de documento.',
        ]);
        if ($data['tipo_documento'] === 'salida_almacen' && empty($data['almacen_id'])) {
            return back()->withInput()->withErrors(['almacen_id' => 'Las series de salida de almacén deben tener un almacén asignado.']);
        }

        $serie->update([
            'codigo'             => strtoupper(trim($data['codigo'])),
            'tipo_documento'     => $data['tipo_documento'],
            'correlativo_actual' => $data['correlativo_actual'],
            'caja_id'            => $data['caja_id'] ?? null,
            'almacen_id'         => $data['almacen_id'] ?? null,
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
