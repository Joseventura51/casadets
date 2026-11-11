<?php

namespace App\Services;

use App\Models\ComisionMovimiento;
use App\Models\RegularizacionCosto;
use App\Models\Venta;
use App\Support\DecimalMoney;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ComisionLedgerService
{
    public function sincronizarVenta(Venta|int $venta): void
    {
        $ventaId = $venta instanceof Venta ? $venta->getKey() : $venta;

        DB::transaction(function () use ($ventaId): void {
            $venta = Venta::query()->whereKey($ventaId)->lockForUpdate()->first();
            if (!$venta) {
                return;
            }

            if (in_array($venta->estado, ['anulado', 'anulado_nc'], true)) {
                $this->revertirAnulada($venta);
                return;
            }

            if ($venta->documento_tipo === 'nota_credito' || $venta->es_referencia_fiscal) {
                return;
            }

            $fechaVenta = Carbon::parse($venta->fecha);
            $porcentaje = $fechaVenta->isSunday() ? 50 : 35;
            $ventaEmpresa = $venta->caja_id
                ? DB::table('cajas')->where('id', $venta->caja_id)->value('empresa')
                : null;
            $montoDevoluciones = DecimalMoney::cents(
                DB::table('devoluciones')->where('venta_id', $venta->id)->sum('monto_devuelto')
            );
            $ajusteSoloDevoluciones = DecimalMoney::cents($venta->ajuste) === -$montoDevoluciones;
            $total = DecimalMoney::cents($venta->total) + DecimalMoney::cents($venta->ajuste);
            $ventaElegible = $venta->vendedor_id
                && $ventaEmpresa !== null
                && DecimalMoney::cents($venta->pagado) >= $total
                && $total >= 0
                && $ajusteSoloDevoluciones;

            $devolucionesPorOrigen = DB::table('devolucion_detalle_costos')
                ->selectRaw('despacho_detalle_costo_id, SUM(cantidad_devuelta) as cantidad_devuelta')
                ->groupBy('despacho_detalle_costo_id');
            $costosDespachados = DB::table('despacho_detalle_costos as ddc')
                ->join('despacho_detalles as dd', 'dd.id', '=', 'ddc.despacho_detalle_id')
                ->join('despachos as d', 'd.id', '=', 'dd.despacho_id')
                ->join('venta_detalles as vd', 'vd.id', '=', 'dd.venta_detalle_id')
                ->join('compra_venta_detalle as cvd', 'cvd.id', '=', 'ddc.compra_venta_detalle_id')
                ->leftJoin('compras as compra', 'compra.id', '=', 'cvd.compra_id')
                ->leftJoin('cajas as compra_caja', 'compra_caja.id', '=', 'compra.caja_id')
                ->leftJoinSub($devolucionesPorOrigen, 'dev', function ($join): void {
                    $join->on('dev.despacho_detalle_costo_id', '=', 'ddc.id');
                })
                ->where('d.venta_id', $venta->id)
                ->where('vd.venta_id', $venta->id)
                ->select([
                    'ddc.id as origen_id',
                    'ddc.cantidad',
                    DB::raw('COALESCE(dev.cantidad_devuelta, 0) as cantidad_devuelta'),
                    'dd.id as despacho_detalle_id',
                    'dd.cantidad as cantidad_despachada',
                    'd.id as despacho_id',
                    'vd.id as venta_detalle_id',
                    'vd.precio_unitario',
                    'cvd.id as cvd_id',
                    'cvd.costo_unitario',
                    'compra_caja.empresa as compra_empresa',
                ])
                ->orderBy('ddc.id')
                ->get();

            foreach ($costosDespachados->groupBy('despacho_detalle_id') as $costosLinea) {
                $cantidadDespachada = DecimalMoney::quantityHundredths($costosLinea->first()->cantidad_despachada);
                $cantidadCosteada = $costosLinea->sum(
                    fn ($origen) => DecimalMoney::quantityHundredths($origen->cantidad)
                );
                $costosEfectivos = $costosLinea->mapWithKeys(fn ($origen) => [
                    $origen->origen_id => $this->costoActual((int) $origen->cvd_id, $origen->costo_unitario),
                ]);
                $lineaCosteadaCompleta = $cantidadCosteada === $cantidadDespachada
                    && !$costosEfectivos->contains(null)
                    && $ventaEmpresa !== null
                    && !$costosLinea->contains(
                        fn ($origen) => $origen->compra_empresa !== $ventaEmpresa
                    );

                foreach ($costosLinea as $origen) {
                    $costo = $costosEfectivos[$origen->origen_id];
                    $cantidad = max(
                        0,
                        DecimalMoney::quantityHundredths($origen->cantidad)
                            - DecimalMoney::quantityHundredths($origen->cantidad_devuelta)
                    );
                    $utilidad = 0;
                    $comision = 0;
                    if ($ventaElegible && $lineaCosteadaCompleta && $cantidad > 0) {
                        $ingreso = DecimalMoney::multiplyCentsByQuantity(
                            DecimalMoney::cents($origen->precio_unitario),
                            $cantidad
                        );
                        $costoCents = DecimalMoney::multiplyCostByQuantity(
                            DecimalMoney::costUnits($costo),
                            $cantidad
                        );
                        $utilidad = $ingreso - $costoCents;
                        $comision = DecimalMoney::commissionCents($utilidad, $porcentaje * 100);
                    }

                    $this->reconciliarFuenteDespachada(
                        $venta,
                        $origen,
                        $fechaVenta,
                        $porcentaje,
                        $utilidad,
                        $comision
                    );
                }
            }
        }, 3);
    }

    public function registrarAjusteRegularizacion(RegularizacionCosto $regularizacion): void
    {
        if ($regularizacion->costo_anterior === null) {
            return;
        }

        DB::transaction(function () use ($regularizacion): void {
            $asignacionId = $regularizacion->compra_venta_detalle_id;
            $costoAnterior = DecimalMoney::costUnits($regularizacion->costo_anterior);
            $costoNuevo = DecimalMoney::costUnits($regularizacion->costo_nuevo);

            $usos = DB::table('despacho_detalle_costos as ddc')
                ->join('despacho_detalles as dd', 'dd.id', '=', 'ddc.despacho_detalle_id')
                ->join('despachos as d', 'd.id', '=', 'dd.despacho_id')
                ->join('venta_detalles as vd', 'vd.id', '=', 'dd.venta_detalle_id')
                ->join('ventas as v', 'v.id', '=', 'd.venta_id')
                ->where('ddc.compra_venta_detalle_id', $asignacionId)
                ->select([
                    'ddc.id as uso_id',
                    'ddc.cantidad',
                    'vd.id as venta_detalle_id',
                    'vd.precio_unitario',
                    'v.id as venta_id',
                    'v.vendedor_id',
                    'v.fecha',
                    'v.estado',
                    'v.documento_tipo',
                    'v.es_referencia_fiscal',
                ])
                ->get();

            foreach ($usos as $uso) {
                $original = ComisionMovimiento::query()
                    ->where('origen_clave', 'despacho-costo:' . $uso->uso_id)
                    ->first();
                if (!$original || in_array($uso->estado, ['anulado', 'anulado_nc'], true)) {
                    continue;
                }

                $cantidad = DecimalMoney::quantityHundredths($uso->cantidad);
                $deltaUtility = DecimalMoney::multiplyCostByQuantity(
                    $costoAnterior - $costoNuevo,
                    $cantidad
                );
                $tasa = DecimalMoney::scaledInt($original->porcentaje, 2);
                $deltaCommission = DecimalMoney::commissionCents($deltaUtility, $tasa);
                $clave = 'regularizacion:' . $regularizacion->id . ':uso:' . $uso->uso_id;

                DB::table('comision_movimientos')->insertOrIgnore([
                    'vendedor_id' => $uso->vendedor_id,
                    'venta_id' => $uso->venta_id,
                    'venta_detalle_id' => $uso->venta_detalle_id,
                    'regularizacion_costo_id' => $regularizacion->id,
                    'movimiento_original_id' => $original->id,
                    'user_id' => auth()->id(),
                    'tipo' => $deltaCommission < 0 ? 'ajuste_negativo' : 'ajuste_positivo',
                    'importe' => DecimalMoney::formatCents($deltaCommission),
                    'porcentaje' => $original->porcentaje,
                    'utilidad_base' => DecimalMoney::formatCents($deltaUtility),
                    'fecha_origen' => $original->fecha_origen->toDateString(),
                    'fecha_efectiva' => today()->toDateString(),
                    'origen_clave' => $clave,
                    'observaciones' => 'Ajuste por regularización de costo histórica.',
                    'created_at' => now(),
                ]);
            }
        }, 3);
    }

    public function saldoVendedor(int $vendedorId, ?string $desde = null, ?string $hasta = null): string
    {
        $sum = DB::table('comision_movimientos')
            ->where('vendedor_id', $vendedorId)
            ->when($desde, fn ($q) => $q->whereDate('fecha_efectiva', '>=', $desde))
            ->when($hasta, fn ($q) => $q->whereDate('fecha_efectiva', '<=', $hasta))
            ->value(DB::raw('COALESCE(SUM(importe), 0)'));

        return DecimalMoney::formatCents(DecimalMoney::cents($sum));
    }

    private function reconciliarFuenteDespachada(
        Venta $venta,
        object $origen,
        Carbon $fechaVenta,
        int $porcentaje,
        int $utilidadObjetivo,
        int $comisionObjetivo
    ): void {
        $origenClave = 'despacho-costo:' . $origen->origen_id;
        $original = DB::table('comision_movimientos')->where('origen_clave', $origenClave)->first();

        if (!$original && $comisionObjetivo !== 0) {
            DB::table('comision_movimientos')->insertOrIgnore([
                'vendedor_id' => $venta->vendedor_id,
                'venta_id' => $venta->id,
                'venta_detalle_id' => $origen->venta_detalle_id,
                'despacho_id' => $origen->despacho_id,
                'despacho_detalle_costo_id' => $origen->origen_id,
                'user_id' => auth()->id(),
                'tipo' => 'liberacion',
                'importe' => DecimalMoney::formatCents($comisionObjetivo),
                'porcentaje' => $porcentaje . '.00',
                'utilidad_base' => DecimalMoney::formatCents($utilidadObjetivo),
                'fecha_origen' => $fechaVenta->toDateString(),
                'fecha_efectiva' => today()->toDateString(),
                'origen_clave' => $origenClave,
                'observaciones' => 'Liberación de utilidad de unidades despachadas, costeadas y pagadas.',
                'created_at' => now(),
            ]);
            $original = DB::table('comision_movimientos')->where('origen_clave', $origenClave)->first();
        }

        if (!$original) {
            return;
        }

        if ((int) $original->vendedor_id !== (int) $venta->vendedor_id) {
            $comisionObjetivo = 0;
            $utilidadObjetivo = 0;
        }

        $movimientos = DB::table('comision_movimientos')
            ->where(function ($query) use ($original): void {
                $query->where('id', $original->id)
                    ->orWhere('movimiento_original_id', $original->id);
            });
        $saldoActual = DecimalMoney::cents((clone $movimientos)->sum('importe'));
        $diferencia = $comisionObjetivo - $saldoActual;
        if ($diferencia === 0) {
            return;
        }

        $secuencia = (clone $movimientos)->count();
        $deltaUtilidad = $utilidadObjetivo - DecimalMoney::cents(
            (clone $movimientos)->sum('utilidad_base')
        );
        DB::table('comision_movimientos')->insertOrIgnore([
            'vendedor_id' => $original->vendedor_id,
            'venta_id' => $venta->id,
            'venta_detalle_id' => $origen->venta_detalle_id,
            'despacho_id' => $origen->despacho_id,
            'despacho_detalle_costo_id' => $origen->origen_id,
            'movimiento_original_id' => $original->id,
            'user_id' => auth()->id(),
            'tipo' => $diferencia < 0 ? 'ajuste_negativo' : 'ajuste_positivo',
            'importe' => DecimalMoney::formatCents($diferencia),
            'porcentaje' => $original->porcentaje,
            'utilidad_base' => DecimalMoney::formatCents($deltaUtilidad),
            'fecha_origen' => Carbon::parse($original->fecha_origen)->toDateString(),
            'fecha_efectiva' => today()->toDateString(),
            'origen_clave' => 'reconciliacion:' . $origen->origen_id . ':' . $secuencia,
            'observaciones' => 'Ajuste de comisión por pago, devolución o regularización.',
            'created_at' => now(),
        ]);
    }

    private function revertirAnulada(Venta $venta): void
    {
        $liberaciones = ComisionMovimiento::query()
            ->where('venta_id', $venta->id)
            ->whereIn('tipo', ['liberacion', 'ajuste_positivo', 'ajuste_negativo'])
            ->get();

        foreach ($liberaciones as $movimiento) {
            $clave = 'anulacion:' . $movimiento->id;
            DB::table('comision_movimientos')->insertOrIgnore([
                'vendedor_id' => $movimiento->vendedor_id,
                'venta_id' => $venta->id,
                'venta_detalle_id' => $movimiento->venta_detalle_id,
                'despacho_id' => $movimiento->despacho_id,
                'movimiento_original_id' => $movimiento->id,
                'user_id' => auth()->id(),
                'tipo' => 'reversion',
                'importe' => DecimalMoney::formatCents(-DecimalMoney::cents($movimiento->importe)),
                'porcentaje' => $movimiento->porcentaje,
                'utilidad_base' => $movimiento->utilidad_base === null
                    ? null
                    : DecimalMoney::formatCents(-DecimalMoney::cents($movimiento->utilidad_base)),
                'fecha_origen' => $movimiento->fecha_origen->toDateString(),
                'fecha_efectiva' => today()->toDateString(),
                'origen_clave' => $clave,
                'observaciones' => 'Reversión automática por anulación de venta.',
                'created_at' => now(),
            ]);
        }
    }

    private function costoActual(int $asignacionId, mixed $costoBase): ?string
    {
        $regularizacion = DB::table('regularizaciones_costo')
            ->where('compra_venta_detalle_id', $asignacionId)
            ->orderByDesc('id')
            ->value('costo_nuevo');

        if ($regularizacion !== null) {
            return (string) $regularizacion;
        }

        return $costoBase === null ? null : (string) $costoBase;
    }
}
