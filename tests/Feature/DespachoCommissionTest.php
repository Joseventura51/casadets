<?php

namespace Tests\Feature;

use App\Http\Controllers\SaldoFavorController;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\CompraVentaDetalle;
use App\Models\Devolucion;
use App\Models\DevolucionDetalle;
use App\Models\SaldoFavor;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\Vendedor;
use App\Services\CobranzaService;
use App\Services\ComisionLedgerService;
use App\Services\DespachoService;
use App\Services\RegularizacionCostoService;
use App\Services\UtilidadVentaService;
use App\Support\DecimalMoney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DespachoCommissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_releases_commission_once_and_does_not_create_stock_movement(): void
    {
        [$venta, $detalle, $asignacion] = $this->ventaCosteadaPagada('2026-11-09');
        $service = app(DespachoService::class);
        $requestKey = (string) \Illuminate\Support\Str::uuid();
        $payload = [
            $detalle->id => '2.00',
        ];
        $costos = [
            $detalle->id => [$asignacion->id => '2.00'],
        ];

        $first = $service->registrar($venta, $payload, $costos, $requestKey, 'Entrega parcial');
        $second = $service->registrar($venta, $payload, $costos, $requestKey, 'Entrega parcial');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, DB::table('despachos')->where('venta_id', $venta->id)->count());
        $this->assertSame(1, DB::table('despacho_detalles')->where('despacho_id', $first->id)->count());
        $this->assertSame(1, DB::table('comision_movimientos')->where('venta_id', $venta->id)->count());
        $this->assertSame('7.00', DecimalMoney::formatCents(DecimalMoney::cents(
            DB::table('comision_movimientos')->where('venta_id', $venta->id)->value('importe')
        )));
        $this->assertSame(0, DB::table('stock_movimientos')->count());
    }

    public function test_dispatch_waits_for_full_payment_and_releases_when_balance_is_paid(): void
    {
        [$venta, $detalle, $asignacion] = $this->ventaCosteadaPagada('2026-11-09', '100.00');
        $service = app(DespachoService::class);
        $service->registrar(
            $venta,
            [$detalle->id => '10.00'],
            [$detalle->id => [$asignacion->id => '10.00']],
            (string) \Illuminate\Support\Str::uuid(),
            null
        );

        $this->assertSame(0, DB::table('comision_movimientos')->where('venta_id', $venta->id)->count());

        $venta->update(['pagado' => '200.00']);

        $this->assertSame(1, DB::table('comision_movimientos')->where('venta_id', $venta->id)->count());
        $this->assertSame('35.00', DecimalMoney::formatCents(DecimalMoney::cents(
            DB::table('comision_movimientos')->where('venta_id', $venta->id)->value('importe')
        )));
    }

    public function test_regularizing_a_released_cost_posts_only_the_commission_delta_once(): void
    {
        [$venta, $detalle, $asignacion] = $this->ventaCosteadaPagada('2026-11-09');
        app(DespachoService::class)->registrar(
            $venta,
            [$detalle->id => '2.00'],
            [$detalle->id => [$asignacion->id => '2.00']],
            (string) \Illuminate\Support\Str::uuid(),
            null
        );
        $key = (string) \Illuminate\Support\Str::uuid();
        $service = app(RegularizacionCostoService::class);

        $regularizacion = $service->regularizar($asignacion->id, '12.0000', $key, 'Factura real', 'Costo final');
        $again = $service->regularizar($asignacion->id, '12.0000', $key, 'Factura real', 'Costo final');

        $this->assertSame($regularizacion->id, $again->id);
        $movimientos = DB::table('comision_movimientos')->where('venta_id', $venta->id)->orderBy('id')->get();
        $this->assertCount(2, $movimientos);
        $this->assertSame('7.00', DecimalMoney::formatCents(DecimalMoney::cents($movimientos[0]->importe)));
        $this->assertSame('-1.40', DecimalMoney::formatCents(DecimalMoney::cents($movimientos[1]->importe)));
        $this->assertSame('5.60', app(\App\Services\ComisionLedgerService::class)->saldoVendedor($venta->vendedor_id));
    }

    public function test_sale_date_controls_commission_rate(): void
    {
        [$venta, $detalle, $asignacion] = $this->ventaCosteadaPagada('2026-11-08');
        app(DespachoService::class)->registrar(
            $venta,
            [$detalle->id => '2.00'],
            [$detalle->id => [$asignacion->id => '2.00']],
            (string) \Illuminate\Support\Str::uuid(),
            null
        );

        $this->assertSame('10.00', DecimalMoney::formatCents(DecimalMoney::cents(
            DB::table('comision_movimientos')->where('venta_id', $venta->id)->value('importe')
        )));
        $this->assertSame(5000, DecimalMoney::scaledInt(
            DB::table('comision_movimientos')->where('venta_id', $venta->id)->value('porcentaje'),
            2
        ));
    }

    public function test_mixed_cost_allocations_are_summed_per_historical_source(): void
    {
        [$venta, $detalle, $firstAssignment] = $this->ventaCosteadaPagada('2026-11-09');
        DB::table('compra_venta_detalle')->where('id', $firstAssignment->id)->update([
            'cantidad' => '5.00',
            'costo_total' => '50.0000',
        ]);
        $secondAssignmentId = $this->crearAsignacion($venta, $detalle, '5.00', '15.0000');

        $summary = app(UtilidadVentaService::class)->resumen($venta->fresh());

        $this->assertSame('10.00', $summary['detalles'][0]['cantidad_costeada']);
        $this->assertSame('125.00', $summary['costo_historico']);
        $this->assertSame('75.00', $summary['utilidad_economica']);
        $this->assertSame('costeada', $summary['estado_costeo']);
        $this->assertNotSame($firstAssignment->id, $secondAssignmentId);
    }

    public function test_mixed_cost_return_uses_the_exact_cost_assignment(): void
    {
        [$venta, $detalle, $firstAssignment] = $this->ventaCosteadaPagada('2026-11-09');
        DB::table('compra_venta_detalle')->where('id', $firstAssignment->id)->update([
            'cantidad' => '5.00',
            'costo_total' => '50.0000',
        ]);
        $this->crearAsignacion($venta, $detalle, '5.00', '15.0000');
        $devolucion = Devolucion::create([
            'venta_id' => $venta->id,
            'tipo' => 'parcial',
            'monto_devuelto' => '40.00',
            'saldo_generado' => '0.00',
            'fecha' => '2026-11-09',
            'empresa' => 'casadets',
            'caja_id' => $venta->caja_id,
        ]);
        $devolucionDetalle = DevolucionDetalle::create([
            'devolucion_id' => $devolucion->id,
            'venta_detalle_id' => $detalle->id,
            'cantidad_devuelta' => '2.00',
            'precio_unitario' => '20.00',
            'subtotal' => '40.00',
        ]);
        DB::table('devolucion_detalle_costos')->insert([
            'devolucion_detalle_id' => $devolucionDetalle->id,
            'compra_venta_detalle_id' => $firstAssignment->id,
            'cantidad_devuelta' => '2.00',
            'costo_unitario' => '10.0000',
            'metodo_asignacion' => 'despacho_identificado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $venta->update(['ajuste' => '-40.00']);

        $summary = app(UtilidadVentaService::class)->resumen($venta->fresh());

        $this->assertSame('8.00', $summary['detalles'][0]['cantidad_neta']);
        $this->assertSame('105.00', $summary['costo_historico']);
        $this->assertSame('55.00', $summary['utilidad_economica']);
        $this->assertSame('costeada', $summary['estado_costeo']);
        $this->assertSame('19.25', $summary['comision_potencial']);
    }

    public function test_unmapped_return_is_ambiguous_when_multiple_costs_exist(): void
    {
        [$venta, $detalle, $firstAssignment] = $this->ventaCosteadaPagada('2026-11-09');
        DB::table('compra_venta_detalle')->where('id', $firstAssignment->id)->update([
            'cantidad' => '5.00',
            'costo_total' => '50.0000',
        ]);
        $this->crearAsignacion($venta, $detalle, '5.00', '15.0000');
        $devolucion = Devolucion::create([
            'venta_id' => $venta->id,
            'tipo' => 'parcial',
            'monto_devuelto' => '40.00',
            'saldo_generado' => '0.00',
            'fecha' => '2026-11-09',
            'empresa' => 'casadets',
            'caja_id' => $venta->caja_id,
        ]);
        DevolucionDetalle::create([
            'devolucion_id' => $devolucion->id,
            'venta_detalle_id' => $detalle->id,
            'cantidad_devuelta' => '2.00',
            'precio_unitario' => '20.00',
            'subtotal' => '40.00',
        ]);
        $venta->update(['ajuste' => '-40.00']);

        $summary = app(UtilidadVentaService::class)->resumen($venta->fresh());

        $this->assertSame('ambiguo_devolucion', $summary['detalles'][0]['estado_costeo']);
        $this->assertNull($summary['utilidad_economica']);
    }

    public function test_dispatch_cannot_exceed_the_remaining_quantity(): void
    {
        [$venta, $detalle, $asignacion] = $this->ventaCosteadaPagada('2026-11-09');
        $service = app(DespachoService::class);

        $this->expectException(ValidationException::class);
        $service->registrar(
            $venta,
            [$detalle->id => '11.00'],
            [$detalle->id => [$asignacion->id => '10.00']],
            (string) \Illuminate\Support\Str::uuid(),
            null
        );
    }

    public function test_credit_note_balance_completes_a_paid_dispatched_sale_without_cash_or_duplicate_commission(): void
    {
        [$venta, $detalle, $asignacion] = $this->ventaCosteadaPagada('2026-11-08', '0.00');
        $cliente = Cliente::create([
            'nombre' => 'Cliente NC de prueba',
            'documento' => 'NC-TEST-001',
            'activo' => true,
        ]);
        $venta->update(['cliente_id' => $cliente->id]);

        app(DespachoService::class)->registrar(
            $venta,
            [$detalle->id => '2.00'],
            [$detalle->id => [$asignacion->id => '2.00']],
            (string) Str::uuid(),
            null
        );
        app(CobranzaService::class)->registrarPago(
            $venta->fresh(),
            [['metodo' => 'efectivo', 'monto' => '100.00']],
            null,
            null,
            'casadets'
        );
        $this->assertSame(0, DB::table('comision_movimientos')->where('venta_id', $venta->id)->count());

        $saldo = $this->convertirNotaCreditoEnSaldo($venta, $cliente, 'NC-TEST-001');
        $resultado = app(CobranzaService::class)->aplicarSaldoFavor($saldo, $venta->fresh(), 100.00);

        $this->assertSame(100.0, $resultado['aplicado']);
        $this->assertSame('200.00', $venta->fresh()->pagado);
        $this->assertSame('pagado', $venta->fresh()->estado);
        $this->assertSame(1, DB::table('movimientos')->where('tipo', 'ingreso')->count());
        $this->assertSame(10000, DecimalMoney::cents(
            DB::table('movimientos')->where('tipo', 'ingreso')->sum('monto')
        ));
        $this->assertSame(1, DB::table('movimientos')
            ->where('tipo', 'contable')
            ->where('subtipo', 'saldo_favor_usado')
            ->count());

        app(ComisionLedgerService::class)->sincronizarVenta($venta->fresh());
        $movimientos = DB::table('comision_movimientos')->where('venta_id', $venta->id)->get();
        $this->assertCount(1, $movimientos);
        $this->assertSame('10.00', DecimalMoney::formatCents(DecimalMoney::cents($movimientos[0]->importe)));
        $this->assertSame(5000, DecimalMoney::scaledInt($movimientos[0]->porcentaje, 2));
        $this->assertSame('2026-11-08', $movimientos[0]->fecha_origen);
    }

    public function test_credit_note_application_uses_adjusted_amount_due(): void
    {
        [$venta] = $this->ventaCosteadaPagada('2026-11-09', '0.00');
        $cliente = Cliente::create([
            'nombre' => 'Cliente con ajuste',
            'documento' => 'AJUSTE-TEST-001',
            'activo' => true,
        ]);
        $venta->update([
            'cliente_id' => $cliente->id,
            'ajuste' => '10.00',
        ]);

        app(CobranzaService::class)->registrarPago(
            $venta->fresh(),
            [['metodo' => 'efectivo', 'monto' => '110.00']],
            null,
            null,
            'casadets'
        );
        $pendiente = app(CobranzaService::class)
            ->ventasPendientesCliente($cliente->id)
            ->firstWhere('id', $venta->id);
        $this->assertSame(100.0, $pendiente->saldo_pendiente);

        $saldo = $this->convertirNotaCreditoEnSaldo($venta, $cliente, 'NC-AJUSTE-001');
        $resultado = app(CobranzaService::class)->aplicarSaldoFavor($saldo, $venta->fresh(), 100.00);

        $this->assertSame(100.0, $resultado['aplicado']);
        $this->assertSame('210.00', $venta->fresh()->pagado);
        $this->assertSame('pagado', $venta->fresh()->estado);
    }

    public function test_unknown_historical_dispatch_cost_waits_for_regularization_and_is_idempotent(): void
    {
        [$venta, $detalle, $asignacion] = $this->ventaCosteadaPagada('2026-11-08');
        DB::table('compra_venta_detalle')->where('id', $asignacion->id)->update([
            'costo_unitario' => null,
            'costo_total' => null,
        ]);

        $despachoId = DB::table('despachos')->insertGetId([
            'venta_id' => $venta->id,
            'idempotency_key' => (string) Str::uuid(),
            'fecha_hora' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $despachoDetalleId = DB::table('despacho_detalles')->insertGetId([
            'despacho_id' => $despachoId,
            'venta_detalle_id' => $detalle->id,
            'cantidad' => '2.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('despacho_detalle_costos')->insert([
            'despacho_detalle_id' => $despachoDetalleId,
            'compra_venta_detalle_id' => $asignacion->id,
            'cantidad' => '2.00',
            'created_at' => now(),
        ]);

        app(ComisionLedgerService::class)->sincronizarVenta($venta);
        $this->assertNull(DB::table('compra_venta_detalle')
            ->where('id', $asignacion->id)
            ->value('costo_unitario'));
        $this->assertSame(0, DB::table('comision_movimientos')->where('venta_id', $venta->id)->count());

        $key = (string) Str::uuid();
        $service = app(RegularizacionCostoService::class);
        $regularizacion = $service->regularizar(
            $asignacion->id,
            '10.0000',
            $key,
            'Factura real',
            'Costo confirmado'
        );
        $repetida = $service->regularizar(
            $asignacion->id,
            '10.0000',
            $key,
            'Factura real',
            'Costo confirmado'
        );

        $this->assertSame($regularizacion->id, $repetida->id);
        $this->assertNull($regularizacion->costo_anterior);
        $this->assertSame(1, DB::table('regularizaciones_costo')
            ->where('compra_venta_detalle_id', $asignacion->id)
            ->count());
        $movimientos = DB::table('comision_movimientos')->where('venta_id', $venta->id)->get();
        $this->assertCount(1, $movimientos);
        $this->assertSame('10.00', DecimalMoney::formatCents(DecimalMoney::cents($movimientos[0]->importe)));
        $this->assertSame(5000, DecimalMoney::scaledInt($movimientos[0]->porcentaje, 2));
        $this->assertSame('2026-11-08', $movimientos[0]->fecha_origen);
    }

    private function convertirNotaCreditoEnSaldo(Venta $venta, Cliente $cliente, string $numero): SaldoFavor
    {
        $notaCredito = Venta::create([
            'vendedor_id' => $venta->vendedor_id,
            'caja_id' => $venta->caja_id,
            'cliente_id' => $cliente->id,
            'total' => '-100.00',
            'ajuste' => '0.00',
            'pagado' => '0.00',
            'documento_tipo' => 'nota_credito',
            'documento_numero' => $numero,
            'fecha' => '2026-11-09',
            'estado' => 'pagado',
            'es_referencia_fiscal' => false,
        ]);

        app(SaldoFavorController::class)->convertirNC(Request::create('/'), $notaCredito);

        return SaldoFavor::query()
            ->where('venta_origen_id', $notaCredito->id)
            ->firstOrFail();
    }

    private function ventaCosteadaPagada(string $fecha, string $pagado = '200.00'): array
    {
        $vendedor = Vendedor::create(['nombre' => 'Vendedor de prueba']);
        $caja = Caja::create([
            'codigo' => 'C01',
            'nombre' => 'Caja de prueba',
            'empresa' => 'casadets',
            'activa' => true,
        ]);
        $venta = Venta::create([
            'vendedor_id' => $vendedor->id,
            'caja_id' => $caja->id,
            'total' => '200.00',
            'ajuste' => '0.00',
            'pagado' => $pagado,
            'metodo_pago' => 'efectivo',
            'documento_tipo' => 'boleta',
            'documento_numero' => 'T001-' . str_replace('-', '', $fecha) . '-' . random_int(100, 999),
            'fecha' => $fecha,
            'estado' => $pagado === '200.00' ? 'pagado' : 'parcial',
            'es_referencia_fiscal' => false,
        ]);
        $detalle = VentaDetalle::create([
            'venta_id' => $venta->id,
            'producto' => 'Producto de prueba',
            'cantidad' => '10.00',
            'precio_unitario' => '20.00',
            'subtotal' => '200.00',
        ]);
        $compraId = DB::table('compras')->insertGetId([
            'empresa' => 'casadets',
            'caja_id' => $caja->id,
            'fecha' => $fecha,
            'monto_total' => '100.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $asignacionId = DB::table('compra_venta_detalle')->insertGetId([
            'compra_id' => $compraId,
            'venta_detalle_id' => $detalle->id,
            'cantidad' => '10.00',
            'costo_unitario' => '10.0000',
            'costo_total' => '100.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $asignacion = CompraVentaDetalle::findOrFail($asignacionId);

        return [$venta, $detalle, $asignacion];
    }

    private function crearAsignacion(Venta $venta, VentaDetalle $detalle, string $cantidad, string $costo): int
    {
        $purchaseId = DB::table('compras')->insertGetId([
            'empresa' => 'casadets',
            'caja_id' => $venta->caja_id,
            'fecha' => $venta->fecha->toDateString(),
            'monto_total' => number_format((float) $cantidad * (float) $costo, 2, '.', ''),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('compra_venta_detalle')->insertGetId([
            'compra_id' => $purchaseId,
            'venta_detalle_id' => $detalle->id,
            'cantidad' => $cantidad,
            'costo_unitario' => $costo,
            'costo_total' => number_format((float) $cantidad * (float) $costo, 4, '.', ''),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
