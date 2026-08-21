<?php

namespace App\Services;

use App\Models\Almacen;
use App\Models\Producto;
use App\Models\ProductoAlmacen;
use App\Models\SalidaAlmacen;
use App\Models\DevolucionSalida;
use App\Models\DevolucionSalidaDetalle;
use App\Models\StockMovimiento;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventarioAlmacenService
{
    /**
     * Registra un movimiento en un almacén y actualiza el saldo cacheado.
     * La fila se bloquea dentro de la transacción para evitar salidas
     * concurrentes que produzcan stock negativo.
     */
    public function mover(
        Producto $producto,
        Almacen $almacen,
        string $tipo,
        float $cantidad,
        ?string $referenciaTipo = null,
        ?int $referenciaId = null,
        ?string $observaciones = null,
        ?float $precioUnitario = null
    ): StockMovimiento {
        if (!in_array($tipo, ['entrada', 'salida'], true)) {
            throw new RuntimeException('El tipo de movimiento de almacén no es válido.');
        }
        if ($cantidad <= 0) {
            throw new RuntimeException('La cantidad del movimiento debe ser mayor que cero.');
        }

        $saldo = ProductoAlmacen::where('producto_id', $producto->id)
            ->where('almacen_id', $almacen->id)
            ->lockForUpdate()
            ->first();

        if (!$saldo) {
            $saldo = ProductoAlmacen::create([
                'producto_id' => $producto->id,
                'almacen_id' => $almacen->id,
                'stock_actual' => 0,
            ]);
            $saldo = ProductoAlmacen::whereKey($saldo->id)->lockForUpdate()->first();
        }

        $anterior = round((float) $saldo->stock_actual, 2);
        $posterior = $tipo === 'entrada'
            ? round($anterior + $cantidad, 2)
            : round($anterior - $cantidad, 2);

        if ($posterior < 0) {
            throw new RuntimeException(
                "Stock insuficiente de {$producto->nombre} en {$almacen->nombre}. " .
                "Disponible: " . number_format($anterior, 2) .
                ", solicitado: " . number_format($cantidad, 2) . '.'
            );
        }

        $saldo->update(['stock_actual' => $posterior]);

        $movimiento = StockMovimiento::create([
            'producto_id' => $producto->id,
            'almacen_id' => $almacen->id,
            'tipo' => $tipo,
            'cantidad' => $cantidad,
            'saldo_anterior' => $anterior,
            'saldo_posterior' => $posterior,
            'precio_unitario' => $precioUnitario ?? $producto->precio_costo,
            'referencia_tipo' => $referenciaTipo,
            'referencia_id' => $referenciaId,
            'usuario_id' => auth()->id(),
            'fecha' => today(),
            'observaciones' => $observaciones,
        ]);

        // Mantener el stock global existente sincronizado con el Kardex.
        $producto->recalcularStock();

        return $movimiento;
    }

    public function registrarSalida(SalidaAlmacen $salida, array $lineas): void
    {
        DB::transaction(function () use ($salida, $lineas) {
            foreach ($lineas as $linea) {
                $producto = Producto::findOrFail($linea['producto_id']);
                $this->mover(
                    $producto,
                    $salida->origen,
                    'salida',
                    (float) $linea['cantidad'],
                    'salida_almacen',
                    $salida->id,
                    'Salida entre almacenes ' . $salida->documento,
                    (float) ($linea['precio_unitario'] ?? $producto->precio_costo)
                );
            }
        });
    }

    public function anularSalida(SalidaAlmacen $salida): void
    {
        DB::transaction(function () use ($salida) {
            $salida->load(['detalles.producto', 'origen', 'devoluciones']);

            if ($salida->estado === SalidaAlmacen::ANULADA) {
                throw new RuntimeException('La salida ya está anulada.');
            }
            if ($salida->devoluciones->where('estado', 'registrada')->isNotEmpty()) {
                throw new RuntimeException(
                    'No se puede anular una salida que ya tiene devoluciones registradas. ' .
                    'Revise primero el historial de devoluciones.'
                );
            }

            foreach ($salida->detalles as $detalle) {
                $this->mover(
                    $detalle->producto,
                    $salida->origen,
                    'entrada',
                    (float) $detalle->cantidad,
                    'anulacion_salida',
                    $salida->id,
                    'Anulación de salida ' . $salida->documento,
                    (float) $detalle->precio_unitario
                );
            }

            $salida->update(['estado' => SalidaAlmacen::ANULADA]);
        });
    }

    public function registrarDevolucion(SalidaAlmacen $salida, array $data): DevolucionSalida
    {
        return DB::transaction(function () use ($salida, $data) {
            $salida = SalidaAlmacen::whereKey($salida->id)
                ->lockForUpdate()
                ->with(['detalles.producto', 'origen'])
                ->firstOrFail();

            if ($salida->estado === SalidaAlmacen::ANULADA) {
                throw new RuntimeException('No se puede devolver una salida anulada.');
            }

            $devolucion = DevolucionSalida::create([
                'salida_almacen_id' => $salida->id,
                'documento' => $data['documento'],
                'fecha' => $data['fecha'],
                'motivo' => $data['motivo'] ?? null,
                'usuario_id' => auth()->id(),
                'almacen_recepcion_id' => $salida->almacen_origen_id,
                'estado' => 'registrada',
            ]);

            $totalDevueltoPorDetalle = DevolucionSalidaDetalle::whereHas(
                'devolucion',
                fn ($q) => $q->where('salida_almacen_id', $salida->id)->where('estado', 'registrada')
            )->selectRaw('salida_almacen_detalle_id, SUM(cantidad_devuelta) AS total')
                ->groupBy('salida_almacen_detalle_id')
                ->pluck('total', 'salida_almacen_detalle_id');

            $lineasValidas = 0;
            foreach ($salida->detalles as $detalle) {
                $cantidad = (float) ($data['cantidades'][$detalle->id] ?? 0);
                if ($cantidad <= 0) {
                    continue;
                }

                $yaDevuelto = (float) ($totalDevueltoPorDetalle[$detalle->id] ?? 0);
                $disponible = round((float) $detalle->cantidad - $yaDevuelto, 2);
                if ($cantidad > $disponible + 0.0001) {
                    throw new RuntimeException(
                        "La devolución de {$detalle->descripcion_original} supera lo disponible. " .
                        'Disponible: ' . number_format($disponible, 2) . '.'
                    );
                }

                $precio = (float) $detalle->precio_unitario;
                DevolucionSalidaDetalle::create([
                    'devolucion_salida_id' => $devolucion->id,
                    'salida_almacen_detalle_id' => $detalle->id,
                    'producto_id' => $detalle->producto_id,
                    'cantidad_devuelta' => $cantidad,
                    'precio_unitario' => $precio,
                    'monto' => round($cantidad * $precio, 2),
                ]);

                $this->mover(
                    $detalle->producto,
                    $salida->origen,
                    'entrada',
                    $cantidad,
                    'devolucion_salida',
                    $devolucion->id,
                    'Devolución de salida ' . $salida->documento,
                    $precio
                );
                $lineasValidas++;
            }

            if ($lineasValidas === 0) {
                throw new RuntimeException('Debe indicar una cantidad mayor que cero en al menos un producto.');
            }

            $totales = DevolucionSalidaDetalle::whereHas(
                'devolucion',
                fn ($q) => $q->where('salida_almacen_id', $salida->id)->where('estado', 'registrada')
            )->selectRaw('salida_almacen_detalle_id, SUM(cantidad_devuelta) AS total')
                ->groupBy('salida_almacen_detalle_id')
                ->pluck('total', 'salida_almacen_detalle_id');

            $completa = $salida->detalles->every(
                fn ($detalle) => (float) ($totales[$detalle->id] ?? 0) >= (float) $detalle->cantidad - 0.0001
            );
            $salida->update([
                'estado' => $completa
                    ? SalidaAlmacen::DEVUELTA_COMPLETAMENTE
                    : SalidaAlmacen::PARCIALMENTE_DEVUELTA,
            ]);

            return $devolucion;
        });
    }
}