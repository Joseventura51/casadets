<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MovimientoController;
use App\Http\Controllers\VendedorController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\VentaImportController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\SaldoFavorController;
use App\Http\Controllers\ReporteController;

Route::get('/', [HomeController::class, 'index']);

Route::view('/zendy/letras', 'zendy.letras');

// Reportes
Route::get('/reportes',               [ReporteController::class, 'index']);
Route::get('/reportes/datos',         [ReporteController::class, 'datos']);
Route::get('/reportes/utilidad-detalle', [ReporteController::class, 'utilidadDetalle']);
Route::get('/reportes/export-excel',  [ReporteController::class, 'exportExcel']);

// Movimientos (ingresos / salidas)
Route::get('/movimientos', [MovimientoController::class, 'index']);
Route::get('/movimientos/create/{tipo}', [MovimientoController::class, 'create']);
Route::post('/movimientos', [MovimientoController::class, 'store']);

// CASADETS — Caja
Route::get('/casadets/caja', [CajaController::class, 'index']);
Route::post('/casadets/caja/apertura', [CajaController::class, 'apertura']);
Route::post('/casadets/caja/cierre', [CajaController::class, 'cierre']);

// CASADETS — Vendedores
Route::get('/casadets/vendedores', [VendedorController::class, 'index']);
Route::get('/casadets/vendedores/create', [VendedorController::class, 'create']);
Route::post('/casadets/vendedores', [VendedorController::class, 'store']);
Route::get('/casadets/vendedores/{vendedor}/edit', [VendedorController::class, 'edit']);
Route::put('/casadets/vendedores/{vendedor}', [VendedorController::class, 'update']);
Route::delete('/casadets/vendedores/{vendedor}', [VendedorController::class, 'destroy']);

// CASADETS — Pendientes
Route::get('/casadets/pendientes', [VentaController::class, 'pendientes']);

// CASADETS — Ventas
Route::get('/casadets/ventas/export', [VentaController::class, 'export']);
Route::get('/casadets/ventas/pago-multiple', [VentaController::class, 'pagoMultiple']);
Route::post('/casadets/ventas/pago-multiple', [VentaController::class, 'updatePagoMultiple']);
Route::get('/casadets/ventas', [VentaController::class, 'index']);
Route::get('/casadets/ventas/create', [VentaController::class, 'create']);
Route::post('/casadets/ventas', [VentaController::class, 'store']);
Route::get('/casadets/ventas/import', [VentaImportController::class, 'form']);
Route::post('/casadets/ventas/import', [VentaImportController::class, 'preview']);
Route::post('/casadets/ventas/import/confirm', [VentaImportController::class, 'confirm']);
Route::get('/casadets/ventas/{venta}', [VentaController::class, 'show']);
Route::get('/casadets/ventas/{venta}/edit', [VentaController::class, 'edit']);
Route::put('/casadets/ventas/{venta}', [VentaController::class, 'update']);
Route::get('/casadets/ventas/{venta}/pago', [VentaController::class, 'pago']);
Route::post('/casadets/ventas/{venta}/pago', [VentaController::class, 'updatePago']);
Route::post('/casadets/ventas/{venta}/estado', [VentaController::class, 'updateEstado']);
Route::delete('/casadets/ventas/{venta}', [VentaController::class, 'destroy']);

// CASADETS — Saldos a favor
Route::get('/casadets/saldos-favor', [SaldoFavorController::class, 'index']);
Route::get('/casadets/saldos-favor/clientes.json', [SaldoFavorController::class, 'clientesJson']);
Route::get('/casadets/saldos-favor/notas-credito.json', [SaldoFavorController::class, 'notasCreditoDisponibles']);
Route::post('/casadets/saldos-favor/crear', [SaldoFavorController::class, 'crear']);
Route::post('/casadets/saldos-favor/nc/{venta}/convertir', [SaldoFavorController::class, 'convertirNC']);
Route::get('/casadets/saldos-favor/cliente/{clienteId}/saldos.json', [SaldoFavorController::class, 'saldosCliente']);
Route::get('/casadets/saldos-favor/cliente/{clienteId}/ventas.json', [SaldoFavorController::class, 'ventasPendientesCliente']);
Route::post('/casadets/saldos-favor/{saldo}/aplicar', [SaldoFavorController::class, 'aplicar']);

// CASADETS — Clientes
Route::get('/casadets/clientes', [ClienteController::class, 'index']);
Route::get('/casadets/clientes/create', [ClienteController::class, 'create']);
Route::post('/casadets/clientes', [ClienteController::class, 'store']);
Route::get('/casadets/clientes/{cliente}/edit', [ClienteController::class, 'edit']);
Route::put('/casadets/clientes/{cliente}', [ClienteController::class, 'update']);
Route::delete('/casadets/clientes/{cliente}', [ClienteController::class, 'destroy']);

// CASADETS — Productos (CRUD + Kardex + Ajuste)
Route::get('/casadets/productos', [ProductoController::class, 'index']);
Route::get('/casadets/productos/create', [ProductoController::class, 'create']);
Route::post('/casadets/productos', [ProductoController::class, 'store']);
Route::get('/casadets/productos/{producto}', [ProductoController::class, 'show']);
Route::get('/casadets/productos/{producto}/edit', [ProductoController::class, 'edit']);
Route::put('/casadets/productos/{producto}', [ProductoController::class, 'update']);
Route::post('/casadets/productos/{producto}/ajuste', [ProductoController::class, 'storeAjuste']);

// CASADETS — Compras
Route::get('/casadets/ventas/{venta}/detalles.json', [CompraController::class, 'detallesVenta']);
Route::get('/casadets/compras', [CompraController::class, 'index']);
Route::get('/casadets/compras/create', [CompraController::class, 'create']);
Route::post('/casadets/compras', [CompraController::class, 'store']);
Route::get('/casadets/compras/{compra}', [CompraController::class, 'show']);
Route::get('/casadets/compras/{compra}/edit', [CompraController::class, 'edit']);
Route::put('/casadets/compras/{compra}', [CompraController::class, 'update']);
Route::delete('/casadets/compras/{compra}', [CompraController::class, 'destroy']);
