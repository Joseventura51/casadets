<?php

namespace Tests\Feature;

use App\Models\CompraVentaDetalle;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\Vendedor;
use App\Services\DespachoService;
use App\Services\RegularizacionCostoService;
use App\Support\DecimalMoney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    private function ventaCosteadaPagada(string $fecha, string $pagado = '200.00'): array
    {
        $vendedor = Vendedor::create(['nombre' => 'Vendedor de prueba']);
        $venta = Venta::create([
            'vendedor_id' => $vendedor->id,
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
}
