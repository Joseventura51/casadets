<?php

namespace App\Services;

use App\Models\Despacho;
use App\Models\DespachoDetalle;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Support\DecimalMoney;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DespachoService
{
    public function registrar(
        Venta $venta,
        array $cantidades,
        array $costos,
        string $idempotencyKey,
        ?string $observaciones
    ): Despacho {
        return DB::transaction(function () use (
            $venta,
            $cantidades,
            $costos,
            $idempotencyKey,
            $observaciones
        ): Despacho {
            $venta = Venta::query()->whereKey($venta->id)->lockForUpdate()->firstOrFail();

            $existente = Despacho::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existente) {
                if ($existente->venta_id !== $venta->id) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'La clave de operación ya se utilizó en otra venta.',
                    ]);
                }
                return $existente;
            }

            if (
                in_array($venta->estado, ['anulado', 'anulado_nc'], true)
                || $venta->documento_tipo === 'nota_credito'
                || $venta->es_referencia_fiscal
            ) {
                throw ValidationException::withMessages([
                    'venta' => 'No se pueden despachar ventas anuladas, notas de crédito o referencias fiscales.',
                ]);
            }

            $detalles = VentaDetalle::query()
                ->where('venta_id', $venta->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $devoluciones = DB::table('devolucion_detalles as dd')
                ->join('devoluciones as d', 'd.id', '=', 'dd.devolucion_id')
                ->where('d.venta_id', $venta->id)
                ->selectRaw('dd.venta_detalle_id, SUM(dd.cantidad_devuelta) as cantidad')
                ->groupBy('dd.venta_detalle_id')
                ->pluck('cantidad', 'venta_detalle_id');
            $yaDespachado = DB::table('despacho_detalles as dd')
                ->join('despachos as d', 'd.id', '=', 'dd.despacho_id')
                ->where('d.venta_id', $venta->id)
                ->selectRaw('dd.venta_detalle_id, SUM(dd.cantidad) as cantidad')
                ->groupBy('dd.venta_detalle_id')
                ->pluck('cantidad', 'venta_detalle_id');

            $lineas = [];
            foreach ($cantidades as $detalleId => $rawCantidad) {
                $cantidad = DecimalMoney::quantityHundredths($rawCantidad);
                if ($cantidad < 0) {
                    throw ValidationException::withMessages([
                        "cantidades.{$detalleId}" => 'La cantidad no puede ser negativa.',
                    ]);
                }
                if ($cantidad === 0) {
                    continue;
                }
                $detalle = $detalles->get((int) $detalleId);
                if (!$detalle) {
                    throw ValidationException::withMessages([
                        "cantidades.{$detalleId}" => 'El producto no pertenece a esta venta.',
                    ]);
                }

                $vendido = DecimalMoney::quantityHundredths($detalle->cantidad);
                $devuelto = DecimalMoney::quantityHundredths($devoluciones->get($detalle->id, 0));
                $entregado = DecimalMoney::quantityHundredths($yaDespachado->get($detalle->id, 0));
                $pendiente = max(0, $vendido - $devuelto - $entregado);
                if ($cantidad > $pendiente) {
                    throw ValidationException::withMessages([
                        "cantidades.{$detalleId}" => 'La cantidad supera el saldo pendiente de despacho.',
                    ]);
                }
                $lineas[$detalle->id] = $cantidad;
            }

            if (!$lineas) {
                throw ValidationException::withMessages([
                    'cantidades' => 'Indica al menos una cantidad mayor que cero.',
                ]);
            }

            $despacho = Despacho::create([
                'venta_id' => $venta->id,
                'user_id' => auth()->id(),
                'idempotency_key' => $idempotencyKey,
                'fecha_hora' => now(),
                'observaciones' => $observaciones,
            ]);

            $asignacionesBloqueadas = [];
            foreach ($lineas as $detalleId => $cantidadHundredths) {
                $detalle = $detalles->get($detalleId);
                $despachoDetalle = DespachoDetalle::create([
                    'despacho_id' => $despacho->id,
                    'venta_detalle_id' => $detalleId,
                    'cantidad' => DecimalMoney::formatScaled($cantidadHundredths, 2),
                ]);

                $seleccion = $costos[$detalleId] ?? [];
                $cantidadCosteada = 0;
                foreach ($seleccion as $asignacionId => $rawCantidadCosto) {
                    $cantidadCosto = DecimalMoney::quantityHundredths($rawCantidadCosto);
                    if ($cantidadCosto < 0) {
                        throw ValidationException::withMessages([
                            "costos.{$detalleId}.{$asignacionId}" => 'La cantidad costeada no puede ser negativa.',
                        ]);
                    }
                    if ($cantidadCosto === 0) {
                        continue;
                    }

                    $asignacion = DB::table('compra_venta_detalle')
                        ->where('id', $asignacionId)
                        ->where('venta_detalle_id', $detalleId)
                        ->lockForUpdate()
                        ->first();
                    if (!$asignacion) {
                        throw ValidationException::withMessages([
                            "costos.{$detalleId}.{$asignacionId}" => 'La asignación de costo no corresponde a este producto.',
                        ]);
                    }
                    $asignacionesBloqueadas[$asignacionId] = $asignacion;

                    $costoActual = app(UtilidadVentaService::class)
                        ->costoActual((int) $asignacionId, $asignacion->costo_unitario);
                    if ($costoActual === null) {
                        throw ValidationException::withMessages([
                            "costos.{$detalleId}.{$asignacionId}" => 'No se puede vincular una asignación sin costo explícito.',
                        ]);
                    }

                    $yaUsado = DB::table('despacho_detalle_costos')
                        ->where('compra_venta_detalle_id', $asignacionId)
                        ->sum('cantidad');
                    $disponible = DecimalMoney::quantityHundredths($asignacion->cantidad)
                        - DecimalMoney::quantityHundredths($yaUsado);
                    if ($cantidadCosto > $disponible) {
                        throw ValidationException::withMessages([
                            "costos.{$detalleId}.{$asignacionId}" => 'La cantidad supera las unidades disponibles de esta asignación de costo.',
                        ]);
                    }

                    $cantidadCosteada += $cantidadCosto;
                    DB::table('despacho_detalle_costos')->insert([
                        'despacho_detalle_id' => $despachoDetalle->id,
                        'compra_venta_detalle_id' => $asignacionId,
                        'cantidad' => DecimalMoney::formatScaled($cantidadCosto, 2),
                        'created_at' => now(),
                    ]);
                }

                if ($cantidadCosteada > $cantidadHundredths) {
                    throw ValidationException::withMessages([
                        "costos.{$detalleId}" => 'Las unidades asignadas a costos no pueden superar la cantidad despachada.',
                    ]);
                }
            }

            // No stock movement is created here: stock was already reduced by the sale.
            app(ComisionLedgerService::class)->sincronizarVenta($venta->id);

            return $despacho->load(['detalles.ventaDetalle', 'user']);
        }, 3);
    }

    /**
     * Builds the sale lines shown in dispatch forms and history.
     */
    public function detalleVenta(Venta $venta): array
    {
        $venta->loadMissing('detalles');
        $detailIds = $venta->detalles->pluck('id')->all();
        if (!$detailIds) {
            return [];
        }

        $devueltas = DB::table('devolucion_detalles as dd')
            ->join('devoluciones as d', 'd.id', '=', 'dd.devolucion_id')
            ->where('d.venta_id', $venta->id)
            ->whereIn('dd.venta_detalle_id', $detailIds)
            ->selectRaw('dd.venta_detalle_id, SUM(dd.cantidad_devuelta) as cantidad')
            ->groupBy('dd.venta_detalle_id')
            ->pluck('cantidad', 'venta_detalle_id');

        $despachadas = DB::table('despacho_detalles as dd')
            ->join('despachos as d', 'd.id', '=', 'dd.despacho_id')
            ->where('d.venta_id', $venta->id)
            ->whereIn('dd.venta_detalle_id', $detailIds)
            ->selectRaw('dd.venta_detalle_id, SUM(dd.cantidad) as cantidad')
            ->groupBy('dd.venta_detalle_id')
            ->pluck('cantidad', 'venta_detalle_id');

        $historial = DB::table('despacho_detalles as dd')
            ->join('despachos as d', 'd.id', '=', 'dd.despacho_id')
            ->leftJoin('users as u', 'u.id', '=', 'd.user_id')
            ->where('d.venta_id', $venta->id)
            ->whereIn('dd.venta_detalle_id', $detailIds)
            ->orderByDesc('d.fecha_hora')
            ->select([
                'd.id as despacho_id',
                'd.fecha_hora',
                'd.observaciones as observaciones_despacho',
                'dd.venta_detalle_id',
                'dd.cantidad',
                'u.name as responsable',
            ])
            ->get()
            ->groupBy('venta_detalle_id');

        $asignaciones = DB::table('compra_venta_detalle')
            ->whereIn('venta_detalle_id', $detailIds)
            ->orderBy('id')
            ->get()
            ->groupBy('venta_detalle_id');
        $asignacionIds = $asignaciones->flatten(1)->pluck('id')->all();
        $usadoPorCosto = empty($asignacionIds) ? collect() : DB::table('despacho_detalle_costos')
            ->whereIn('compra_venta_detalle_id', $asignacionIds)
            ->selectRaw('compra_venta_detalle_id, SUM(cantidad) as cantidad')
            ->groupBy('compra_venta_detalle_id')
            ->pluck('cantidad', 'compra_venta_detalle_id');
        $regularizaciones = empty($asignacionIds) ? collect() : DB::table('regularizaciones_costo')
            ->whereIn('compra_venta_detalle_id', $asignacionIds)
            ->orderBy('id')
            ->get()
            ->groupBy('compra_venta_detalle_id');

        $rows = [];
        foreach ($venta->detalles as $detalle) {
            $vendido = DecimalMoney::quantityHundredths($detalle->cantidad);
            $devuelto = DecimalMoney::quantityHundredths($devueltas->get($detalle->id, 0));
            $despachado = DecimalMoney::quantityHundredths($despachadas->get($detalle->id, 0));
            $asignacionRows = [];
            foreach ($asignaciones->get($detalle->id, collect()) as $asignacion) {
                $latest = $regularizaciones->get($asignacion->id, collect())->last();
                $costo = $latest?->costo_nuevo ?? $asignacion->costo_unitario;
                $asignacionRows[] = [
                    'id' => (int) $asignacion->id,
                    'cantidad' => (string) $asignacion->cantidad,
                    'cantidad_disponible' => DecimalMoney::formatScaled(
                        max(
                            0,
                            DecimalMoney::quantityHundredths($asignacion->cantidad)
                                - DecimalMoney::quantityHundredths($usadoPorCosto->get($asignacion->id, 0))
                        ),
                        2
                    ),
                    'costo_unitario' => $costo === null ? null : (string) $costo,
                ];
            }
            $rows[] = [
                'detalle' => $detalle,
                'cantidad_vendida' => DecimalMoney::formatScaled($vendido, 2),
                'cantidad_devuelta' => DecimalMoney::formatScaled($devuelto, 2),
                'cantidad_despachada' => DecimalMoney::formatScaled($despachado, 2),
                'cantidad_pendiente' => DecimalMoney::formatScaled(max(0, $vendido - $devuelto - $despachado), 2),
                'asignaciones_costo' => $asignacionRows,
                'historial' => $historial->get($detalle->id, collect()),
            ];
        }
        return $rows;
    }
}
