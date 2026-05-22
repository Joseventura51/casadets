<?php

namespace App\Services;

use App\Models\DetallePagoFactura;
use App\Models\Movimiento;
use App\Models\Pago;
use App\Models\SaldoFavor;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;

class CobranzaService
{
    /**
     * Registra un pago para una venta.
     *
     * Escenarios manejados:
     *  - Pago exacto   → venta queda PAGADA
     *  - Pago parcial  → venta queda PARCIAL
     *  - Pago excedente→ venta queda PAGADA + saldo a favor del cliente
     *  - Venta ya PAGADA → 100% saldo a favor (no se toca la venta)
     *
     * @param  Venta  $venta
     * @param  array  $pagosInput   [['metodo'=>'efectivo','monto'=>100], ...]
     * @param  string|null $estadoManual  forzar estado: 'pendiente'|'pagado'|'anulado'|null
     * @return array  ['pago'=>Pago, 'estado'=>string, 'saldo_favor'=>float, 'saldo_pendiente'=>float]
     */
    public function registrarPago(Venta $venta, array $pagosInput, ?string $estadoManual = null): array
    {
        return DB::transaction(function () use ($venta, $pagosInput, $estadoManual) {

            // ── 1. Calcular monto total del nuevo pago ─────────────────
            $pagosReales = collect($pagosInput)
                ->filter(fn($p) => ($p['metodo'] ?? 'ninguno') !== 'ninguno' && ($p['monto'] ?? 0) > 0);

            $montoNuevo = round($pagosReales->sum(fn($p) => (float) $p['monto']), 2);

            // Método de pago: lista única de métodos usados
            $metodoStr = $pagosReales->pluck('metodo')->unique()->implode(',') ?: null;

            // Si no hay pago real, solo cambiar estado si se forzó manualmente
            if ($montoNuevo <= 0) {
                if ($estadoManual && $estadoManual !== 'ninguno') {
                    $venta->update(['estado' => $estadoManual]);
                }
                return [
                    'pago'            => null,
                    'estado'          => $venta->fresh()->estado,
                    'saldo_favor'     => 0.0,
                    'saldo_pendiente' => (float) $venta->total - (float) $venta->pagado,
                ];
            }

            // ── 2. Crear el registro de Pago ───────────────────────────
            $pago = Pago::create([
                'cliente_id'  => $venta->cliente_id,
                'monto_total' => $montoNuevo,
                'metodo_pago' => $metodoStr,
                'estado'      => 'aplicado',
                'fecha'       => now()->toDateString(),
            ]);

            // ── 3. Calcular cuánto se aplica a la venta ────────────────
            $totalDeuda       = (float) $venta->total;
            $yaPagedo         = (float) $venta->pagado;
            $saldoDeuda       = max(0, round($totalDeuda - $yaPagedo, 2));
            $ventaYaPagada    = $venta->estado === 'pagado';

            if ($ventaYaPagada) {
                // Venta ya pagada: todo el monto nuevo va a saldo a favor
                $montoAplicado = 0;
                $excedente     = $montoNuevo;
            } else {
                $montoAplicado = min($montoNuevo, $saldoDeuda);
                $excedente     = round($montoNuevo - $montoAplicado, 2);
            }

            // ── 4. Registrar detalle pago ↔ factura ────────────────────
            if ($montoAplicado > 0) {
                DetallePagoFactura::create([
                    'pago_id'        => $pago->id,
                    'venta_id'       => $venta->id,
                    'monto_aplicado' => $montoAplicado,
                ]);

                // Actualizar columna pagado de la venta
                $nuevoPagado = round($yaPagedo + $montoAplicado, 2);
                $venta->update(['pagado' => $nuevoPagado, 'metodo_pago' => $metodoStr]);

                // Recalcular estado (a menos que sea manual)
                if ($estadoManual) {
                    $venta->update(['estado' => $estadoManual]);
                } else {
                    $venta->refresh();
                    $venta->recalcularEstado();
                }
            }

            // ── 5. Manejar excedente → saldo a favor ──────────────────
            if ($excedente > 0.005 && $venta->cliente_id) {
                $descripcion = $ventaYaPagada
                    ? "Pago sobre venta ya cobrada #{$venta->id} ({$venta->documento_tipo} {$venta->documento_numero})"
                    : "Excedente de pago en venta #{$venta->id} ({$venta->documento_tipo} {$venta->documento_numero})";

                SaldoFavor::create([
                    'cliente_id'       => $venta->cliente_id,
                    'pago_id'          => $pago->id,
                    'monto_original'   => $excedente,
                    'monto_disponible' => $excedente,
                    'estado'           => 'disponible',
                    'descripcion'      => $descripcion,
                    'fecha'            => now()->toDateString(),
                ]);

                $pago->update(['estado' => $montoAplicado > 0 ? 'parcial' : 'saldo_favor']);
            }

            // ── 6. Registrar en movimientos_caja ──────────────────────
            if ($montoNuevo > 0) {
                $docStr = trim(ucfirst($venta->documento_tipo ?? '') . ' ' . ($venta->documento_numero ?? ''));
                Movimiento::create([
                    'tipo'             => 'ingreso',
                    'subtipo'          => 'pago_venta',
                    'categoria'        => 'Cobro de venta',
                    'metodo_pago'      => $metodoStr,
                    'referencia_tipo'  => 'pago',
                    'referencia_id'    => $pago->id,
                    'cliente_id'       => $venta->cliente_id,
                    'documento_tipo'   => $venta->documento_tipo ?? 'venta',
                    'documento_numero' => $venta->documento_numero ?? (string) $venta->id,
                    'monto'            => $montoNuevo,
                    'fecha'            => now()->toDateString(),
                    'observaciones'    => "Pago venta #{$venta->id}" . ($docStr ? " — {$docStr}" : ''),
                ]);
            }

            $venta->refresh();
            return [
                'pago'            => $pago,
                'estado'          => $venta->estado,
                'saldo_favor'     => $excedente > 0.005 ? $excedente : 0.0,
                'saldo_pendiente' => max(0, round((float) $venta->total - (float) $venta->pagado, 2)),
            ];
        });
    }

