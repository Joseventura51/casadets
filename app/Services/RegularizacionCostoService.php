<?php

namespace App\Services;

use App\Models\CompraVentaDetalle;
use App\Models\RegularizacionCosto;
use App\Models\Venta;
use App\Support\DecimalMoney;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegularizacionCostoService
{
    public function regularizar(
        int $asignacionId,
        string $costoNuevo,
        string $idempotencyKey,
        ?string $referencia,
        ?string $motivo
    ): RegularizacionCosto {
        if (!preg_match('/^\d{1,8}(?:\.\d{1,4})?$/', $costoNuevo)) {
            throw ValidationException::withMessages([
                'costo_nuevo' => 'El costo debe ser un importe no negativo con hasta cuatro decimales.',
            ]);
        }

        $ventaId = CompraVentaDetalle::query()
            ->whereKey($asignacionId)
            ->join('venta_detalles', 'venta_detalles.id', '=', 'compra_venta_detalle.venta_detalle_id')
            ->value('venta_detalles.venta_id');

        return DB::transaction(function () use (
            $asignacionId,
            $ventaId,
            $costoNuevo,
            $idempotencyKey,
            $referencia,
            $motivo
        ): RegularizacionCosto {
            $venta = $ventaId ? Venta::query()->whereKey($ventaId)->lockForUpdate()->first() : null;
            $existente = RegularizacionCosto::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existente) {
                if ($existente->compra_venta_detalle_id !== $asignacionId) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'La clave de operación ya se utilizó para otra asignación.',
                    ]);
                }
                return $existente;
            }

            $asignacion = CompraVentaDetalle::query()
                ->whereKey($asignacionId)
                ->lockForUpdate()
                ->firstOrFail();
            $anterior = $asignacion->regularizaciones()->orderByDesc('id')->value('costo_nuevo')
                ?? $asignacion->costo_unitario;
            $nuevoEscalado = DecimalMoney::costUnits($costoNuevo);
            $anteriorEscalado = $anterior === null ? null : DecimalMoney::costUnits($anterior);
            $diferenciaUnit = $anteriorEscalado === null
                ? null
                : DecimalMoney::formatScaled($nuevoEscalado - $anteriorEscalado, 4);

            $registro = RegularizacionCosto::create([
                'compra_venta_detalle_id' => $asignacion->id,
                'user_id' => auth()->id(),
                'idempotency_key' => $idempotencyKey,
                'costo_anterior' => $anterior,
                'costo_nuevo' => DecimalMoney::formatScaled($nuevoEscalado, 4),
                'diferencia_unitaria' => $diferenciaUnit,
                'referencia' => $referencia,
                'motivo' => $motivo,
                'created_at' => now(),
            ]);

            app(ComisionLedgerService::class)->registrarAjusteRegularizacion($registro);
            if ($venta) {
                app(ComisionLedgerService::class)->sincronizarVenta($venta);
            }

            return $registro;
        }, 3);
    }
}
