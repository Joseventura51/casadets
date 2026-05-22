<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\SaldoFavor;
use App\Models\Venta;
use App\Services\CobranzaService;
use Illuminate\Http\Request;

class SaldoFavorController extends Controller
{
    public function __construct(private CobranzaService $cobranza) {}

    /* ── Listado general agrupado por cliente ─── */
    public function index(Request $request)
    {
        // Clientes con saldo activo
        $clientesIds = SaldoFavor::whereIn('estado', ['disponible', 'parcialmente_usado'])
            ->where('monto_disponible', '>', 0)
            ->pluck('cliente_id')
            ->unique();

        $clientes = Cliente::whereIn('id', $clientesIds)
            ->withCount(['ventas as ventas_pendientes_count' => function ($q) {
                $q->whereIn('estado', ['pendiente', 'parcial']);
            }])
            ->orderBy('nombre')
            ->get()
            ->map(function ($c) {
                $c->saldo_total = $this->cobranza->saldoFavorDisponible($c->id);
                $c->saldos      = SaldoFavor::with('pago')
                    ->where('cliente_id', $c->id)
                    ->orderBy('created_at', 'desc')
                    ->get();
                return $c;
            });

        // KPIs
        $totalDisponible    = SaldoFavor::whereIn('estado', ['disponible', 'parcialmente_usado'])->sum('monto_disponible');
        $totalClientes      = $clientesIds->count();
        $totalRegistros     = SaldoFavor::whereIn('estado', ['disponible', 'parcialmente_usado'])->count();

        return view('casadets.saldos_favor.index', compact(
            'clientes', 'totalDisponible', 'totalClientes', 'totalRegistros'
        ));
    }

    /* ── JSON: saldos disponibles de un cliente (para verificar_pago) ── */
    public function saldosCliente(int $clienteId)
    {
        $saldos = $this->cobranza->saldosDisponibles($clienteId);
        return response()->json($saldos->map(fn($s) => [
            'id'               => $s->id,
            'monto_disponible' => (float) $s->monto_disponible,
            'monto_original'   => (float) $s->monto_original,
            'descripcion'      => $s->descripcion,
            'fecha'            => $s->fecha->format('d/m/Y'),
            'estado'           => $s->estado,
        ]));
    }

    /* ── JSON: ventas pendientes/parciales de un cliente ── */
    public function ventasPendientesCliente(int $clienteId)
    {
        $ventas = $this->cobranza->ventasPendientesCliente($clienteId);
        return response()->json($ventas->map(fn($v) => [
            'id'               => $v->id,
            'label'            => ($v->documento_tipo ? ucfirst($v->documento_tipo) . ' ' . $v->documento_numero . ' — ' : 'Venta #' . $v->id . ' — ')
                                  . 'S/ ' . number_format($v->saldo_pendiente, 2) . ' pendiente'
                                  . ' (' . $v->fecha->format('d/m/Y') . ')',
            'saldo_pendiente'  => $v->saldo_pendiente,
            'estado'           => $v->estado,
        ]));
    }

    /* ── Aplicar saldo a una venta (POST) ── */
    public function aplicar(Request $request, SaldoFavor $saldo)
    {
        $data = $request->validate([
            'venta_id' => 'required|exists:ventas,id',
            'monto'    => 'required|numeric|min:0.01',
        ]);

        $venta = Venta::findOrFail($data['venta_id']);

        try {
            $result = $this->cobranza->aplicarSaldoFavor($saldo, $venta, (float) $data['monto']);

            if ($request->expectsJson()) {
                return response()->json([
                    'success'       => true,
                    'aplicado'      => $result['aplicado'],
                    'saldo_restante'=> $result['saldo_restante'],
                    'estado_venta'  => $result['estado_venta'],
                    'saldo_estado'  => $result['saldo_estado'],
                    'message'       => 'Saldo aplicado correctamente. Se cobró S/ ' . number_format($result['aplicado'], 2),
                ]);
            }

            $msg = 'Saldo de S/ ' . number_format($result['aplicado'], 2) . ' aplicado correctamente a la venta #' . $venta->id . '.';
            if ($result['saldo_restante'] > 0) {
                $msg .= ' Queda S/ ' . number_format($result['saldo_restante'], 2) . ' de saldo a favor disponible.';
            }
            return redirect('/casadets/saldos-favor')->with('success', $msg);

        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return back()->with('error', $e->getMessage());
        }
    }
}
