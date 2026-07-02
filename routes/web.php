<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MovimientoController;
use App\Http\Controllers\VendedorController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\VentaImportController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\CajaSelectorController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\SaldoFavorController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Admin\RolController;
use App\Http\Controllers\Admin\CajaAdminController;
use App\Http\Controllers\Admin\SerieController;
use App\Http\Controllers\DevolucionController;

// ── Autenticación (rutas públicas) ─────────────────────────────────────────
Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ── Rutas protegidas ────────────────────────────────────────────────────────
Route::middleware(['auth', 'check.activo'])->group(function () {

    Route::get('/', [HomeController::class, 'index'])->middleware('auth');

    // ── Selector de caja (AJAX o POST) ──────────────────────────────────────
    Route::post('/caja/seleccionar',     [CajaSelectorController::class, 'seleccionar']);
    Route::get('/caja/disponibles.json', [CajaSelectorController::class, 'disponibles']);

    Route::view('/zendy/letras', 'zendy.letras')->middleware('rol:zendy');

    // Reportes de Caja
    Route::middleware('rol:reportes-caja')->group(function () {
        Route::get('/casadets/reportes-caja',                    [\App\Http\Controllers\ReporteCajaController::class, 'index']);
        Route::get('/casadets/reportes-caja/{reporte}/descargar',[\App\Http\Controllers\ReporteCajaController::class, 'descargar']);
        Route::post('/casadets/reportes-caja/{reporte}/regenerar',[\App\Http\Controllers\ReporteCajaController::class, 'regenerar']);
    });

    // Reportes
    Route::middleware('rol:reportes')->group(function () {
        Route::get('/reportes',                  [ReporteController::class, 'index']);
        Route::get('/reportes/datos',            [ReporteController::class, 'datos']);
        Route::get('/reportes/utilidad-detalle', [ReporteController::class, 'utilidadDetalle']);
        Route::get('/reportes/ventas-detalle',   [ReporteController::class, 'ventasDetalle']);
        Route::get('/reportes/compras-detalle',  [ReporteController::class, 'comprasDetalle']);
        Route::get('/reportes/export-excel',     [ReporteController::class, 'exportExcel']);
        Route::get('/reportes/export-pdf',       [ReporteController::class, 'exportPdf']);

        Route::get('/reportes/semanales',         [\App\Http\Controllers\ReporteSemanalController::class, 'index']);
        Route::get('/reportes/semanales/preview', [\App\Http\Controllers\ReporteSemanalController::class, 'preview']);
        Route::post('/reportes/semanales/cerrar', [\App\Http\Controllers\ReporteSemanalController::class, 'cerrar']);
        Route::get('/reportes/semanales/{id}',    [\App\Http\Controllers\ReporteSemanalController::class, 'detalle']);
    });

    // Movimientos
    Route::middleware('rol:movimientos')->group(function () {
        Route::get('/movimientos',                          [MovimientoController::class, 'index']);
        Route::get('/movimientos/create/{tipo}',            [MovimientoController::class, 'create']);
        Route::post('/movimientos',                         [MovimientoController::class, 'store']);
        Route::post('/movimientos/{movimiento}/anular',     [MovimientoController::class, 'anular']);
    });

    // Caja
    Route::middleware('rol:caja')->group(function () {
        Route::get('/casadets/caja',           [CajaController::class, 'index']);
        Route::post('/casadets/caja/apertura', [CajaController::class, 'apertura']);
        Route::post('/casadets/caja/cierre',   [CajaController::class, 'cierre']);
    });

    // Vendedores
    Route::middleware('rol:vendedores')->group(function () {
        Route::get('/casadets/vendedores',                    [VendedorController::class, 'index']);
        Route::get('/casadets/vendedores/create',             [VendedorController::class, 'create']);
        Route::post('/casadets/vendedores',                   [VendedorController::class, 'store']);
        Route::get('/casadets/vendedores/{vendedor}/edit',    [VendedorController::class, 'edit']);
        Route::put('/casadets/vendedores/{vendedor}',         [VendedorController::class, 'update']);
        Route::delete('/casadets/vendedores/{vendedor}',      [VendedorController::class, 'destroy']);
    });

    // Pendientes (requiere caja abierta para acceder)
    Route::get('/casadets/pendientes', [VentaController::class, 'pendientes'])
        ->middleware(['rol:pendientes', 'caja.abierta']);

    // Ventas
    // Nivel 1: rol:ventas   → acceso al módulo Ventas
    // Nivel 2: permiso:X    → permiso de acción dentro de Ventas
    // Nivel 3: caja.abierta → acción que requiere caja abierta
    // IMPORTANTE: todas las rutas estáticas ANTES del wildcard {venta}
    Route::middleware('rol:ventas')->group(function () {

        // ── Sólo lectura ───────────────────────────────────────────────────
        Route::get('/casadets/ventas',        [VentaController::class, 'index']);
        Route::get('/casadets/ventas/export', [VentaController::class, 'export']);

        // ── Crear venta ────────────────────────────────────────────────────
        Route::get('/casadets/ventas/create',  [VentaController::class, 'create'])
            ->middleware(['permiso:ventas.crear', 'caja.abierta']);
        Route::post('/casadets/ventas',        [VentaController::class, 'store'])
            ->middleware(['permiso:ventas.crear', 'caja.abierta']);

        // ── Pago múltiple (estático, antes de {venta}) ────────────────────
        Route::get('/casadets/ventas/pago-multiple',  [VentaController::class, 'pagoMultiple'])
            ->middleware(['permiso:ventas.pago', 'caja.abierta']);
        Route::post('/casadets/ventas/pago-multiple', [VentaController::class, 'updatePagoMultiple'])
            ->middleware(['permiso:ventas.pago', 'caja.abierta']);

        // ── Importar ventas (estático, antes de {venta}) ──────────────────
        Route::get('/casadets/ventas/import',          [VentaImportController::class, 'form'])
            ->middleware('permiso:ventas.importar');
        Route::post('/casadets/ventas/import',         [VentaImportController::class, 'preview'])
            ->middleware(['permiso:ventas.importar', 'caja.abierta']);
        Route::post('/casadets/ventas/import/confirm', [VentaImportController::class, 'confirm'])
            ->middleware(['permiso:ventas.importar', 'caja.abierta']);

        // ── Wildcard {venta} — sólo lectura ───────────────────────────────
        Route::get('/casadets/ventas/{venta}', [VentaController::class, 'show']);

        // ── Editar venta ───────────────────────────────────────────────────
        Route::get('/casadets/ventas/{venta}/edit',    [VentaController::class, 'edit'])
            ->middleware('permiso:ventas.editar');
        Route::put('/casadets/ventas/{venta}',         [VentaController::class, 'update'])
            ->middleware(['permiso:ventas.editar', 'caja.abierta']);
        Route::post('/casadets/ventas/{venta}/estado', [VentaController::class, 'updateEstado'])
            ->middleware(['permiso:ventas.editar', 'caja.abierta']);

        // ── Eliminar venta ─────────────────────────────────────────────────
        Route::delete('/casadets/ventas/{venta}', [VentaController::class, 'destroy'])
            ->middleware(['permiso:ventas.eliminar', 'caja.abierta']);

        // ── Registrar pago ─────────────────────────────────────────────────
        Route::get('/casadets/ventas/{venta}/pago',           [VentaController::class, 'pago'])
            ->middleware('permiso:ventas.pago');
        Route::post('/casadets/ventas/{venta}/pago',          [VentaController::class, 'updatePago'])
            ->middleware(['permiso:ventas.pago', 'caja.abierta']);
        Route::post('/casadets/ventas/{venta}/reducir-saldo', [VentaController::class, 'reducirSaldo'])
            ->middleware(['permiso:ventas.pago', 'caja.abierta']);
    });

    // Devoluciones y Anulaciones
    Route::middleware('rol:devoluciones')->group(function () {
        Route::get('/casadets/devoluciones',                         [DevolucionController::class, 'index']);
        Route::get('/casadets/devoluciones/{venta}',                 [DevolucionController::class, 'show']);
        Route::post('/casadets/devoluciones/{venta}',                [DevolucionController::class, 'store'])
            ->middleware('permiso:devoluciones.procesar');
        Route::post('/casadets/devoluciones/{venta}/anular',         [DevolucionController::class, 'anular'])
            ->middleware('permiso:devoluciones.procesar');
    });

    // Saldos a favor
    Route::middleware('rol:saldos-favor')->group(function () {
        Route::get('/casadets/saldos-favor',                                 [SaldoFavorController::class, 'index']);
        Route::get('/casadets/saldos-favor/clientes.json',                   [SaldoFavorController::class, 'clientesJson']);
        Route::get('/casadets/saldos-favor/notas-credito.json',              [SaldoFavorController::class, 'notasCreditoDisponibles']);
        Route::get('/casadets/saldos-favor/cliente/{clienteId}/saldos.json', [SaldoFavorController::class, 'saldosCliente']);
        Route::get('/casadets/saldos-favor/cliente/{clienteId}/ventas.json', [SaldoFavorController::class, 'ventasPendientesCliente']);

        Route::post('/casadets/saldos-favor/crear',                          [SaldoFavorController::class, 'crear'])           ->middleware('caja.abierta');
        Route::post('/casadets/saldos-favor/nc/{venta}/cliente',             [SaldoFavorController::class, 'asignarClienteNC'])->middleware('caja.abierta');
        Route::post('/casadets/saldos-favor/nc/{venta}/convertir',           [SaldoFavorController::class, 'convertirNC'])     ->middleware('caja.abierta');
        Route::post('/casadets/saldos-favor/{saldo}/aplicar',                [SaldoFavorController::class, 'aplicar'])         ->middleware('caja.abierta');
        Route::post('/casadets/saldos-favor/{saldo}/anular',                 [SaldoFavorController::class, 'anular']);
    });

    // Clientes
    Route::middleware('rol:clientes')->group(function () {
        Route::get('/casadets/clientes',                [ClienteController::class, 'index']);
        Route::get('/casadets/clientes/create',         [ClienteController::class, 'create']);
        Route::post('/casadets/clientes',               [ClienteController::class, 'store']);
        Route::get('/casadets/clientes/{cliente}/edit', [ClienteController::class, 'edit']);
        Route::put('/casadets/clientes/{cliente}',      [ClienteController::class, 'update']);
        Route::delete('/casadets/clientes/{cliente}',   [ClienteController::class, 'destroy']);
    });

    // Productos
    Route::middleware('rol:productos')->group(function () {
        Route::get('/casadets/productos',                    [ProductoController::class, 'index']);
        Route::get('/casadets/productos/create',             [ProductoController::class, 'create']);
        Route::post('/casadets/productos',                   [ProductoController::class, 'store']);
        Route::get('/casadets/productos/{producto}',         [ProductoController::class, 'show']);
        Route::get('/casadets/productos/{producto}/edit',    [ProductoController::class, 'edit']);
        Route::put('/casadets/productos/{producto}',         [ProductoController::class, 'update']);
        Route::post('/casadets/productos/{producto}/ajuste', [ProductoController::class, 'storeAjuste']);
    });

    // Compras
    Route::middleware('rol:compras')->group(function () {
        Route::get('/casadets/ventas/{venta}/detalles.json', [CompraController::class, 'detallesVenta']);
        Route::get('/casadets/compras',                      [CompraController::class, 'index']);
        // create debe ir ANTES de {compra} para que el segmento literal no sea capturado por el wildcard
        Route::get('/casadets/compras/create',               [CompraController::class, 'create']);
        Route::get('/casadets/compras/{compra}',             [CompraController::class, 'show']);
        Route::get('/casadets/compras/{compra}/edit',        [CompraController::class, 'edit']);

        Route::middleware('caja.abierta')->group(function () {
            Route::post('/casadets/compras',           [CompraController::class, 'store']);
            Route::put('/casadets/compras/{compra}',   [CompraController::class, 'update']);
            Route::delete('/casadets/compras/{compra}',[CompraController::class, 'destroy']);
        });
    });

    // ── Administración ────────────────────────────────────────────────────────
    Route::prefix('admin')->group(function () {

        // Usuarios
        Route::middleware('rol:admin.usuarios')->group(function () {
            Route::get('/usuarios',                    [UsuarioController::class, 'index']);
            Route::get('/usuarios/create',             [UsuarioController::class, 'create']);
            Route::post('/usuarios',                   [UsuarioController::class, 'store']);
            Route::get('/usuarios/{usuario}/edit',     [UsuarioController::class, 'edit']);
            Route::put('/usuarios/{usuario}',          [UsuarioController::class, 'update']);
            Route::patch('/usuarios/{usuario}/toggle', [UsuarioController::class, 'toggleActivo']);
        });

        // Roles
        Route::middleware('rol:admin.roles')->group(function () {
            Route::get('/roles',            [RolController::class, 'index']);
            Route::get('/roles/create',     [RolController::class, 'create']);
            Route::post('/roles',           [RolController::class, 'store']);
            Route::get('/roles/{rol}/edit', [RolController::class, 'edit']);
            Route::put('/roles/{rol}',      [RolController::class, 'update']);
            Route::delete('/roles/{rol}',   [RolController::class, 'destroy']);
        });

        // Cajas
        Route::middleware('rol:admin.cajas')->group(function () {
            Route::get('/cajas',                 [CajaAdminController::class, 'index']);
            Route::get('/cajas/create',          [CajaAdminController::class, 'create']);
            Route::post('/cajas',                [CajaAdminController::class, 'store']);
            Route::get('/cajas/{caja}/edit',     [CajaAdminController::class, 'edit']);
            Route::put('/cajas/{caja}',          [CajaAdminController::class, 'update']);
            Route::patch('/cajas/{caja}/toggle', [CajaAdminController::class, 'toggleActiva']);
        });

        // Series
        Route::middleware('rol:admin.series')->group(function () {
            Route::get('/series',                [SerieController::class, 'index']);
            Route::get('/series/create',         [SerieController::class, 'create']);
            Route::post('/series',               [SerieController::class, 'store']);
            Route::get('/series/{serie}/edit',   [SerieController::class, 'edit']);
            Route::put('/series/{serie}',        [SerieController::class, 'update']);
            Route::delete('/series/{serie}',     [SerieController::class, 'destroy']);
            Route::get('/series/caja/{caja}.json', [SerieController::class, 'porCaja']);
        });

    });

});
