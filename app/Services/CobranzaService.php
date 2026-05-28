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
     * Registra un pago para UNA venta y genera el movimiento en el ledger.
     *
     * @param  Venta        $venta
     * @param  array        $pagosInput   [['metodo'=>'efectivo','monto'=>100,'descripcion'=>'BCP'], ...]
     * @param  string|null  $estadoManual
     * @param  int|null     $userId
     * @param  string       $empresa
     */
    public function registrarPago(
        Venta $venta,
        array $pagosInput,
        ?string $estadoManual = null,
        ?int $userId = null,
        string $empresa = 'casadets'
    ): array {
        return DB::transaction(function () use ($venta, $pagosInput, $estadoManual, $userId, $empresa) {

            $pagosReales = collect($pagosInput)
                ->filter(fn ($p) => ($p['metodo'] ?? 'ninguno') !== 'ninguno' && ($p['monto'] ?? 0) > 0)
                ->map(fn ($p) => [
                    'metodo'      => $p['metodo'],
                    'monto'       => round((float) $p['monto'], 2),
                    'descripcion' => trim($p['descripcion'] ?? ''),
                ]);

            $montoNuevo = (float) $pagosReales->reduce(
                fn ($carry, $p) => bcadd($carry, (string) $p['monto'], 2),
                '0'
            );

            $metodoStr = $pagosReales->pluck('metodo')->unique()->implode(',') ?: null;

            if ($montoNuevo <= 0) {
                if ($estadoManual && $estadoManual !== 'ninguno') {
                    $venta->update(['estado' => $estadoManual]);
                }
                return [
                    'pago'            => null,
                    'estado'          => $venta->fresh()->estado,
                    'saldo_favor'     => 0.0,
                    'saldo_pendiente' => max(0, (float) bcsub((string) $venta->total, (string) $venta->pagado, 2)),
                ];
            }

            $pago = Pago::create([
                'cliente_id'  => $venta->cliente_id,
                'user_id'     => $userId,
                'monto_total' => $montoNuevo,
                'metodo_pago' => $metodoStr,
                'estado'      => 'aplicado',
                'fecha'       => now()->toDateString(),
            ]);

            foreach ($pagosReales as $p) {
                PagoMetodo::create([
                    'pago_id'     => $pago->id,
                    'metodo'      => $p['metodo'],
                    'descripcion' => $p['descripcion'] ?: null,
                    'monto'       => $p['monto'],
                ]);
            }

            $totalDeuda    = (float) $venta->total;
            $yaPagado      = (float) $venta->pagado;
            $saldoDeuda    = (float) max('0', bcsub((string) $totalDeuda, (string) $yaPagado, 2));
            $ventaYaPagada = $venta->estado === 'pagado';

            if ($ventaYaPagada) {
                $montoAplicado = 0.0;
                $excedente     = $montoNuevo;
            } else {
                $montoAplicado = $montoNuevo <= $saldoDeuda ? $montoNuevo : $saldoDeuda;
                $excedente     = (float) bcsub((string) $montoNuevo, (string) $montoAplicado, 2);
            }

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

            $hayExcedente = $excedente > 0;

            if ($hayExcedente && $venta->cliente_id) {
                $docStr      = trim(($venta->documento_tipo ?? '') . ' ' . ($venta->documento_numero ?? ''));
                $descripcion = $ventaYaPagada
                    ? "Excedente sobre venta ya cobrada — {$docStr}"
                    : "Excedente de pago — {$docStr}";

                SaldoFavor::create([
                    'cliente_id'       => $venta->cliente_id,
                    'pago_id'          => $pago->id,
                    'venta_origen_id'  => $venta->id,
                    'monto_original'   => $excedente,
                    'monto_disponible' => $excedente,
                    'estado'           => 'disponible',
                    'descripcion'      => $descripcion,
                    'fecha'            => now()->toDateString(),
                ]);

                $pago->update(['estado' => $montoAplicado > 0 ? 'parcial' : 'saldo_favor']);
            }

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
                'saldo_pendiente' => max(0, (float) bcsub((string) $venta->total, (string) $venta->pagado, 2)),
            ];
        });
    }

    /**
     * Registra un pago para MÚLTIPLES ventas en una sola operación.
     * El pago se aplica FIFO (más antiguas primero) hasta agotar el monto.
     *
     * @param  int[]    $ventaIds
     * @param  array    $pagosInput  [['metodo'=>'...','monto'=>...,'descripcion'=>'...'], ...]
     * @param  int|null $userId
     * @param  string   $empresa
     */
    public function registrarPagoMultiple(
        array $ventaIds,
        array $pagosInput,
        ?int $userId = null,
        string $empresa = 'casadets'
    ): array {
        return DB::transaction(function () use ($ventaIds, $pagosInput, $userId, $empresa) {

            // Cargar ventas pendientes/parciales en orden FIFO
            $ventas = Venta::whereIn('id', $ventaIds)
                ->whereIn('estado', ['pendiente', 'parcial'])
                ->orderBy('fecha', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            if ($ventas->isEmpty()) {
                throw new \InvalidArgumentException('No hay ventas pendientes válidas seleccionadas.');
            }

            // Filtrar métodos reales con monto > 0
            $pagosReales = collect($pagosInput)
                ->filter(fn ($p) => ($p['metodo'] ?? 'ninguno') !== 'ninguno' && ($p['monto'] ?? 0) > 0)
                ->map(fn ($p) => [
                    'metodo'      => $p['metodo'],
                    'monto'       => round((float) $p['monto'], 2),
                    'descripcion' => trim($p['descripcion'] ?? ''),
                ]);

            $montoTotal = (float) $pagosReales->reduce(
                fn ($carry, $p) => bcadd($carry, (string) $p['monto'], 2),
                '0'
            );

            if ($montoTotal <= 0) {
                throw new \InvalidArgumentException('El monto total del pago debe ser mayor a cero.');
            }

            $metodoStr = $pagosReales->pluck('metodo')->unique()->implode(',') ?: null;

            // Tomar el cliente de la primera venta (pagos múltiples siempre del mismo cliente)
            $clienteId = $ventas->first()?->cliente_id;

            // Crear un único Pago para todas las ventas
            $pago = Pago::create([
                'cliente_id'  => $clienteId,
                'user_id'     => $userId,
                'monto_total' => $montoTotal,
                'metodo_pago' => $metodoStr,
                'estado'      => 'aplicado',
                'fecha'       => now()->toDateString(),
            ]);

            foreach ($pagosReales as $p) {
                PagoMetodo::create([
                    'pago_id'     => $pago->id,
                    'metodo'      => $p['metodo'],
                    'descripcion' => $p['descripcion'] ?: null,
                    'monto'       => $p['monto'],
                ]);
            }

            // Distribuir el pago FIFO entre las ventas
            $restante         = $montoTotal;
            $ventasActualizadas = [];
            $totalAplicado    = 0.0;

            foreach ($ventas as $venta) {
                if ($restante <= 0) break;

                $saldoVenta = max(0.0, (float) bcsub((string) $venta->total, (string) $venta->pagado, 2));
                if ($saldoVenta <= 0) continue;

                $aplicar  = min($restante, $saldoVenta);
                $aplicar  = round($aplicar, 2);
                $restante = round((float) bcsub((string) $restante, (string) $aplicar, 2), 2);

                DetallePagoFactura::create([
                    'pago_id'        => $pago->id,
                    'venta_id'       => $venta->id,
                    'monto_aplicado' => $aplicar,
                ]);

                $nuevoPagado = (float) bcadd((string) $venta->pagado, (string) $aplicar, 2);
                $venta->update(['pagado' => $nuevoPagado, 'metodo_pago' => $metodoStr]);
                $venta->refresh();
                $venta->recalcularEstado();

                $totalAplicado += $aplicar;
                $ventasActualizadas[] = [
                    'id'             => $venta->id,
                    'documento'      => trim(ucfirst($venta->documento_tipo ?? '') . ' ' . ($venta->documento_numero ?? '')),
                    'aplicado'       => $aplicar,
                    'estado'         => $venta->fresh()->estado,
                ];
            }

            // Movimiento consolidado en el ledger
            $docList = collect($ventasActualizadas)->pluck('documento')->filter()->implode(', ');
            Movimiento::create([
                'tipo'             => 'ingreso',
                'subtipo'          => 'pago_venta',
                'origen'           => 'auto',
                'estado'           => 'activo',
                'empresa'          => $empresa,
                'categoria'        => 'Cobro múltiple de ventas',
                'metodo_pago'      => $metodoStr,
                'referencia_tipo'  => 'pago',
                'referencia_id'    => $pago->id,
                'cliente_id'       => $clienteId,
                'user_id'          => $userId,
                'monto'            => $totalAplicado,
                'fecha'            => now()->toDateString(),
                'observaciones'    => 'Pago múltiple (' . count($ventasActualizadas) . ' ventas)' . ($docList ? ": {$docList}" : ''),
            ]);

            return [
                'pago'               => $pago,
                'ventas_actualizadas' => $ventasActualizadas,
                'total_aplicado'     => $totalAplicado,
                'sobrante'           => $restante,
                'ventas_cobradas'    => collect($ventasActualizadas)->where('estado', 'pagado')->count(),
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

            $nuevoPagado = (float) bcadd((string) $venta->pagado, (string) $aplicar, 2);
            $venta->update(['pagado' => $nuevoPagado]);
            $venta->refresh();
            $venta->recalcularEstado();

            $nuevoDisponible = max(0.0, (float) bcsub((string) $disponible, (string) $aplicar, 2));
            $nuevoEstado     = round($nuevoDisponible, 2) <= 0 ? 'usado' : 'parcialmente_usado';
            $saldo->update([
                'monto_disponible' => $nuevoDisponible,
                'estado'           => $nuevoEstado,
            ]);

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
     * Saldos a favor disponibles de un cliente.
     */
    public function saldosDisponibles(int $clienteId): \Illuminate\Support\Collection
    {
        return SaldoFavor::with(['pago', 'ventaOrigen'])
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
                $v->saldo_pendiente = (float) bcsub((string) $v->total, (string) $v->pagado, 2);
                return $v;
            });
    }
}
