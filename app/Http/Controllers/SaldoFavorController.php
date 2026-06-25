<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Movimiento;
use App\Models\SaldoFavor;
use App\Models\Venta;
use App\Services\CajaService;
use App\Services\CobranzaService;
use App\Services\VendedorScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaldoFavorController extends Controller
{
    public function __construct(private CobranzaService $cobranza) {}

    public function index(Request $request)
    {
        $saldosActivos = SaldoFavor::with(['cliente', 'pago', 'ventaOrigen'])
            ->whereIn('estado', ['disponible', 'parcialmente_usado'])
            ->where('monto_disponible', '>', 0)
            ->orderBy('created_at', 'desc');

        // Si el usuario NO tiene permiso de ver todos, aplicar filtros de caja/vendedor
        $verTodos = auth()->user()?->puedeHacer('saldos.ver_todos');

        if (!$verTodos) {
            VendedorScope::aplicarSaldos($saldosActivos);

            if (session('caja_id')) {
                $saldosActivos->where('caja_id', session('caja_id'));
            }
        }

        $saldosActivos = $saldosActivos->get();
        $saldosPorCliente = $saldosActivos->groupBy('cliente_id');
        $clienteIds = $saldosActivos->pluck('cliente_id')->unique()->toArray();

        $saldosUsados = SaldoFavor::with(['ventaOrigen', 'anulador'])
            ->whereIn('cliente_id', $clienteIds)
            ->whereIn('estado', ['usado', 'anulado'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('cliente_id');

        $pendientesPorCliente = Venta::whereIn('cliente_id', $clienteIds)
            ->whereIn('estado', ['pendiente', 'parcial'])
            ->selectRaw('cliente_id, count(*) as total')
            ->groupBy('cliente_id')
            ->pluck('total', 'cliente_id');

        $clientes = Cliente::whereIn('id', $clienteIds)
            ->orderBy('nombre')
            ->get()
            ->map(function ($c) use ($saldosPorCliente, $saldosUsados, $pendientesPorCliente) {
                $activos = $saldosPorCliente->get($c->id, collect());
                $c->saldo_total = round($activos->sum('monto_disponible'), 2);
                $c->saldos = $activos;
                $c->saldos_historial = $saldosUsados->get($c->id, collect());
                $c->ventas_pendientes_count = $pendientesPorCliente->get($c->id, 0);
                return $c;
            });

        $totalDisponible = round($saldosActivos->sum('monto_disponible'), 2);
        $totalClientes = $saldosActivos->pluck('cliente_id')->unique()->count();
        $totalRegistros = $saldosActivos->count();

        $todosClientes = Cliente::where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'documento']);

        $idsNcConvertidas = $this->idsNotasCreditoConvertidas();

        $notasCreditoPendientes = Venta::where('documento_tipo', 'nota_credito')
            ->whereNotIn('id', $idsNcConvertidas)
            ->where('total', '<', 0)
            ->count();

        $notasCreditoSinCliente = Venta::where('documento_tipo', 'nota_credito')
            ->whereNotIn('id', $idsNcConvertidas)
            ->where('total', '<', 0)
            ->whereNull('cliente_id')
            ->count();

        $cajaAbierta = CajaService::cajaAbierta();

        return view('casadets.saldos_favor.index', compact(
            'clientes',
            'totalDisponible',
            'totalClientes',
            'totalRegistros',
            'todosClientes',
            'notasCreditoPendientes',
            'notasCreditoSinCliente',
            'cajaAbierta'
        ));
    }

    public function clientesJson()
    {
        return response()->json(
            Cliente::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'documento'])
        );
    }

    public function notasCreditoDisponibles()
    {
        $ventas = Venta::where('documento_tipo', 'nota_credito')
            ->whereNotIn('id', $this->idsNotasCreditoConvertidas())
            ->where('total', '<', 0)
            ->with('cliente')
            ->orderBy('fecha', 'desc')
            ->get();

        return response()->json($ventas->map(fn ($v) => [
            'id'               => $v->id,
            'numero'           => $v->documento_numero ?? ('NC #' . $v->id),
            'cliente_id'       => $v->cliente_id,
            'cliente'          => $v->cliente->nombre ?? null,
            'cliente_doc'      => $v->cliente->documento ?? null,
            'requiere_cliente' => !$v->cliente_id,
            'fecha'            => $v->fecha->format('d/m/Y'),
            'monto'            => abs((float) $v->total),
        ]));
    }

    public function asignarClienteNC(Request $request, Venta $venta)
    {
        $data = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
        ]);

        $error = $this->validarNotaCreditoConvertible($venta, false);
        if ($error) {
            return response()->json(['success' => false, 'message' => $error], 422);
        }

        $venta->update(['cliente_id' => $data['cliente_id']]);
        $venta->load('cliente');

        return response()->json([
            'success'     => true,
            'message'     => 'Cliente asignado correctamente.',
            'cliente_id'  => $venta->cliente_id,
            'cliente'     => $venta->cliente?->nombre,
            'cliente_doc' => $venta->cliente?->documento,
        ]);
    }

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
            'caja_id'          => session('caja_id'),
            'venta_origen_id'  => null,
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

    public function convertirNC(Request $request, Venta $venta)
    {
        $error = $this->validarNotaCreditoConvertible($venta, true);
        if ($error) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $error], 422);
            }

            return back()->with('error', $error);
        }

        $monto = abs((float) $venta->total);

        DB::transaction(function () use ($venta, $monto) {
            SaldoFavor::create([
                'cliente_id'       => $venta->cliente_id,
                'pago_id'          => null,
                'venta_origen_id'  => $venta->id,
                'monto_original'   => $monto,
                'monto_disponible' => $monto,
                'estado'           => 'disponible',
                'descripcion'      => 'NC ' . ($venta->documento_numero ?: '#' . $venta->id) . ' - Convertida a saldo a favor',
                'fecha'            => $venta->fecha->format('Y-m-d'),
            ]);

            $venta->update([
                'observaciones' => trim(($venta->observaciones ? $venta->observaciones . ' - ' : '') . 'Convertida a saldo a favor'),
            ]);
        });

        $message = 'Nota de credito ' . ($venta->documento_numero ?? '#' . $venta->id)
            . ' convertida a saldo a favor por S/ ' . number_format($monto, 2) . '.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'monto'   => $monto,
            ]);
        }

        return redirect('/casadets/saldos-favor')->with('success', $message);
    }

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
            'tipo_origen'      => $s->venta_origen_id
                ? (optional($s->ventaOrigen)->documento_tipo === 'nota_credito' ? 'nc' : 'excedente')
                : ($s->pago_id ? 'excedente' : 'manual'),
        ]));
    }

    public function ventasPendientesCliente(int $clienteId)
    {
        $ventas = $this->cobranza->ventasPendientesCliente($clienteId);

        return response()->json($ventas->map(fn($v) => [
            'id'               => $v->id,
            'label'            => ($v->documento_tipo ? ucfirst($v->documento_tipo) . ' ' . $v->documento_numero . ' - ' : 'Venta #' . $v->id . ' - ')
                                  . 'S/ ' . number_format($v->saldo_pendiente, 2) . ' pendiente'
                                  . ' (' . $v->fecha->format('d/m/Y') . ')',
            'saldo_pendiente'  => $v->saldo_pendiente,
            'estado'           => $v->estado,
        ]));
    }

    public function anular(Request $request, SaldoFavor $saldo)
    {
        if ($saldo->estado === 'anulado') {
            return response()->json(['success' => false, 'message' => 'Este saldo ya está anulado.'], 422);
        }

        $data = $request->validate([
            'motivo' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($saldo, $data) {
            $montoAnulado = (float) $saldo->monto_disponible;

            $saldo->update([
                'estado'           => 'anulado',
                'monto_disponible' => 0,
                'anulado_at'       => now(),
                'anulado_por_id'   => auth()->id(),
                'motivo_anulacion' => $data['motivo'] ?? null,
            ]);

            // Registrar en el ledger para trazabilidad (subtipo 'anulacion' → no afecta balance)
            if ($montoAnulado > 0) {
                $cajaId  = session('caja_id');
                $empresa = $cajaId
                    ? (\App\Models\Caja::find($cajaId)?->empresa ?? session('empresa', 'casadets'))
                    : session('empresa', 'casadets');

                $obs = 'Anulación de saldo a favor' . ($saldo->descripcion ? ' — ' . $saldo->descripcion : '');
                if ($data['motivo'] ?? null) {
                    $obs .= ' — Motivo: ' . $data['motivo'];
                }

                Movimiento::create([
                    'tipo'            => 'contable',
                    'subtipo'         => 'anulacion',
                    'origen'          => 'auto',
                    'estado'          => 'activo',
                    'empresa'         => $empresa,
                    'caja_id'         => $cajaId,
                    'categoria'       => 'saldo_favor',
                    'referencia_tipo' => 'saldo_favor',
                    'referencia_id'   => $saldo->id,
                    'cliente_id'      => $saldo->cliente_id,
                    'user_id'         => auth()->id(),
                    'monto'           => $montoAnulado,
                    'fecha'           => today(),
                    'observaciones'   => $obs,
                ]);
            }
        });

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Saldo a favor anulado correctamente.',
            ]);
        }

        return redirect('/casadets/saldos-favor')->with('success', 'Saldo a favor anulado. Queda registrado en el historial.');
    }

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
                    'success'        => true,
                    'aplicado'       => $result['aplicado'],
                    'saldo_restante' => $result['saldo_restante'],
                    'estado_venta'   => $result['estado_venta'],
                    'saldo_estado'   => $result['saldo_estado'],
                    'message'        => 'Saldo aplicado correctamente. Se cobro S/ ' . number_format($result['aplicado'], 2),
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

    private function idsNotasCreditoConvertidas(): array
    {
        return SaldoFavor::whereNotNull('venta_origen_id')
            ->pluck('venta_origen_id')
            ->unique()
            ->toArray();
    }

    private function validarNotaCreditoConvertible(Venta $venta, bool $requiereCliente): ?string
    {
        if ($venta->documento_tipo !== 'nota_credito') {
            return 'Este documento no es una nota de credito.';
        }

        if ($requiereCliente && !$venta->cliente_id) {
            return 'La nota de credito no tiene cliente asignado. Asigna un cliente primero.';
        }

        if (SaldoFavor::where('venta_origen_id', $venta->id)->exists()) {
            return 'Esta nota de credito ya fue convertida a saldo a favor anteriormente.';
        }

        if (abs((float) $venta->total) <= 0 || (float) $venta->total >= 0) {
            return 'El monto de la nota de credito debe ser mayor a cero y estar registrado en negativo.';
        }

        return null;
    }
}
