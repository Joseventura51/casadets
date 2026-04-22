<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MovimientoController;
use App\Http\Controllers\VendedorController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\VentaImportController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\CompraController;

Route::get('/', [HomeController::class, 'index']);

Route::view('/zendy/letras', 'zendy.letras');
Route::view('/reportes', 'reportes');

// Movimientos (ingresos / salidas)
Route::get('/movimientos', [MovimientoController::class, 'index']);
Route::get('/movimientos/create/{tipo}', [MovimientoController::class, 'create']);
Route::post('/movimientos', [MovimientoController::class, 'store']);

// CASADETS — Caja
Route::get('/casadets/caja', [CajaController::class, 'index']);

// CASADETS — Vendedores
Route::get('/casadets/vendedores', [VendedorController::class, 'index']);
Route::get('/casadets/vendedores/create', [VendedorController::class, 'create']);
Route::post('/casadets/vendedores', [VendedorController::class, 'store']);
Route::get('/casadets/vendedores/{vendedor}/edit', [VendedorController::class, 'edit']);
Route::put('/casadets/vendedores/{vendedor}', [VendedorController::class, 'update']);
Route::delete('/casadets/vendedores/{vendedor}', [VendedorController::class, 'destroy']);

// CASADETS — Ventas
Route::get('/casadets/ventas', [VentaController::class, 'index']);
Route::get('/casadets/ventas/create', [VentaController::class, 'create']);
Route::post('/casadets/ventas', [VentaController::class, 'store']);
Route::get('/casadets/ventas/import', [VentaImportController::class, 'form']);
Route::post('/casadets/ventas/import', [VentaImportController::class, 'preview']);
Route::post('/casadets/ventas/import/confirm', [VentaImportController::class, 'confirm']);
Route::get('/casadets/ventas/{venta}', [VentaController::class, 'show']);
Route::get('/casadets/ventas/{venta}/edit', [VentaController::class, 'edit']);
Route::put('/casadets/ventas/{venta}', [VentaController::class, 'update']);
Route::delete('/casadets/ventas/{venta}', [VentaController::class, 'destroy']);

// CASADETS — Compras
Route::get('/casadets/compras', [CompraController::class, 'index']);
Route::get('/casadets/compras/create', [CompraController::class, 'create']);
Route::post('/casadets/compras', [CompraController::class, 'store']);
Route::get('/casadets/compras/{compra}', [CompraController::class, 'show']);
Route::get('/casadets/compras/{compra}/edit', [CompraController::class, 'edit']);
Route::put('/casadets/compras/{compra}', [CompraController::class, 'update']);
Route::delete('/casadets/compras/{compra}', [CompraController::class, 'destroy']);
