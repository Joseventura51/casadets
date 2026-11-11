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

            $total = DecimalMoney::cents($venta->total) + DecimalMoney::cents($venta->ajuste);
            if (DecimalMoney::cents($venta->pagado) < $total || $total < 0) {
                return;
            }

            // venta.ajuste is a sale-level amount. Until its line-level meaning is
            // known, attributing it to dispatched products would invent accounting data.
            if (DecimalMoney::cents($venta->ajuste) !== 0 || !$venta->vendedor_id) {
                return;
            }

            $fechaVenta = Carbon::parse($venta->fecha);
            $porcentaje = $fechaVenta->isSunday() ? 50 : 35;

            $costosDespachados = DB::table('despacho_detalle_costos as ddc')
                ->join('despacho_detalles as dd', 'dd.id', '=', 'ddc.despacho_detalle_id')
                ->join('despachos as d', 'd.id', '=', 'dd.despacho_id')
                ->join('venta_detalles as vd', 'vd.id', '=', 'dd.venta_detalle_id')
                ->join('compra_venta_detalle as cvd', 'cvd.id', '=', 'ddc.compra_venta_detalle_id')
                ->where('d.venta_id', $venta->id)
                ->where('vd.venta_id', $venta->id)
                ->select([
                    'ddc.id as origen_id',
                    'ddc.cantidad',
                    'dd.id as despacho_detalle_id',
                    'd.id as despacho_id',
                    'vd.id as venta_detalle_id',
                    'vd.precio_unitario',
                    'cvd.id as cvd_id',
                    'cvd.costo_unitario',
                ])
                ->orderBy('ddc.id')
                ->get();

            foreach ($costosDespachados as $origen) {
                $costo = $this->costoActual((int) $origen->cvd_id, $origen->costo_unitario);
                if ($costo === null) {
                    continue;
                }

                $cantidad = DecimalMoney::quantityHundredths($origen->cantidad);
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
                $origenClave = 'despacho-costo:' . $origen->origen_id;

                DB::table('comision_movimientos')->insertOrIgnore([
                    'vendedor_id' => $venta->vendedor_id,
                    'venta_id' => $venta->id,
                    'venta_detalle_id' => $origen->venta_detalle_id,
                    'despacho_id' => $origen->despacho_id,
                    'despacho_detalle_costo_id' => $origen->origen_id,
                    'user_id' => auth()->id(),
                    'tipo' => 'liberacion',
                    'importe' => DecimalMoney::formatCents($comision),
                    'porcentaje' => $porcentaje . '.00',
                    'utilidad_base' => DecimalMoney::formatCents($utilidad),
                    'fecha_origen' => $fechaVenta->toDateString(),
                    'fecha_efectiva' => today()->toDateString(),
                    'origen_clave' => $origenClave,
                    'observaciones' => 'Liberación por unidades despachadas, costeadas y venta pagada.',
                    'created_at' => now(),
                ]);
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
