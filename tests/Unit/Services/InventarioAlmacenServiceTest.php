<?php

namespace Tests\Unit\Services;

use App\Models\Almacen;
use App\Models\Producto;
use App\Models\ProductoAlmacen;
use App\Models\StockMovimiento;
use App\Services\InventarioAlmacenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class InventarioAlmacenServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_registra_una_entrada_con_saldos_y_almacen(): void
    {
        [$producto, $pimpon] = $this->productoConStock(100, 'PIMPON');

        $movimiento = app(InventarioAlmacenService::class)->mover(
            $producto,
            $pimpon,
            'entrada',
            20,
            'prueba',
            10
        );

        $this->assertSame(120.0, (float) ProductoAlmacen::where('producto_id', $producto->id)
            ->where('almacen_id', $pimpon->id)
            ->value('stock_actual'));
        $this->assertSame(100.0, (float) $movimiento->saldo_anterior);
        $this->assertSame(20.0, (float) $movimiento->cantidad);
        $this->assertSame(120.0, (float) $movimiento->saldo_posterior);
        $this->assertSame($pimpon->id, $movimiento->almacen_id);
        $this->assertSame(10, $movimiento->referencia_id);
    }

    public function test_registra_una_salida_sobre_el_saldo_del_mismo_almacen(): void
    {
        [$producto, $pimpon] = $this->productoConStock(120, 'PIMPON');

        $movimiento = app(InventarioAlmacenService::class)->mover(
            $producto,
            $pimpon,
            'salida',
            30,
            'prueba',
            11
        );

        $this->assertSame(90.0, (float) ProductoAlmacen::where('producto_id', $producto->id)
            ->where('almacen_id', $pimpon->id)
            ->value('stock_actual'));
        $this->assertSame(120.0, (float) $movimiento->saldo_anterior);
        $this->assertSame(90.0, (float) $movimiento->saldo_posterior);
    }

    public function test_rechaza_stock_negativo_y_conserva_el_saldo(): void
    {
        [$producto, $pimpon] = $this->productoConStock(90, 'PIMPON');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stock insuficiente');

        try {
            app(InventarioAlmacenService::class)->mover($producto, $pimpon, 'salida', 100);
        } finally {
            $this->assertSame(90.0, (float) ProductoAlmacen::where('producto_id', $producto->id)
                ->where('almacen_id', $pimpon->id)
                ->value('stock_actual'));
            $this->assertSame(0, StockMovimiento::count());
        }
    }

    public function test_rechaza_casadets(): void
    {
        [$producto] = $this->productoConStock(0, 'PIMPON');
        $casadets = Almacen::create([
            'codigo' => 'CASADETS',
            'nombre' => 'CASADETS',
            'empresa' => 'casadets',
            'activo' => true,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('catálogo oficial activo de ZENDY');

        app(InventarioAlmacenService::class)->mover($producto, $casadets, 'entrada', 1);
    }

    public function test_rechaza_principal(): void
    {
        [$producto] = $this->productoConStock(0, 'PIMPON');
        $principal = Almacen::create([
            'codigo' => 'PRINCIPAL',
            'nombre' => 'PRINCIPAL',
            'empresa' => 'zendy',
            'activo' => true,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('catálogo oficial activo de ZENDY');

        app(InventarioAlmacenService::class)->mover($producto, $principal, 'entrada', 1);
    }

    public function test_rechaza_almacen_inexistente(): void
    {
        [$producto] = $this->productoConStock(0, 'PIMPON');
        $inexistente = new Almacen(['id' => 999999]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('catálogo oficial activo de ZENDY');

        app(InventarioAlmacenService::class)->mover($producto, $inexistente, 'entrada', 1);
    }

    public function test_rechaza_almacen_oficial_inactivo(): void
    {
        [$producto, $pimpon] = $this->productoConStock(0, 'PIMPON');
        $pimpon->update(['activo' => false]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('catálogo oficial activo de ZENDY');

        app(InventarioAlmacenService::class)->mover($producto, $pimpon, 'entrada', 1);
    }

    public function test_dos_almacenes_mantienen_saldos_independientes(): void
    {
        [$producto, $pimpon] = $this->productoConStock(100, 'PIMPON');
        $casa = Almacen::where('codigo', 'CASA')->firstOrFail();

        app(InventarioAlmacenService::class)->mover($producto, $casa, 'entrada', 50);
        app(InventarioAlmacenService::class)->mover($producto, $pimpon, 'salida', 20);

        $this->assertSame(80.0, (float) ProductoAlmacen::where('producto_id', $producto->id)
            ->where('almacen_id', $pimpon->id)
            ->value('stock_actual'));
        $this->assertSame(50.0, (float) ProductoAlmacen::where('producto_id', $producto->id)
            ->where('almacen_id', $casa->id)
            ->value('stock_actual'));
    }

    public function test_movimientos_secuenciales_sobre_el_mismo_saldo_conservan_la_contabilidad(): void
    {
        [$producto, $pimpon] = $this->productoConStock(100, 'PIMPON');
        $service = app(InventarioAlmacenService::class);

        $service->mover($producto, $pimpon, 'salida', 20, 'prueba', 1);
        $service->mover($producto, $pimpon, 'salida', 30, 'prueba', 2);

        $this->assertSame(50.0, (float) ProductoAlmacen::where('producto_id', $producto->id)
            ->where('almacen_id', $pimpon->id)
            ->value('stock_actual'));
        $this->assertSame(100.0, (float) StockMovimiento::where('referencia_id', 1)->value('saldo_anterior'));
        $this->assertSame(80.0, (float) StockMovimiento::where('referencia_id', 1)->value('saldo_posterior'));
        $this->assertSame(80.0, (float) StockMovimiento::where('referencia_id', 2)->value('saldo_anterior'));
        $this->assertSame(50.0, (float) StockMovimiento::where('referencia_id', 2)->value('saldo_posterior'));
    }

    public function test_el_modelo_rechaza_un_movimiento_nuevo_sin_almacen(): void
    {
        $producto = Producto::create([
            'nombre' => 'Producto sin almacén',
            'empresa' => 'zendy',
            'precio_venta' => 10,
            'precio_costo' => 5,
            'activo' => true,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('debe indicar un almacén ZENDY');

        StockMovimiento::create([
            'producto_id' => $producto->id,
            'tipo' => 'entrada',
            'cantidad' => 1,
            'fecha' => today(),
        ]);
    }

    private function productoConStock(float $stock, string $codigo): array
    {
        $producto = Producto::create([
            'nombre' => 'Producto ' . $codigo . ' ' . uniqid(),
            'empresa' => 'zendy',
            'precio_venta' => 10,
            'precio_costo' => 5,
            'activo' => true,
        ]);
        $almacen = Almacen::where('codigo', $codigo)->firstOrFail();

        ProductoAlmacen::create([
            'producto_id' => $producto->id,
            'almacen_id' => $almacen->id,
            'stock_actual' => $stock,
        ]);

        return [$producto, $almacen];
    }
}