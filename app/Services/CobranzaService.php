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
}
