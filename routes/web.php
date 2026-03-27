<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MovimientoController;

Route::get('/', function () {
    return view('home');
});

Route::view('/ingresos', 'ingresos');
Route::view('/salidas', 'salidas');
Route::view('/zendy/letras', 'zendy.letras');
Route::view('/reportes', 'reportes');
Route::view('/ventas', 'ventas');

// listado general
Route::get('/movimientos', [MovimientoController::class, 'index']);

// formularios por tipo
Route::get('/movimientos/create/{tipo}', [MovimientoController::class, 'create']);

// guardar
Route::post('/movimientos', [MovimientoController::class, 'store']);