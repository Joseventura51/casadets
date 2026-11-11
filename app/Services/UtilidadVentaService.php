<?php

namespace App\Services;

use App\Models\Venta;
use App\Support\DecimalMoney;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shared source for sale profitability and commission-potential reporting.
 * Historical cost comes from compra_venta_detalle, never the current catalog.
 */
class UtilidadVentaService
{
    /**
     * Loads summaries in bounded batches for reports covering large histories.
     *
     * @param Collection<int, int> $ventaIds
     * @return array<int, array<string, mixed>> keyed by sale id
     */
    public function resumenesPorIds(Collection $ventaIds): array
    {
        if ($ventaIds->isEmpty()) {
            return [];
        }

        $result = [];
        Venta::query()
            ->whereIn('id', $ventaIds->all())
            ->orderBy('id')
            ->chunkById(200, function ($ventas) use (&$result): void {
                $result += $this->resumenes($ventas);
            });

        return $result;
    }

    /**
     * Shared financial totals for reports and weekly closing.
     */
    public function agregados(Collection $ventaIds): array
    {
        $totales = [
            'ingreso_neto' => 0,
            'costo_historico' => 0,
            'utilidad_economica' => 0,
            'comision_potencial' => 0,
            'ventas_costeadas' => 0,
            'por_fecha' => [],
        ];

        if ($ventaIds->isEmpty()) {
            return [
                'ingreso_neto' => '0.00',
                'costo_historico' => '0.00',
                'utilidad_economica' => '0.00',
                'comision_potencial' => '0.00',
                'ventas_costeadas' => 0,
                'por_fecha' => [],
            ];
        }

        Venta::query()
            ->whereIn('id', $ventaIds->all())
            ->orderBy('id')
            ->chunkById(200, function ($ventas) use (&$totales): void {
                foreach ($this->resumenes($ventas) as $resumen) {
                    if ($resumen['utilidad_economica'] === null) {
                        continue;
                    }

                    $ingreso = DecimalMoney::cents($resumen['ingreso_neto']);
                    $costo = DecimalMoney::cents($resumen['costo_historico']);
                    $utilidad = DecimalMoney::cents($resumen['utilidad_economica']);
                    $comision = DecimalMoney::cents($resumen['comision_potencial'] ?? 0);
                    $fecha = Carbon::parse($resumen['fecha'])->toDateString();

                    $totales['ingreso_neto'] += $ingreso;
                    $totales['costo_historico'] += $costo;
                    $totales['utilidad_economica'] += $utilidad;
                    $totales['comision_potencial'] += $comision;
                    $totales['ventas_costeadas']++;
                    $totales['por_fecha'][$fecha] ??= [
                        'fecha' => $fecha,
                        'cantidad' => 0,
                        'ingreso_neto' => 0,
                        'costo_historico' => 0,
                        'utilidad_economica' => 0,
                    ];
                    $totales['por_fecha'][$fecha]['cantidad']++;
                    $totales['por_fecha'][$fecha]['ingreso_neto'] += $ingreso;
                    $totales['por_fecha'][$fecha]['costo_historico'] += $costo;
                    $totales['por_fecha'][$fecha]['utilidad_economica'] += $utilidad;
                }
            });

        foreach (['ingreso_neto', 'costo_historico', 'utilidad_economica', 'comision_potencial'] as $key) {
            $totales[$key] = DecimalMoney::formatCents($totales[$key]);
        }
        foreach ($totales['por_fecha'] as &$fecha) {
            foreach (['ingreso_neto', 'costo_historico', 'utilidad_economica'] as $key) {
                $fecha[$key] = DecimalMoney::formatCents($fecha[$key]);
            }
        }
        unset($fecha);

        return $totales;
    }

