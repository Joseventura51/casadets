<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\SaldoFavor;
use App\Models\Venta;
use App\Services\CobranzaService;
use Illuminate\Http\Request;
use Carbon\Carbon;

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

        $todosClientes = Cliente::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'documento']);

        return view('casadets.saldos_favor.index', compact(
            'clientes', 'totalDisponible', 'totalClientes', 'totalRegistros', 'todosClientes'
        ));
    }

    /* ── JSON: lista de todos los clientes activos ─────────────────── */
    public function clientesJson()
    {
        $clientes = Cliente::where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'documento']);
        return response()->json($clientes);
    }

    /* ── JSON: notas de crédito disponibles para convertir ──────────── */
    public function notasCreditoDisponibles()
    {
        $ventas = Venta::where('documento_tipo', 'nota_credito')
            ->whereNotNull('cliente_id')
            ->with('cliente')
            ->orderBy('fecha', 'desc')
            ->get()
            ->filter(function ($v) {
                // Excluir las que ya fueron convertidas a saldo
                return !SaldoFavor::where('descripcion', 'like', '%NC #' . $v->id . '%')->exists();
            })
            ->values();

        return response()->json($ventas->map(fn ($v) => [
            'id'      => $v->id,
            'numero'  => $v->documento_numero ?? ('NC #' . $v->id),
            'cliente' => $v->cliente->nombre ?? '—',
            'fecha'   => $v->fecha->format('d/m/Y'),
            'monto'   => abs((float) $v->total),
        ]));
    }

    /* ── Crear saldo a favor manualmente ───────────────────────────── */
    public function crear(Request $request)
    {
        $data = $request->validate([
            'cliente_id'  => 'required|exists:clientes,id',
            'monto'       => 'required|numeric|min:0.01',
            'descripcion' => 'nullable|string|max:255',
            'fecha'       => 'required|date',
        ]);

        SaldoFavor::create([
            'cliente_id'       => $data['cliente_id'],
            'pago_id'          => null,
            'monto_original'   => $data['monto'],
            'monto_disponible' => $data['monto'],
            'estado'           => 'disponible',
            'descripcion'      => $data['descripcion'] ?: 'Ingreso manual',
            'fecha'            => $data['fecha'],
        ]);

        $cliente = Cliente::find($data['cliente_id']);
        return redirect('/casadets/saldos-favor')->with(
            'success',
            'Saldo a favor de S/ ' . number_format($data['monto'], 2) . ' creado para ' . ($cliente->nombre ?? '') . '.'
        );
    }

    /* ── Convertir nota de crédito a saldo a favor ─────────────────── */
    public function convertirNC(Request $request, Venta $venta)
    {
        if ($venta->documento_tipo !== 'nota_credito') {
            return back()->with('error', 'Este documento no es una nota de crédito.');
        }
        if (!$venta->cliente_id) {
            return back()->with('error', 'La nota de crédito no tiene cliente asignado. Asigna un cliente primero.');
        }

        // Verificar que no fue ya convertida
        $yaConvertida = SaldoFavor::where('descripcion', 'like', '%NC #' . $venta->id . '%')->exists();
        if ($yaConvertida) {
            return back()->with('error', 'Esta nota de crédito ya fue convertida a saldo a favor anteriormente.');
        }

        $monto = abs((float) $venta->total);
        if ($monto <= 0) {
            return back()->with('error', 'El monto de la nota de crédito debe ser mayor a cero.');
        }

        SaldoFavor::create([
            'cliente_id'       => $venta->cliente_id,
            'pago_id'          => null,
            'monto_original'   => $monto,
            'monto_disponible' => $monto,
            'estado'           => 'disponible',
            'descripcion'      => 'NC #' . $venta->id . ($venta->documento_numero ? ' (' . $venta->documento_numero . ')' : '') . ' — Convertida a saldo a favor',
            'fecha'            => $venta->fecha->format('Y-m-d'),
        ]);

        // Anotar en la venta que ya fue convertida
        $venta->update([
            'observaciones' => trim(($venta->observaciones ? $venta->observaciones . ' — ' : '') . 'Convertida a saldo a favor'),
        ]);

        return redirect('/casadets/saldos-favor')->with(
            'success',
            'Nota de crédito ' . ($venta->documento_numero ?? '#' . $venta->id) . ' convertida a saldo a favor por S/ ' . number_format($monto, 2) . '.'
        );
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