    /**
     * Retorna el saldo a favor disponible de un cliente.
     */
    public function saldoFavorDisponible(int $clienteId): float
    {
        return (float) SaldoFavor::where('cliente_id', $clienteId)
            ->whereIn('estado', ['disponible', 'parcialmente_usado'])
            ->sum('monto_disponible');
    }

    /**
     * Historial de pagos aplicados a una venta.
     */
    public function historialPagos(Venta $venta): \Illuminate\Support\Collection
    {
        return DetallePagoFactura::with('pago')
            ->where('venta_id', $venta->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Aplica un saldo a favor a una venta pendiente/parcial.
     *
     * @param  SaldoFavor  $saldo       El registro de saldo a consumir
     * @param  Venta       $venta       La venta destino (debe ser del mismo cliente)
     * @param  float       $monto       Cuánto aplicar (máx: min(saldo disponible, deuda venta))
     * @return array  ['aplicado'=>float, 'saldo_restante'=>float, 'estado_venta'=>string]
     */
    public function aplicarSaldoFavor(SaldoFavor $saldo, Venta $venta, float $monto): array
    {
        return DB::transaction(function () use ($saldo, $venta, $monto) {

            if ($saldo->cliente_id !== $venta->cliente_id) {
                throw new \InvalidArgumentException('El saldo no pertenece al cliente de la venta.');
            }
            if (!in_array($saldo->estado, ['disponible', 'parcialmente_usado'])) {
                throw new \InvalidArgumentException('El saldo ya fue utilizado completamente.');
            }
            if ($venta->estado === 'pagado') {
                throw new \InvalidArgumentException('La venta ya está pagada.');
            }

            $disponible   = (float) $saldo->monto_disponible;
            $deuda        = max(0, (float) $venta->total - (float) $venta->pagado);
            $aplicar      = round(min($monto, $disponible, $deuda), 2);

            if ($aplicar <= 0) {
                throw new \InvalidArgumentException('El monto a aplicar debe ser mayor a cero.');
            }

            // Crear o reusar un Pago tipo "saldo_favor" para el registro contable
            $pago = Pago::create([
                'cliente_id'  => $venta->cliente_id,
                'monto_total' => $aplicar,
                'metodo_pago' => 'saldo_favor',
                'estado'      => 'aplicado',
                'fecha'       => now()->toDateString(),
                'observacion' => "Uso de saldo a favor SF#{$saldo->id}",
            ]);

            // Vincular pago con venta
            DetallePagoFactura::create([
                'pago_id'        => $pago->id,
                'venta_id'       => $venta->id,
                'monto_aplicado' => $aplicar,
            ]);

            // Actualizar venta
            $nuevoPagado = round((float) $venta->pagado + $aplicar, 2);
            $venta->update(['pagado' => $nuevoPagado, 'metodo_pago' => $venta->metodo_pago]);
            $venta->refresh();
            $venta->recalcularEstado();

            // Actualizar saldo a favor
            $nuevoDisponible = round($disponible - $aplicar, 2);
            $nuevoEstado = $nuevoDisponible <= 0.005
                ? 'usado'
                : 'parcialmente_usado';
            $saldo->update([
                'monto_disponible' => max(0, $nuevoDisponible),
                'estado'           => $nuevoEstado,
            ]);

            // Movimiento de caja
            $docStr = trim(ucfirst($venta->documento_tipo ?? '') . ' ' . ($venta->documento_numero ?? ''));
            Movimiento::create([
                'tipo'             => 'ingreso',
                'subtipo'          => 'saldo_favor_usado',
                'categoria'        => 'Saldo a favor aplicado',
                'metodo_pago'      => 'saldo_favor',
                'referencia_tipo'  => 'saldo_favor',
                'referencia_id'    => $saldo->id,
                'cliente_id'       => $venta->cliente_id,
                'documento_tipo'   => $venta->documento_tipo ?? 'venta',
                'documento_numero' => $venta->documento_numero ?? (string) $venta->id,
                'monto'            => $aplicar,
                'fecha'            => now()->toDateString(),
                'observaciones'    => "Saldo a favor SF#{$saldo->id} aplicado a venta #{$venta->id}" . ($docStr ? " ({$docStr})" : ''),
            ]);

            $venta->refresh();
            return [
                'aplicado'       => $aplicar,
                'saldo_restante' => max(0, $nuevoDisponible),
                'estado_venta'   => $venta->estado,
                'saldo_estado'   => $nuevoEstado,
            ];
        });
    }

    /**
     * Saldos a favor disponibles de un cliente (colección).
     */
    public function saldosDisponibles(int $clienteId): \Illuminate\Support\Collection
    {
        return SaldoFavor::with('pago')
            ->where('cliente_id', $clienteId)
            ->whereIn('estado', ['disponible', 'parcialmente_usado'])
            ->where('monto_disponible', '>', 0)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Ventas pendientes o parciales de un cliente.
     */
    public function ventasPendientesCliente(int $clienteId): \Illuminate\Support\Collection
    {
        return Venta::where('cliente_id', $clienteId)
            ->whereIn('estado', ['pendiente', 'parcial'])
            ->select('id', 'fecha', 'total', 'pagado', 'documento_tipo', 'documento_numero', 'estado')
            ->orderBy('fecha', 'asc')
            ->get()
            ->map(function ($v) {
                $v->saldo_pendiente = round((float) $v->total - (float) $v->pagado, 2);
                return $v;
            });
    }
}