    /**
     * @param Collection<int, Venta> $ventas
     * @return array<int, array<string, mixed>> keyed by sale id
     */
    public function resumenes(Collection $ventas): array
    {
        if ($ventas->isEmpty()) {
            return [];
        }

        $salesById = $ventas->keyBy('id');
        $saleIds = $salesById->keys()->all();
        $detalles = DB::table('venta_detalles')
            ->whereIn('venta_id', $saleIds)
            ->orderBy('id')
            ->get()
            ->groupBy('venta_id');
        $detailIds = $detalles->flatten(1)->pluck('id')->all();

        $returned = empty($detailIds) ? collect() : DB::table('devolucion_detalles as dd')
            ->join('devoluciones as d', 'd.id', '=', 'dd.devolucion_id')
            ->whereIn('dd.venta_detalle_id', $detailIds)
            ->selectRaw('dd.venta_detalle_id, SUM(dd.cantidad_devuelta) as cantidad')
            ->groupBy('dd.venta_detalle_id')
            ->get()
            ->keyBy('venta_detalle_id');

        $dispatched = empty($detailIds) ? collect() : DB::table('despacho_detalles as dd')
            ->join('despachos as d', 'd.id', '=', 'dd.despacho_id')
            ->whereIn('dd.venta_detalle_id', $detailIds)
            ->selectRaw('dd.venta_detalle_id, SUM(dd.cantidad) as cantidad')
            ->groupBy('dd.venta_detalle_id')
            ->get()
            ->keyBy('venta_detalle_id');

        $costos = empty($detailIds) ? collect() : DB::table('compra_venta_detalle')
            ->whereIn('venta_detalle_id', $detailIds)
            ->orderBy('id')
            ->get()
            ->groupBy('venta_detalle_id');
        $costIds = $costos->flatten(1)->pluck('id')->all();

        $regularizaciones = empty($costIds) ? collect() : DB::table('regularizaciones_costo')
            ->whereIn('compra_venta_detalle_id', $costIds)
            ->orderBy('id')
            ->get()
            ->groupBy('compra_venta_detalle_id');

        $ledgerSums = DB::table('comision_movimientos')
            ->whereIn('venta_id', $saleIds)
            ->selectRaw('venta_id, SUM(importe) as saldo')
            ->groupBy('venta_id')
            ->get()
            ->keyBy('venta_id');

        $result = [];
        foreach ($salesById as $saleId => $venta) {
            $lineSummaries = [];
            $costCents = 0;
            $profitKnownCents = 0;
            $saleDetails = $detalles->get($saleId, collect());
            $allCosted = $saleDetails->isNotEmpty();
            $hasAmbiguousReturn = false;
            $quantitySold = 0;
            $quantityReturned = 0;
            $quantityDispatched = 0;

            foreach ($saleDetails as $detalle) {
                $soldQty = DecimalMoney::quantityHundredths($detalle->cantidad);
                $returnedQty = DecimalMoney::quantityHundredths($returned->get($detalle->id)?->cantidad ?? 0);
                $deliveredQty = DecimalMoney::quantityHundredths($dispatched->get($detalle->id)?->cantidad ?? 0);
                $netQty = max(0, $soldQty - $returnedQty);
                $quantitySold += $soldQty;
                $quantityReturned += $returnedQty;
                $quantityDispatched += $deliveredQty;

                $sources = [];
                $costedQty = 0;
                $lineCostCents = 0;
                foreach ($costos->get($detalle->id, collect()) as $allocation) {
                    $effectiveCost = $this->effectiveCost(
                        $allocation->costo_unitario,
                        $regularizaciones->get($allocation->id, collect())
                    );
                    $allocationQty = DecimalMoney::quantityHundredths($allocation->cantidad);
                    $sources[] = [
                        'cantidad' => $allocationQty,
                        'costo' => $effectiveCost,
                    ];
                    if ($effectiveCost !== null && $allocationQty > 0) {
                        $costedQty += $allocationQty;
                        $lineCostCents += DecimalMoney::multiplyCostByQuantity(
                            DecimalMoney::costUnits($effectiveCost),
                            $allocationQty
                        );
                    }
                }

                $costState = 'sin_costear';
                $lineProfitCents = 0;
                if ($returnedQty > 0 && $netQty > 0) {
                    $knownCosts = collect($sources)
                        ->filter(fn ($source) => $source['costo'] !== null)
                        ->pluck('costo')
                        ->unique()
                        ->values();
                    if ($knownCosts->count() > 1) {
                        $hasAmbiguousReturn = true;
                        $costState = 'ambiguo_devolucion';
                    } elseif ($costedQty >= $soldQty && $knownCosts->count() === 1) {
                        $singleCost = DecimalMoney::costUnits($knownCosts->first());
                        $lineCostCents = DecimalMoney::multiplyCostByQuantity($singleCost, $netQty);
                        $costedQty = $netQty;
                        $costState = 'costeada';
                    } else {
                        $allCosted = false;
                        $costState = $costedQty > 0 ? 'parcial' : 'sin_costear';
                        if ($knownCosts->count() === 1) {
                            $costedQty = min($costedQty, $netQty);
                            $lineCostCents = DecimalMoney::multiplyCostByQuantity(
                                DecimalMoney::costUnits($knownCosts->first()),
                                $costedQty
                            );
                        } else {
                            $hasAmbiguousReturn = true;
                        }
                    }
                } elseif ($costedQty >= $netQty && $netQty > 0) {
                    $costState = 'costeada';
                } elseif ($costedQty > 0) {
                    $allCosted = false;
                    $costState = 'parcial';
                } else {
                    $allCosted = false;
                }

                if ($costedQty > $netQty && $returnedQty === 0) {
                    $costState = 'inconsistente';
                    $allCosted = false;
                }

                $usableQty = min($netQty, $costedQty);
                if ($costState === 'costeada' && $netQty > 0) {
                    $usableQty = $netQty;
                    $costCents += $lineCostCents;
                } elseif ($usableQty > 0 && $returnedQty === 0) {
                    $lineCostCents = $this->capCostToQuantity($sources, $usableQty);
                    $costCents += $lineCostCents;
                }

                if ($usableQty > 0 && $costState !== 'ambiguo_devolucion' && $costState !== 'inconsistente') {
                    $lineIncomeCents = DecimalMoney::multiplyCentsByQuantity(
                        DecimalMoney::cents($detalle->precio_unitario),
                        $usableQty
                    );
                    $profitKnownCents += $lineIncomeCents - $lineCostCents;
                    $lineProfitCents = $lineIncomeCents - $lineCostCents;
                }

                if ($costState !== 'costeada') {
                    $allCosted = false;
                }

                $lineSummaries[] = [
                    'venta_detalle_id' => (int) $detalle->id,
                    'producto' => $detalle->producto,
                    'codigo' => $detalle->codigo ?? '',
                    'cantidad_vendida' => DecimalMoney::formatScaled($soldQty, 2),
                    'cantidad_devuelta' => DecimalMoney::formatScaled($returnedQty, 2),
                    'cantidad_neta' => DecimalMoney::formatScaled($netQty, 2),
                    'cantidad_despachada' => DecimalMoney::formatScaled($deliveredQty, 2),
                    'cantidad_pendiente' => DecimalMoney::formatScaled(max(0, $netQty - $deliveredQty), 2),
                    'cantidad_costeada' => DecimalMoney::formatScaled(min($netQty, $costedQty), 2),
                    'estado_costeo' => $costState,
                    'costo_historico' => DecimalMoney::formatCents($lineCostCents),
                    'utilidad_conocida' => DecimalMoney::formatCents($lineProfitCents),
                    'precio_unitario' => (string) $detalle->precio_unitario,
                    'subtotal' => (string) $detalle->subtotal,
                ];
            }

            $esCalculable = !in_array($venta->estado, ['anulado', 'anulado_nc'], true)
                && $venta->documento_tipo !== 'nota_credito'
                && !$venta->es_referencia_fiscal;
            $ingresoCents = $esCalculable
                ? DecimalMoney::cents($venta->total) + DecimalMoney::cents($venta->ajuste)
                : 0;
            $utilidadCents = $esCalculable && $allCosted && !$hasAmbiguousReturn
                ? $ingresoCents - $costCents
                : null;
            $tasa = Carbon::parse($venta->fecha)->isSunday() ? 5000 : 3500;
            $comisionPotencialCents = null;
            if ($utilidadCents !== null) {
                $comisionPotencialCents = DecimalMoney::commissionCents($utilidadCents, $tasa);
            }

            $totalCobrarCents = $ingresoCents;
            $pagadoCents = DecimalMoney::cents($venta->pagado);
            $estadoPago = $totalCobrarCents <= 0 || $pagadoCents >= $totalCobrarCents
                ? 'pagado'
                : ($pagadoCents > 0 ? 'parcial' : 'pendiente');
            $pendienteDespacho = array_sum(array_map(
                fn ($line) => DecimalMoney::quantityHundredths($line['cantidad_pendiente']),
                $lineSummaries
            ));
            $estadoDespacho = $pendienteDespacho <= 0
                ? 'completo'
                : ($quantityDispatched > 0 ? 'parcial' : 'pendiente');
            $saldoLiberado = DecimalMoney::cents($ledgerSums->get($saleId)?->saldo ?? 0);

            $result[$saleId] = [
                'venta_id' => (int) $saleId,
                'fecha' => $venta->fecha,
                'ingreso_neto' => DecimalMoney::formatCents($ingresoCents),
                'costo_historico' => DecimalMoney::formatCents($costCents),
                'utilidad_economica' => $utilidadCents === null ? null : DecimalMoney::formatCents($utilidadCents),
                'utilidad_conocida' => DecimalMoney::formatCents($profitKnownCents),
                'estado_costeo' => $allCosted && !$hasAmbiguousReturn ? 'costeada' : 'incompleto',
                'estado_pago' => $estadoPago,
                'estado_despacho' => $estadoDespacho,
                'comision_porcentaje' => $tasa === 5000 ? '50.00' : '35.00',
                'comision_potencial' => !$esCalculable || $comisionPotencialCents === null
                    ? null
                    : DecimalMoney::formatCents($comisionPotencialCents),
                'comision_liberada' => DecimalMoney::formatCents($saldoLiberado),
                'comision_pendiente' => !$esCalculable || $comisionPotencialCents === null
                    ? null
                    : DecimalMoney::formatCents($comisionPotencialCents - $saldoLiberado),
                'comision_conocida_parcial' => $esCalculable && !$allCosted
                    ? DecimalMoney::formatCents(DecimalMoney::commissionCents($profitKnownCents, $tasa))
                    : null,
                'cantidad_vendida' => DecimalMoney::formatScaled($quantitySold, 2),
                'cantidad_devuelta' => DecimalMoney::formatScaled($quantityReturned, 2),
                'cantidad_despachada' => DecimalMoney::formatScaled($quantityDispatched, 2),
                'detalles' => $lineSummaries,
            ];
        }

        return $result;
    }

