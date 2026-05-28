<?php

namespace App\Http\Controllers;

use App\Models\Movimiento;
use Illuminate\Http\Request;

class MovimientoController extends Controller
{
    public function index(Request $request)
    {
        $desde = $request->input('desde', today()->toDateString());
        $hasta = $request->input('hasta', $desde);
        if ($hasta < $desde) $hasta = $desde;

        $query = Movimiento::with([
                'cliente:id,nombre,documento',
                'pago.metodos',
                'pago.detalles.venta',
            ])
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc');

        if ($request->filled('tipo'))        $query->where('tipo', $request->tipo);
        if ($request->filled('subtipo'))     $query->where('subtipo', $request->subtipo);
        if ($request->filled('empresa'))     $query->where('empresa', $request->empresa);
        if ($request->filled('estado'))      $query->where('estado', $request->estado);
        if ($request->filled('metodo_pago')) $query->where('metodo_pago', $request->metodo_pago);
        if ($request->filled('categoria'))   $query->where('categoria', 'like', '%'.$request->categoria.'%');
        if ($request->filled('cliente')) {
            $query->whereHas('cliente', fn ($q) => $q->where('nombre', 'like', '%'.$request->cliente.'%'));
        }
        if ($request->filled('documento')) {
            $query->where(function ($q) use ($request) {
                $q->where('documento_numero', 'like', '%'.$request->documento.'%')
                  ->orWhere('documento_tipo',   'like', '%'.$request->documento.'%');
            });
        }
        $query->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta);

        $movimientos = $query->paginate(50)->withQueryString();

        // Anular relación pago para movimientos que no son referencia='pago'
        $movimientos->each(function ($m) {
            if ($m->referencia_tipo !== 'pago') {
                $m->setRelation('pago', null);
            }
        });

        // Totales de la página — solo movimientos activos afectan balance
        $col = $movimientos->getCollection();
        $activos = $col->where('estado', 'activo');
        $totales = [
            'ingresos' => round($activos->where('tipo', 'ingreso')->sum('monto'), 2),
            'salidas'  => round($activos->where('tipo', 'salida')->sum('monto'), 2),
            'balance'  => round(
                $activos->where('tipo', 'ingreso')->sum('monto') - $activos->where('tipo', 'salida')->sum('monto'),
                2
            ),
        ];

        return view('movimientos.index', compact('movimientos', 'totales', 'desde', 'hasta'));
    }

    public function create(string $tipo)
    {
        return view('movimientos.create', compact('tipo'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'tipo'             => 'required|in:ingreso,salida',
            'categoria'        => 'required|string|max:255',
            'empresa'          => 'nullable|string|in:casadets,zendy',
            'metodo_pago'      => 'required|string|in:efectivo,yape,plin,deposito,transferencia',
            'documento_tipo'   => 'nullable|string|max:50',
            'documento_numero' => 'nullable|string|max:255',
            'monto'            => 'required|numeric|min:0.01',
            'fecha'            => 'required|date',
            'observaciones'    => 'nullable|string',
        ]);

        Movimiento::create(array_merge(
            $request->only([
                'tipo', 'categoria', 'metodo_pago', 'documento_tipo',
                'documento_numero', 'monto', 'fecha', 'observaciones',
            ]),
            [
                'subtipo' => 'manual',
                'origen'  => 'manual',
                'estado'  => 'activo',
                'empresa' => $request->input('empresa', 'casadets'),
            ]
        ));

        return redirect('/movimientos')->with('success', 'Movimiento registrado.');
    }
}
