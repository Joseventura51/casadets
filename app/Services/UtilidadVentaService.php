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
            'ingreso_costeado' => 0,
            'ingreso_despachado' => 0,
            'costo_costeado' => 0,
            'costo_historico' => 0,
            'utilidad_economica' => 0,
            'utilidad_conocida' => 0,
            'costo_despachado' => 0,
            'utilidad_despachada' => 0,
            'comision_potencial' => 0,
            'comision_despachada_potencial' => 0,
            'comision_liberada' => 0,
            'comision_pendiente' => 0,
            'ventas_costeadas' => 0,
            'ventas' => 0,
            'por_fecha' => [],
        ];

        if ($ventaIds->isEmpty()) {
            return [
                'ingreso_neto' => '0.00',
                'ingreso_costeado' => '0.00',
                'ingreso_despachado' => '0.00',
                'costo_costeado' => '0.00',
                'costo_historico' => '0.00',
                'utilidad_economica' => '0.00',
                'utilidad_conocida' => '0.00',
                'costo_despachado' => '0.00',
                'utilidad_despachada' => '0.00',
                'comision_potencial' => '0.00',
                'comision_despachada_potencial' => '0.00',
                'comision_liberada' => '0.00',
                'comision_pendiente' => '0.00',
                'ventas_costeadas' => 0,
                'ventas' => 0,
                'por_fecha' => [],
            ];
        }

        Venta::query()
            ->whereIn('id', $ventaIds->all())
            ->orderBy('id')
            ->chunkById(200, function ($ventas) use (&$totales): void {
                foreach ($this->resumenes($ventas) as $resumen) {
                    $ingreso = DecimalMoney::cents($resumen['ingreso_neto']);
                    $ingresoCosteado = DecimalMoney::cents($resumen['ingreso_costeado']);
                    $ingresoDespachado = DecimalMoney::cents($resumen['ingreso_despachado']);
                    $costo = DecimalMoney::cents($resumen['costo_historico']);
                    $utilidad = DecimalMoney::cents($resumen['utilidad_economica'] ?? 0);
                    $utilidadConocida = DecimalMoney::cents($resumen['utilidad_conocida']);
                    $costoDespachado = DecimalMoney::cents($resumen['costo_despachado']);
                    $utilidadDespachada = DecimalMoney::cents($resumen['utilidad_despachada']);
                    $comision = DecimalMoney::cents($resumen['comision_potencial'] ?? 0);
                    $comisionDespachada = DecimalMoney::cents($resumen['comision_despachada_potencial'] ?? 0);
                    $comisionLiberada = DecimalMoney::cents($resumen['comision_liberada']);
                    $comisionPendiente = DecimalMoney::cents($resumen['comision_pendiente'] ?? 0);
                    $fecha = Carbon::parse($resumen['fecha'])->toDateString();

                    $totales['ingreso_neto'] += $ingreso;
                    $totales['ingreso_costeado'] += $ingresoCosteado;
                    $totales['ingreso_despachado'] += $ingresoDespachado;
                    $totales['costo_historico'] += $costo;
                    $totales['utilidad_economica'] += $utilidad;
                    $totales['utilidad_conocida'] += $utilidadConocida;
                    $totales['costo_despachado'] += $costoDespachado;
                    $totales['utilidad_despachada'] += $utilidadDespachada;
                    $totales['comision_potencial'] += $comision;
                    $totales['comision_despachada_potencial'] += $comisionDespachada;
                    $totales['comision_liberada'] += $comisionLiberada;
                    $totales['comision_pendiente'] += $comisionPendiente;
                    $totales['ventas']++;
                    if ($resumen['utilidad_economica'] !== null) {
                        $totales['ventas_costeadas']++;
                        $totales['costo_costeado'] += $costo;
                    }
                    $totales['por_fecha'][$fecha] ??= [
                        'fecha' => $fecha,
                        'cantidad' => 0,
                        'ingreso_neto' => 0,
                        'ingreso_costeado' => 0,
                        'ingreso_despachado' => 0,
                        'costo_costeado' => 0,
                        'costo_historico' => 0,
                        'utilidad_economica' => 0,
                        'utilidad_conocida' => 0,
                        'costo_despachado' => 0,
                        'utilidad_despachada' => 0,
                        'comision_liberada' => 0,
                    ];
                    $totales['por_fecha'][$fecha]['cantidad']++;
                    $totales['por_fecha'][$fecha]['ingreso_neto'] += $ingreso;
                    $totales['por_fecha'][$fecha]['ingreso_costeado'] += $ingresoCosteado;
                    $totales['por_fecha'][$fecha]['ingreso_despachado'] += $ingresoDespachado;
                    if ($resumen['utilidad_economica'] !== null) {
                        $totales['por_fecha'][$fecha]['costo_costeado'] += $costo;
                    }
                    $totales['por_fecha'][$fecha]['costo_historico'] += $costo;
                    $totales['por_fecha'][$fecha]['utilidad_economica'] += $utilidad;
                    $totales['por_fecha'][$fecha]['utilidad_conocida'] += $utilidadConocida;
                    $totales['por_fecha'][$fecha]['costo_despachado'] += $costoDespachado;
                    $totales['por_fecha'][$fecha]['utilidad_despachada'] += $utilidadDespachada;
                    $totales['por_fecha'][$fecha]['comision_liberada'] += $comisionLiberada;
                }
            });

        foreach ([
            'ingreso_neto', 'ingreso_costeado', 'ingreso_despachado', 'costo_costeado', 'costo_historico', 'utilidad_economica', 'utilidad_conocida',
            'costo_despachado', 'utilidad_despachada', 'comision_potencial',
            'comision_despachada_potencial', 'comision_liberada', 'comision_pendiente',
        ] as $key) {
            $totales[$key] = DecimalMoney::formatCents($totales[$key]);
        }
        foreach ($totales['por_fecha'] as &$fecha) {
            foreach ([
                'ingreso_neto', 'ingreso_costeado', 'ingreso_despachado', 'costo_costeado', 'costo_historico', 'utilidad_economica', 'utilidad_conocida',
                'costo_despachado', 'utilidad_despachada', 'comision_liberada',
            ] as $key) {
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

        $returnedByCvd = empty($detailIds) ? collect() : DB::table('devolucion_detalle_costos as ddc')
            ->join('devolucion_detalles as dd', 'dd.id', '=', 'ddc.devolucion_detalle_id')
            ->whereIn('dd.venta_detalle_id', $detailIds)
            ->whereNotNull('ddc.compra_venta_detalle_id')
            ->selectRaw('ddc.compra_venta_detalle_id, SUM(ddc.cantidad_devuelta) as cantidad')
            ->groupBy('ddc.compra_venta_detalle_id')
            ->get()
            ->keyBy('compra_venta_detalle_id');
        $returnedMappedByDetail = empty($detailIds) ? collect() : DB::table('devolucion_detalle_costos as ddc')
            ->join('devolucion_detalles as dd', 'dd.id', '=', 'ddc.devolucion_detalle_id')
            ->whereIn('dd.venta_detalle_id', $detailIds)
            ->whereNotNull('ddc.compra_venta_detalle_id')
            ->selectRaw('dd.venta_detalle_id, SUM(ddc.cantidad_devuelta) as cantidad')
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

        $costos = empty($detailIds) ? collect() : DB::table('compra_venta_detalle as cvd')
            ->join('venta_detalles as vd', 'vd.id', '=', 'cvd.venta_detalle_id')
            ->join('ventas as v', 'v.id', '=', 'vd.venta_id')
            ->leftJoin('compras as compra', 'compra.id', '=', 'cvd.compra_id')
            ->leftJoin('cajas as venta_caja', 'venta_caja.id', '=', 'v.caja_id')
            ->leftJoin('cajas as compra_caja', 'compra_caja.id', '=', 'compra.caja_id')
            ->whereIn('cvd.venta_detalle_id', $detailIds)
            ->select([
                'cvd.*',
                'venta_caja.empresa as venta_empresa',
                'compra_caja.empresa as compra_empresa',
            ])
            ->orderBy('cvd.id')
            ->get()
            ->groupBy('venta_detalle_id');
        $costIds = $costos->flatten(1)->pluck('id')->all();

        $regularizaciones = empty($costIds) ? collect() : DB::table('regularizaciones_costo')
            ->whereIn('compra_venta_detalle_id', $costIds)
            ->orderBy('id')
            ->get()
            ->groupBy('compra_venta_detalle_id');

        $devolucionesDespachadas = DB::table('devolucion_detalle_costos')
            ->selectRaw('despacho_detalle_costo_id, SUM(cantidad_devuelta) as cantidad_devuelta')
            ->groupBy('despacho_detalle_costo_id');
        $despachoCostos = empty($detailIds) ? collect() : DB::table('despacho_detalle_costos as ddc')
            ->join('despacho_detalles as dd', 'dd.id', '=', 'ddc.despacho_detalle_id')
            ->join('despachos as d', 'd.id', '=', 'dd.despacho_id')
            ->join('venta_detalles as vd', 'vd.id', '=', 'dd.venta_detalle_id')
            ->join('ventas as v', 'v.id', '=', 'd.venta_id')
            ->join('compra_venta_detalle as cvd', 'cvd.id', '=', 'ddc.compra_venta_detalle_id')
            ->leftJoin('compras as c', 'c.id', '=', 'cvd.compra_id')
            ->leftJoin('cajas as sale_caja', 'sale_caja.id', '=', 'v.caja_id')
            ->leftJoin('cajas as purchase_caja', 'purchase_caja.id', '=', 'c.caja_id')
            ->leftJoinSub($devolucionesDespachadas, 'dev', function ($join): void {
                $join->on('dev.despacho_detalle_costo_id', '=', 'ddc.id');
            })
            ->whereIn('vd.id', $detailIds)
            ->select([
                'ddc.id as source_id',
                'ddc.cantidad as costed_quantity',
                DB::raw('COALESCE(dev.cantidad_devuelta, 0) as returned_quantity'),
                'dd.id as dispatch_detail_id',
                'dd.cantidad as dispatched_quantity',
                'd.id as dispatch_id',
                'vd.id as venta_detalle_id',
                'vd.precio_unitario',
                'cvd.id as cost_assignment_id',
                'cvd.costo_unitario',
                'sale_caja.empresa as sale_company',
                'purchase_caja.empresa as purchase_company',
            ])
            ->orderBy('ddc.id')
            ->get()
            ->groupBy('venta_detalle_id');

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
            $dispatchCostCents = 0;
            $dispatchIncomeCents = 0;
            $dispatchProfitCents = 0;
            $dispatchCommissionCents = 0;
            $dispatchCostedQuantity = 0;
            $dispatchCostIncomplete = false;
            $dispatchCostAmbiguous = false;

            foreach ($saleDetails as $detalle) {
                $soldQty = DecimalMoney::quantityHundredths($detalle->cantidad);
                $returnedQty = DecimalMoney::quantityHundredths($returned->get($detalle->id)?->cantidad ?? 0);
                $deliveredQty = DecimalMoney::quantityHundredths($dispatched->get($detalle->id)?->cantidad ?? 0);
                $netQty = max(0, $soldQty - $returnedQty);
                $quantitySold += $soldQty;
                $quantityReturned += $returnedQty;
                $quantityDispatched += $deliveredQty;

                $allSources = $costos->get($detalle->id, collect());
                $sources = $allSources->filter(
                    fn ($allocation) => !$allocation->venta_empresa
                        || $allocation->compra_empresa === $allocation->venta_empresa
                )->map(function ($allocation) use ($regularizaciones, $returnedByCvd): array {
                    $allocationQty = DecimalMoney::quantityHundredths($allocation->cantidad);
                    $returnedFromSource = DecimalMoney::quantityHundredths(
                        $returnedByCvd->get($allocation->id)?->cantidad ?? 0
                    );

                    return [
                        'asignacion_id' => (int) $allocation->id,
                        'cantidad_original' => $allocationQty,
                        'cantidad_devuelta' => $returnedFromSource,
                        'cantidad' => max(0, $allocationQty - $returnedFromSource),
                        'costo' => $this->effectiveCost(
                            $allocation->costo_unitario,
                            $regularizaciones->get($allocation->id, collect())
                        ),
                    ];
                })->values()->all();
                $crossCompanyCost = count($sources) !== $allSources->count();
                $assignedQty = array_sum(array_column($sources, 'cantidad_original'));
                $returnedFromAssignments = array_sum(array_column($sources, 'cantidad_devuelta'));
                $costedQty = 0;
                $lineCostCents = 0;
                $hasUncostedRemainingSource = false;
                $returnSourceInconsistent = false;
                foreach ($sources as $source) {
                    if ($source['cantidad_devuelta'] > $source['cantidad_original']) {
                        $returnSourceInconsistent = true;
                    }
                    if ($source['cantidad'] <= 0) {
                        continue;
                    }
                    if ($source['costo'] === null) {
                        $hasUncostedRemainingSource = true;
                        continue;
                    }
                    $costedQty += $source['cantidad'];
                    $lineCostCents += DecimalMoney::multiplyCostByQuantity(
                        DecimalMoney::costUnits($source['costo']),
                        $source['cantidad']
                    );
                }

                $costState = 'sin_costear';
                $lineProfitCents = 0;
                $mappedReturnedQty = DecimalMoney::quantityHundredths(
                    $returnedMappedByDetail->get($detalle->id)?->cantidad ?? 0
                );
                $unmappedReturnedQty = max(0, $returnedQty - $mappedReturnedQty);
                $returnMappingInconsistent = $returnSourceInconsistent
                    || $returnedFromAssignments !== $mappedReturnedQty
                    || $mappedReturnedQty > $returnedQty;

                if (
                    $assignedQty > 0
                    && ($unmappedReturnedQty > 0 || $returnMappingInconsistent)
                ) {
                    $hasAmbiguousReturn = true;
                    $allCosted = false;
                    $costState = 'ambiguo_devolucion';
                    $costedQty = 0;
                    $lineCostCents = 0;
                } elseif ($returnedQty > $soldQty || $assignedQty > $soldQty) {
                    $allCosted = false;
                    $costState = 'inconsistente';
                    $costedQty = 0;
                    $lineCostCents = 0;
                } elseif ($netQty === 0) {
                    $costState = 'costeada';
                } elseif (
                    $assignedQty - $returnedFromAssignments === $netQty
                    && $costedQty === $netQty
                    && !$hasUncostedRemainingSource
                ) {
                    $costState = 'costeada';
                    $lineIncomeCents = DecimalMoney::multiplyCentsByQuantity(
                        DecimalMoney::cents($detalle->precio_unitario),
                        $netQty
                    );
                    $lineProfitCents = $lineIncomeCents - $lineCostCents;
                    $profitKnownCents += $lineProfitCents;
                } elseif ($costedQty > 0) {
                    $allCosted = false;
                    $costState = 'parcial';
                    $usableQty = min($netQty, $costedQty);
                    $lineIncomeCents = DecimalMoney::multiplyCentsByQuantity(
                        DecimalMoney::cents($detalle->precio_unitario),
                        $usableQty
                    );
                    $lineProfitCents = $lineIncomeCents - $lineCostCents;
                    $profitKnownCents += $lineProfitCents;
                } else {
                    $allCosted = false;
                }

                if ($costState === 'costeada' && $netQty > 0) {
                    $costCents += $lineCostCents;
                } elseif ($costState === 'parcial') {
                    $costCents += $lineCostCents;
                }

                if ($costState !== 'costeada' || $crossCompanyCost) {
                    $allCosted = false;
                }

                $dispatchRows = $despachoCostos->get($detalle->id, collect())
                    ->groupBy('dispatch_detail_id');
                $lineDispatchCost = 0;
                $lineDispatchIncome = 0;
                $lineDispatchProfit = 0;
                $lineDispatchCommission = 0;
                $lineDispatchQty = 0;
                $lineHasIncomplete = false;
                foreach ($dispatchRows as $dispatchCostRows) {
                    $dispatchedQty = DecimalMoney::quantityHundredths($dispatchCostRows->first()->dispatched_quantity);
                    $allocatedQty = $dispatchCostRows->sum(
                        fn ($source) => DecimalMoney::quantityHundredths($source->costed_quantity)
                    );
                    $dispatchCostSources = $dispatchCostRows->map(function ($source) use ($regularizaciones) {
                        return [
                            'source' => $source,
                            'cost' => $this->effectiveCost(
                                $source->costo_unitario,
                                $regularizaciones->get($source->cost_assignment_id, collect())
                            ),
                        ];
                    });
                    $crossCompany = $dispatchCostRows->contains(
                        fn ($source) => $source->sale_company
                            && $source->purchase_company !== $source->sale_company
                    );
                    if ($allocatedQty !== $dispatchedQty
                        || $dispatchCostSources->contains(fn ($source) => $source['cost'] === null)
                        || $crossCompany) {
                        $lineHasIncomplete = true;
                        continue;
                    }

                    foreach ($dispatchCostSources as $sourceRow) {
                        $source = $sourceRow['source'];
                        $qty = max(
                            0,
                            DecimalMoney::quantityHundredths($source->costed_quantity)
                                - DecimalMoney::quantityHundredths($source->returned_quantity)
                        );
                        if ($qty === 0) {
                            continue;
                        }
                        $unitCost = DecimalMoney::costUnits($sourceRow['cost']);
                        $revenue = DecimalMoney::multiplyCentsByQuantity(
                            DecimalMoney::cents($source->precio_unitario),
                            $qty
                        );
                        $cost = DecimalMoney::multiplyCostByQuantity($unitCost, $qty);
                        $lineDispatchCost += $cost;
                        $lineDispatchIncome += $revenue;
                        $lineDispatchProfit += $revenue - $cost;
                        $lineDispatchQty += $qty;
                    }
                }

                if ($lineDispatchQty > 0) {
                    $rate = Carbon::parse($venta->fecha)->isSunday() ? 5000 : 3500;
                    $lineDispatchCommission = DecimalMoney::commissionCents($lineDispatchProfit, $rate);
                }
                $dispatchCostCents += $lineDispatchCost;
                $dispatchIncomeCents += $lineDispatchIncome;
                $dispatchProfitCents += $lineDispatchProfit;
                $dispatchCommissionCents += $lineDispatchCommission;
                $dispatchCostedQuantity += $lineDispatchQty;
                $dispatchCostIncomplete = $dispatchCostIncomplete || $lineHasIncomplete;

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
                    'cantidad_despachada_costeada' => DecimalMoney::formatScaled($lineDispatchQty, 2),
                    'estado_costeo' => $costState,
                    'costo_historico' => DecimalMoney::formatCents($lineCostCents),
                    'costo_despachado' => DecimalMoney::formatCents($lineDispatchCost),
                    'utilidad_despachada' => DecimalMoney::formatCents($lineDispatchProfit),
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
            $commissionPendingCents = $dispatchCommissionCents - $saldoLiberado;

            $result[$saleId] = [
                'venta_id' => (int) $saleId,
                'fecha' => $venta->fecha,
                'ingreso_neto' => DecimalMoney::formatCents($ingresoCents),
                'costo_historico' => DecimalMoney::formatCents($costCents),
                'costo_despachado' => DecimalMoney::formatCents($dispatchCostCents),
                'ingreso_costeado' => $allCosted && $esCalculable
                    ? DecimalMoney::formatCents($ingresoCents)
                    : '0.00',
                'ingreso_despachado' => DecimalMoney::formatCents(
                    $dispatchIncomeCents
                ),
                'utilidad_economica' => $utilidadCents === null ? null : DecimalMoney::formatCents($utilidadCents),
                'utilidad_conocida' => DecimalMoney::formatCents($profitKnownCents),
                'utilidad_despachada' => DecimalMoney::formatCents($dispatchProfitCents),
                'estado_costeo' => $allCosted && !$hasAmbiguousReturn ? 'costeada' : 'incompleto',
                'estado_pago' => $estadoPago,
                'estado_despacho' => $estadoDespacho,
                'comision_porcentaje' => $tasa === 5000 ? '50.00' : '35.00',
                'comision_potencial' => !$esCalculable || $comisionPotencialCents === null
                    ? null
                    : DecimalMoney::formatCents($comisionPotencialCents),
                'comision_despachada_potencial' => $esCalculable
                    ? DecimalMoney::formatCents($dispatchCommissionCents)
                    : '0.00',
                'comision_liberada' => DecimalMoney::formatCents($saldoLiberado),
                'comision_pendiente' => !$esCalculable
                    ? null
                    : DecimalMoney::formatCents($commissionPendingCents),
                'comision_conocida_parcial' => $esCalculable && !$allCosted
                    ? DecimalMoney::formatCents(DecimalMoney::commissionCents($profitKnownCents, $tasa))
                    : null,
                'cantidad_vendida' => DecimalMoney::formatScaled($quantitySold, 2),
                'cantidad_devuelta' => DecimalMoney::formatScaled($quantityReturned, 2),
                'cantidad_despachada' => DecimalMoney::formatScaled($quantityDispatched, 2),
                'cantidad_despachada_costeada' => DecimalMoney::formatScaled($dispatchCostedQuantity, 2),
                'despacho_costeo_incompleto' => $dispatchCostIncomplete,
                'despacho_costo_ambiguo' => $dispatchCostAmbiguous,
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

}
