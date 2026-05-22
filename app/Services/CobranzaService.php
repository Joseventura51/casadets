<?php

namespace App\Services;

use App\Models\DetallePagoFactura;
use App\Models\Movimiento;
use App\Models\Pago;
use App\Models\PagoMetodo;
use App\Models\SaldoFavor;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;

class CobranzaService
{
    /**
     * Registra un pago para una venta y genera el movimiento en el ledger.
     *
     * Escenarios:
     *  - Pago exacto    → venta PAGADA
     *  - Pago parcial   → venta PARCIAL
     *  - Pago excedente → venta PAGADA + saldo a favor (si hay cliente)
     *  - Venta ya PAGADA → 100% saldo a favor
     *
     * @param  Venta        $venta
     * @param  array        $pagosInput   [['metodo'=>'efectivo','monto'=>100.00], ...]
     * @param  string|null  $estadoManual Fuerza estado concreto (sin efecto financiero)
     * @param  int|null     $userId       ID del usuario (auditoría)
     * @param  string       $empresa      Empresa dueña de la operación
     */
    public function registrarPago(
        Venta $venta,
        array $pagosInput,
        ?string $estadoManual = null,
        ?int $userId = null,
        string $empresa = 'casadets'
    ): array {
        return DB::transaction(function () use ($venta, $pagosInput, $estadoManual, $userId, $empresa) {

            // ── 1. Filtrar métodos reales con monto > 0 ────────────────────
            $pagosReales = collect($pagosInput)
                ->filter(fn ($p) => ($p['metodo'] ?? 'ninguno') !== 'ninguno' && ($p['monto'] ?? 0) > 0)
                ->map(fn ($p) => [
                    'metodo' => $p['metodo'],
                    'monto'  => round((float) $p['monto'], 2),
                ]);

            // Suma exacta con bcmath
            $montoNuevo = (float) $pagosReales->reduce(
                fn ($carry, $p) => bcadd($carry, (string) $p['monto'], 2),
                '0'
            );

            // String de métodos únicos (display / compatibilidad)
            $metodoStr = $pagosReales->pluck('metodo')->unique()->implode(',') ?: null;

            if ($montoNuevo <= 0) {
                if ($estadoManual && $estadoManual !== 'ninguno') {
                    $venta->update(['estado' => $estadoManual]);
                }
                return [
                    'pago'            => null,
                    'estado'          => $venta->fresh()->estado,
                    'saldo_favor'     => 0.0,
                    'saldo_pendiente' => max(0, (float) bcsub(
                        (string) $venta->total,
                        (string) $venta->pagado,
                        2
                    )),
                ];
            }

            // ── 2. Crear registro de Pago ────────────────────────────────
            $pago = Pago::create([
                'cliente_id'  => $venta->cliente_id,
                'user_id'     => $userId,
                'monto_total' => $montoNuevo,
                'metodo_pago' => $metodoStr,
                'estado'      => 'aplicado',
                'fecha'       => now()->toDateString(),
            ]);

            // ── 3. Desglose real por método ──────────────────────────────
            foreach ($pagosReales as $p) {
                PagoMetodo::create([
                    'pago_id' => $pago->id,
                    'metodo'  => $p['metodo'],
                    'monto'   => $p['monto'],
                ]);
            }

            // ── 4. Calcular monto aplicable (bcmath) ─────────────────────
            $totalDeuda    = (float) $venta->total;
            $yaPagado      = (float) $venta->pagado;
            $saldoDeuda    = (float) max(
                '0',
                bcsub((string) $totalDeuda, (string) $yaPagado, 2)
            );
            $ventaYaPagada = $venta->estado === 'pagado';

            if ($ventaYaPagada) {
                $montoAplicado = 0.0;
                $excedente     = $montoNuevo;
            } else {
                $montoAplicado = $montoNuevo <= $saldoDeuda ? $montoNuevo : $saldoDeuda;
                $excedente     = (float) bcsub((string) $montoNuevo, (string) $montoAplicado, 2);
            }

            // ── 5. Vincular pago con venta ───────────────────────────────
            if ($montoAplicado > 0) {
                DetallePagoFactura::create([
                    'pago_id'        => $pago->id,
                    'venta_id'       => $venta->id,
                    'monto_aplicado' => $montoAplicado,
                ]);

                $nuevoPagado = (float) bcadd((string) $yaPagado, (string) $montoAplicado, 2);
                $venta->update(['pagado' => $nuevoPagado, 'metodo_pago' => $metodoStr]);

                if ($estadoManual) {
                    $venta->update(['estado' => $estadoManual]);
                } else {
                    $venta->refresh();
                    $venta->recalcularEstado();
                }
            }

            // ── 6. Saldo a favor si hay excedente + cliente ──────────────
            $hayExcedente = $excedente > 0;

            if ($hayExcedente && $venta->cliente_id) {
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

            // ── 7. Movimiento en ledger ──────────────────────────────────
            // NOTA: solo se registra el monto total del pago recibido (montoNuevo).
            // NO se registra el saldo a favor como ingreso separado
            // (ya está incluido en montoNuevo). Sin doble conteo.
            $docStr = trim(ucfirst($venta->documento_tipo ?? '') . ' ' . ($venta->documento_numero ?? ''));
            Movimiento::create([
                'tipo'             => 'ingreso',
                'subtipo'          => 'pago_venta',
                'origen'           => 'auto',
                'estado'           => 'activo',
                'empresa'          => $empresa,
                'categoria'        => 'Cobro de venta',
                'metodo_pago'      => $metodoStr,
                'referencia_tipo'  => 'pago',
                'referencia_id'    => $pago->id,
                'cliente_id'       => $venta->cliente_id,
                'user_id'          => $userId,
                'documento_tipo'   => $venta->documento_tipo,
                'documento_numero' => $venta->documento_numero ?? (string) $venta->id,
                'monto'            => $montoNuevo,
                'fecha'            => now()->toDateString(),
                'observaciones'    => "Pago venta #{$venta->id}" . ($docStr ? " — {$docStr}" : ''),
            ]);

            $venta->refresh();
            return [
                'pago'            => $pago,
                'estado'          => $venta->estado,
                'saldo_favor'     => $hayExcedente ? $excedente : 0.0,
                'saldo_pendiente' => max(0, (float) bcsub(
                    (string) $venta->total,
                    (string) $venta->pagado,
                    2
                )),
            ];
        });
    }

    /**
     * Saldo a favor disponible de un cliente (suma).
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
        return DetallePagoFactura::with(['pago.metodos'])
            ->where('venta_id', $venta->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Aplica un saldo a favor existente a una venta pendiente/parcial.
     *
     * BUG #3 CORREGIDO: NO genera movimiento tipo='ingreso'.
     * El dinero ya entró al sistema cuando se registró el excedente.
     * Se registra un movimiento tipo='contable' solo para trazabilidad.
     *
     * @param  int|null $userId
     */
    public function aplicarSaldoFavor(SaldoFavor $saldo, Venta $venta, float $monto, ?int $userId = null, string $empresa = 'casadets'): array
    {
        return DB::transaction(function () use ($saldo, $venta, $monto, $userId, $empresa) {

            if ($saldo->cliente_id !== $venta->cliente_id) {
                throw new \InvalidArgumentException('El saldo no pertenece al cliente de la venta.');
            }
            if (!in_array($saldo->estado, ['disponible', 'parcialmente_usado'])) {
                throw new \InvalidArgumentException('El saldo ya fue utilizado completamente.');
            }
            if ($venta->estado === 'pagado') {
                throw new \InvalidArgumentException('La venta ya está pagada.');
            }
            if ($venta->estado === 'anulado') {
                throw new \InvalidArgumentException('La venta está anulada.');
            }

            $disponible = (float) $saldo->monto_disponible;
            $deuda      = max(0.0, (float) bcsub((string) $venta->total, (string) $venta->pagado, 2));
            $aplicar    = round(min($monto, $disponible, $deuda), 2);

            if ($aplicar <= 0) {
                throw new \InvalidArgumentException('El monto a aplicar debe ser mayor a cero.');
            }

            // Pago contable para el saldo a favor
            $pago = Pago::create([
                'cliente_id'  => $venta->cliente_id,
                'user_id'     => $userId,
                'monto_total' => $aplicar,
                'metodo_pago' => 'saldo_favor',
                'estado'      => 'aplicado',
                'fecha'       => now()->toDateString(),
                'observacion' => "Uso de saldo a favor SF#{$saldo->id}",
            ]);

            PagoMetodo::create([
                'pago_id' => $pago->id,
                'metodo'  => 'saldo_favor',
                'monto'   => $aplicar,
            ]);

            DetallePagoFactura::create([
                'pago_id'        => $pago->id,
                'venta_id'       => $venta->id,
                'monto_aplicado' => $aplicar,
            ]);

            // Actualizar venta (bcmath)
            $nuevoPagado = (float) bcadd((string) $venta->pagado, (string) $aplicar, 2);
            $venta->update(['pagado' => $nuevoPagado, 'metodo_pago' => $venta->metodo_pago]);
            $venta->refresh();
            $venta->recalcularEstado();

            // Actualizar saldo a favor (bcmath)
            $nuevoDisponible = max(0.0, (float) bcsub((string) $disponible, (string) $aplicar, 2));
            $nuevoEstado     = round($nuevoDisponible, 2) <= 0 ? 'usado' : 'parcialmente_usado';
            $saldo->update([
                'monto_disponible' => $nuevoDisponible,
                'estado'           => $nuevoEstado,
            ]);

            // ── Movimiento CONTABLE — solo trazabilidad, NO afecta balance ──
            // El dinero ya fue registrado como ingreso cuando se recibió el pago
            // original que generó el excedente. Aplicar el saldo es una
            // transferencia contable (deuda → saldada), no un nuevo ingreso de caja.
            $docStr = trim(ucfirst($venta->documento_tipo ?? '') . ' ' . ($venta->documento_numero ?? ''));
            Movimiento::create([
                'tipo'             => 'contable',
                'subtipo'          => 'saldo_favor_usado',
                'origen'           => 'auto',
                'estado'           => 'activo',
                'empresa'          => $empresa,
                'categoria'        => 'Saldo a favor aplicado',
                'metodo_pago'      => 'saldo_favor',
                'referencia_tipo'  => 'saldo_favor',
                'referencia_id'    => $saldo->id,
                'cliente_id'       => $venta->cliente_id,
                'user_id'          => $userId,
                'documento_tipo'   => $venta->documento_tipo,
                'documento_numero' => $venta->documento_numero ?? (string) $venta->id,
                'monto'            => $aplicar,
                'fecha'            => now()->toDateString(),
                'observaciones'    => "SF#{$saldo->id} aplicado a venta #{$venta->id}" . ($docStr ? " ({$docStr})" : ''),
            ]);

            $venta->refresh();
            return [
                'aplicado'       => $aplicar,
                'saldo_restante' => $nuevoDisponible,
                'estado_venta'   => $venta->estado,
                'saldo_estado'   => $nuevoEstado,
            ];
        });
    }

    /**
     * Saldos a favor disponibles de un cliente (colección con pago precargado).
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
     * Ventas pendientes o parciales de un cliente con saldo calculado.
     */
    public function ventasPendientesCliente(int $clienteId): \Illuminate\Support\Collection
    {
        return Venta::where('cliente_id', $clienteId)
            ->whereIn('estado', ['pendiente', 'parcial'])
            ->select('id', 'fecha', 'total', 'pagado', 'documento_tipo', 'documento_numero', 'estado')
            ->orderBy('fecha', 'asc')
            ->get()
            ->map(function ($v) {
                $v->saldo_pendiente = (float) bcsub(
                    (string) $v->total,
                    (string) $v->pagado,
                    2
                );
                return $v;
            });
    }
}