    public function resumen(Venta $venta): array
    {
        $venta->loadMissing('detalles');
        return $this->resumenes(collect([$venta]))[$venta->id];
    }

    public function costoActual(int $asignacionId, mixed $costoBase): ?string
    {
        $ultimo = DB::table('regularizaciones_costo')
            ->where('compra_venta_detalle_id', $asignacionId)
            ->orderByDesc('id')
            ->value('costo_nuevo');
        return $ultimo !== null ? (string) $ultimo : ($costoBase === null ? null : (string) $costoBase);
    }

    private function effectiveCost(mixed $base, Collection $regularizaciones): ?string
    {
        if ($regularizaciones->isNotEmpty()) {
            return (string) $regularizaciones->last()->costo_nuevo;
        }
        return $base === null ? null : (string) $base;
    }

    private function capCostToQuantity(array $sources, int $quantityHundredths): int
    {
        $remaining = $quantityHundredths;
        $total = 0;
        foreach ($sources as $source) {
            if ($source['costo'] === null || $remaining <= 0) {
                continue;
            }
            $take = min($remaining, $source['cantidad']);
            $total += DecimalMoney::multiplyCostByQuantity(
                DecimalMoney::costUnits($source['costo']),
                $take
            );
            $remaining -= $take;
        }
        return $total;
    }
}
