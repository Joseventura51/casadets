<?php

namespace App\Http\Controllers;

use App\Models\Movimiento;
use App\Models\Vendedor;
use App\Services\VendedorScope;
use Illuminate\Http\Request;

class MovimientoController extends Controller
{
    public function index(Request $request)
    {
        $periodo = $request->input('periodo', 'hoy');
        [$desde, $hasta] = $this->rangoPeriodo($periodo, $request);

        $query = Movimiento::with([
                'cliente:id,nombre,documento',
                'vendedor:id,nombre',
                'pago.metodos',
                'pago.detalles.venta',
            ])
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc');

        // Restricción por vendedor asignado al usuario
        VendedorScope::aplicarMovimientos($query);

        if (session('caja_id')) {
            $query->where('caja_id', session('caja_id'));
        }

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

        $categorias = Movimiento::query()
            ->select('categoria')
            ->whereNotNull('categoria')
            ->distinct()
            ->orderBy('categoria')
            ->pluck('categoria');

        return view('movimientos.index', compact('movimientos', 'totales', 'desde', 'hasta', 'periodo', 'categorias'));
    }

    private function rangoPeriodo(string $periodo, Request $request): array
    {
        return match ($periodo) {
            'ayer'   => [today()->subDay()->toDateString(), today()->subDay()->toDateString()],
            'semana' => [today()->startOfWeek()->toDateString(), today()->toDateString()],
            'mes'    => [today()->startOfMonth()->toDateString(), today()->toDateString()],
            'todo'   => ['1900-01-01', today()->toDateString()],
            'rango'  => $this->rangoPersonalizado($request),
            default  => [today()->toDateString(), today()->toDateString()],
        };
    }

    private function rangoPersonalizado(Request $request): array
    {
        $desde = $request->input('desde', today()->toDateString());
        $hasta = $request->input('hasta', $desde);
        if ($hasta < $desde) {
            $hasta = $desde;
        }
        return [$desde, $hasta];
    }

    public function create(string $tipo)
    {
        // Los vendedores solo se necesitan para salidas manuales
        $vendedores = ($tipo === 'salida')
            ? Vendedor::where('activo', true)->orderBy('nombre')->get(['id', 'nombre'])
            : collect();

        return view('movimientos.create', compact('tipo', 'vendedores'));
    }

    public function store(Request $request)
    {
        $esSalida = $request->input('tipo') === 'salida';

        $rules = [
            'tipo'             => 'required|in:ingreso,salida',
            'categoria'        => 'required|string|max:255',
            'empresa'          => 'nullable|string|in:casadets,zendy',
            'metodo_pago'      => 'required|string|in:efectivo,yape,plin,deposito,transferencia',
            'documento_tipo'   => 'nullable|string|max:50',
            'documento_numero' => 'nullable|string|max:255',
            'monto'            => 'required|numeric|min:0.01',
            'fecha'            => 'required|date',
            'observaciones'    => 'nullable|string',
            'vendedor_id'      => $esSalida ? 'required|exists:vendedores,id' : 'nullable|exists:vendedores,id',
        ];

        $request->validate($rules);

        Movimiento::create(array_merge(
            $request->only([
                'tipo', 'categoria', 'metodo_pago', 'documento_tipo',
                'documento_numero', 'monto', 'fecha', 'observaciones', 'vendedor_id',
            ]),
            [
                'subtipo' => 'manual',
                'origen'  => 'manual',
                'estado'  => 'activo',
                'empresa' => $request->input('empresa', 'casadets'),
                'caja_id' => session('caja_id'),
                'user_id' => auth()->id(),
            ]
        ));

        return redirect('/movimientos')->with('success', 'Movimiento registrado.');
    }
}
