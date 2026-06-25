<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Models\Devolucion;
use App\Models\Movimiento;
use App\Models\Pago;
use App\Models\Vendedor;
use App\Services\VendedorScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        // Totales de la página — solo movimientos activos afectan balance.
        // Los subtipos 'anulacion' y 'saldo_favor_usado' son reversas internas
        // (no representan flujo de caja real) y se excluyen del balance.
        $col    = $movimientos->getCollection();
        $activos = $col->where('estado', 'activo');

        $SUBTIPOS_NO_BALANCE = ['anulacion', 'saldo_favor_usado'];
        $activosBalance = $activos->filter(
            fn ($m) => !in_array($m->subtipo, $SUBTIPOS_NO_BALANCE, true)
        );

        $totales = [
            'ingresos' => round($activosBalance->where('tipo', 'ingreso')->sum('monto'), 2),
            'salidas'  => round($activosBalance->where('tipo', 'salida')->sum('monto'), 2),
            'balance'  => round(
                $activosBalance->where('tipo', 'ingreso')->sum('monto') - $activosBalance->where('tipo', 'salida')->sum('monto'),
                2
            ),
            'reversas' => round($activos->whereIn('subtipo', $SUBTIPOS_NO_BALANCE)->sum('monto'), 2),
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

        // Empresa de la caja activa en sesión (para preseleccionar en el form)
        $cajaActiva       = session('caja_id') ? Caja::find(session('caja_id')) : null;
        $empresaPreselect = $cajaActiva?->empresa ?? 'casadets';

        return view('movimientos.create', compact('tipo', 'vendedores', 'empresaPreselect', 'cajaActiva'));
    }

    /* ─── Anular un movimiento ───────────────────────────────────── */

    public function anular(Request $request, Movimiento $movimiento)
    {
        if ($movimiento->estado === 'anulado') {
            return back()->with('info', 'Este movimiento ya está anulado.');
        }

        $request->validate([
            'motivo' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($movimiento, $request) {
            $motivo  = trim($request->input('motivo') ?: 'Anulación de movimiento');
            $empresa = $movimiento->empresa ?? session('empresa', 'casadets');
            $cajaId  = $movimiento->caja_id;

            // 1. Marcar el movimiento como anulado
            $movimiento->update([
                'estado'        => 'anulado',
                'observaciones' => trim(($movimiento->observaciones ?? '') . ' [ANULADO: ' . $motivo . ']'),
            ]);

            // 2. Si está vinculado a un pago: revertir y registrar en Devoluciones
            if ($movimiento->referencia_tipo === 'pago' && $movimiento->referencia_id) {
                $pago = Pago::with(['detalles.venta'])->find($movimiento->referencia_id);

                if ($pago && $pago->estado !== 'anulado') {
                    foreach ($pago->detalles as $dpf) {
                        $venta = $dpf->venta;
                        if (!$venta || $venta->estado === 'anulado') {
                            continue;
                        }

                        // Restar el monto aplicado del campo pagado de la venta
                        $montoAplicado = (float) $dpf->monto_aplicado;
                        $nuevoPagado   = max(0, round((float) $venta->pagado - $montoAplicado, 2));
                        $venta->update(['pagado' => $nuevoPagado]);
                        $venta->refresh();
                        $venta->recalcularEstado(); // → vuelve a 'pendiente' si pagado cae a 0

                        // Registrar en módulo de Devoluciones
                        Devolucion::create([
                            'venta_id'       => $venta->id,
                            'user_id'        => auth()->id(),
                            'tipo'           => 'parcial',
                            'monto_devuelto' => $montoAplicado,
                            'saldo_generado' => 0,
                            'motivo'         => 'Anulación de movimiento #' . $movimiento->id . ' — ' . $motivo,
                            'fecha'          => today(),
                            'empresa'        => $empresa,
                            'caja_id'        => $cajaId,
                        ]);
                    }

                    // Marcar el pago como anulado
                    $pago->update(['estado' => 'anulado']);
                }
            }

            // 3. Crear movimiento contable de contrapartida para dejar el ledger balanceado
            $tipoContra = $movimiento->tipo === 'ingreso' ? 'salida' : 'ingreso';
            Movimiento::create([
                'tipo'            => $tipoContra,
                'subtipo'         => 'anulacion',
                'origen'          => 'auto',
                'estado'          => 'activo',
                'empresa'         => $empresa,
                'caja_id'         => $cajaId,
                'categoria'       => 'anulacion',
                'referencia_tipo' => 'movimiento',
                'referencia_id'   => $movimiento->id,
                'cliente_id'      => $movimiento->cliente_id,
                'user_id'         => auth()->id(),
                'monto'           => (float) $movimiento->monto,
                'fecha'           => today(),
                'observaciones'   => 'Contrapartida anulación Mov. #' . $movimiento->id . ' — ' . $motivo,
            ]);
        });

        return back()->with('success', 'Movimiento #' . $movimiento->id . ' anulado correctamente.');
    }

    /* ─── Registrar movimiento manual ───────────────────────────── */

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

        // Derivar empresa desde la caja activa en sesión para garantizar consistencia.
        // Si no hay caja en sesión, usar el valor del form (fallback).
        $cajaActiva = session('caja_id') ? Caja::find(session('caja_id')) : null;
        $empresa    = $cajaActiva?->empresa ?? $request->input('empresa', 'casadets');

        Movimiento::create(array_merge(
            $request->only([
                'tipo', 'categoria', 'metodo_pago', 'documento_tipo',
                'documento_numero', 'monto', 'fecha', 'observaciones', 'vendedor_id',
            ]),
            [
                'subtipo' => 'manual',
                'origen'  => 'manual',
                'estado'  => 'activo',
                'empresa' => $empresa,
                'caja_id' => session('caja_id'),
                'user_id' => auth()->id(),
            ]
        ));

        return redirect('/movimientos')->with('success', 'Movimiento registrado.');
    }
}
