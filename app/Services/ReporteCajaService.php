<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\CajaSesion;
use App\Models\DetallePagoFactura;
use App\Models\Devolucion;
use App\Models\Movimiento;
use App\Models\PagoMetodo;
use App\Models\ReporteCaja;
use App\Models\Serie;
use App\Models\Venta;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ReporteCajaService
{
    private const AZUL    = '2563EB';
    private const VERDE   = 'D1FAE5';
    private const ROJO    = 'FEE2E2';
    private const AMARILLO = 'FEF08A';
    private const GRIS    = 'E9ECEF';
    private const BLANCO  = 'FFFFFF';

    /**
     * Genera el Excel de cierre para una sesión y guarda el registro en BD.
     * Retorna el ReporteCaja creado.
     */
    public static function generar(CajaSesion $sesion): ReporteCaja
    {
        $caja  = $sesion->caja;
        $fecha = $sesion->fecha instanceof \Carbon\Carbon
            ? $sesion->fecha
            : \Carbon\Carbon::parse($sesion->fecha);

        // ── Recopilar datos ───────────────────────────────────────────────
        [$ventas, $movimientos, $kpis, $metodos, $porVendedor, $devoluciones, $ventasAnuladas, $movimientosDetalle] = self::recopilarDatos($sesion, $caja, $fecha);

        // ── Generar Excel ─────────────────────────────────────────────────
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle("Reporte Caja {$caja?->codigo} {$fecha->format('d/m/Y')}");

        self::hojaSesion($spreadsheet, $sesion, $caja, $fecha, $kpis, $metodos, $porVendedor, $ventas, $movimientosDetalle, $devoluciones, $ventasAnuladas);
        self::hojaVentas($spreadsheet, $ventas);
        self::hojaMovimientos($spreadsheet, $movimientosDetalle, (float) $sesion->monto_apertura);

        $spreadsheet->setActiveSheetIndex(0);

        // ── Guardar archivo ───────────────────────────────────────────────
        // El disco 'local' tiene root = storage_path('app/private'),
        // así que $ruta es relativa a ese root (sin incluir "private/").
        // La ruta física absoluta sí incluye /private/.
        $subdirectorio = 'reportes_caja';
        $dirAbs        = storage_path("app/private/{$subdirectorio}");
        if (!is_dir($dirAbs)) {
            mkdir($dirAbs, 0775, true);
        }

        $nombre  = "reporte_caja_{$caja?->id}_{$sesion->id}_{$fecha->format('Y-m-d')}.xlsx";
        $ruta    = "{$subdirectorio}/{$nombre}";          // relativa al disk 'local'
        $rutaAbs = storage_path("app/private/{$ruta}");   // ruta física absoluta

        $writer = new Xlsx($spreadsheet);
        $writer->save($rutaAbs);

        // ── Crear / actualizar registro ───────────────────────────────────
        $reporte = ReporteCaja::updateOrCreate(
            ['caja_sesion_id' => $sesion->id],
            [
                'caja_id'          => $caja?->id,
                'fecha'            => $fecha->toDateString(),
                'archivo'          => $ruta,
                'generado_at'      => now(),
                'total_cobradas'   => $kpis['total_cobradas'],
                'total_otros'      => $kpis['total_otros'],
                'total_salidas'    => $kpis['total_salidas'],
                'balance'          => $kpis['balance'],
                'efectivo_esperado'=> $kpis['efectivo_en_caja'],
            ]
        );

        return $reporte;
    }

    // ── Recopilar datos ───────────────────────────────────────────────────

    private static function recopilarDatos(CajaSesion $sesion, ?Caja $caja, \Carbon\Carbon $fecha): array
    {
        [$inicioSesion, $finSesion] = self::limitesSesion($sesion, $fecha);

        // Movimientos — acotados al rango temporal real de esta sesión.
        // No filtrar por la fecha de negocio: una sesión puede cruzar medianoche
        // y debe incluir todo lo registrado hasta su cierre.
        $movQuery = Movimiento::with('cliente:id,nombre')
            ->where('movimientos.created_at', '>=', $inicioSesion)
            ->where('movimientos.created_at', '<=', $finSesion);
        if ($caja) {
            $movQuery->where('caja_id', $caja->id);
        } else {
            $movQuery->where('empresa', $sesion->empresa)->whereNull('caja_id');
        }
        $movimientos = $movQuery->orderBy('id')->get();
        $movActivos  = $movimientos->where('estado', 'activo');

        // Ventas creadas durante esta sesión. Las ventas creadas antes de la
        // apertura, pero cobradas aquí, se agregan más abajo a partir de sus
        // movimientos de pago.
        $ventasQuery = Venta::with(['vendedor', 'cliente', 'detalles'])
            ->where('ventas.created_at', '>=', $inicioSesion)
            ->where('ventas.created_at', '<=', $finSesion);
        if ($caja) {
            $seriesCodigos = Serie::where('caja_id', $caja->id)->pluck('codigo');
            if ($seriesCodigos->isNotEmpty()) {
                $ventasQuery->where(function ($q) use ($caja, $seriesCodigos) {
                    foreach ($seriesCodigos as $cod) {
                        $q->orWhere('documento_numero', 'like', $cod . '-%');
                    }
                    $q->orWhere(fn ($q2) => $q2->where('caja_id', $caja->id)->whereNull('documento_numero'));
                });
            } else {
                $ventasQuery->where('caja_id', $caja->id);
            }
        }
        $ventas = $ventasQuery->orderBy('id')->get();

        // Complementar con ventas cobradas en esta sesión según los movimientos pago_venta
        // (cubre casos donde la venta no tiene caja_id o serie asignada)
        $pagoMovIds = $movActivos
            ->where('subtipo', 'pago_venta')
            ->where('referencia_tipo', 'pago')
            ->pluck('referencia_id')->filter()->unique();

        if ($pagoMovIds->isNotEmpty()) {
            $ventaIdsFromPagos = DetallePagoFactura::whereIn('pago_id', $pagoMovIds)
                ->pluck('venta_id')->unique();
            $missingIds = $ventaIdsFromPagos->diff($ventas->pluck('id'));
            if ($missingIds->isNotEmpty()) {
                $ventasExtra = Venta::with(['vendedor', 'cliente', 'detalles'])
                    ->whereIn('id', $missingIds)->get();
                $ventas = $ventas->merge($ventasExtra)->sortBy('id')->values();
            }
        }

        // El estado y pagado de una venta son acumulados y pueden cambiar en
        // sesiones posteriores. Para este reporte usamos solo lo aplicado por
        // pagos pertenecientes a la ventana de la sesión actual.
        $cobradoPorVenta = $pagoMovIds->isNotEmpty()
            ? DetallePagoFactura::whereIn('pago_id', $pagoMovIds)
                ->selectRaw('venta_id, SUM(monto_aplicado) as monto')
                ->groupBy('venta_id')
                ->pluck('monto', 'venta_id')
            : collect();

        $ventas->each(function ($venta) use ($cobradoPorVenta, $pagoMovIds) {
            $monto = $cobradoPorVenta->get($venta->id);

            // Compatibilidad con ventas históricas que no tienen movimiento
            // de pago: solo se usa el acumulado si la venta nació en esta
            // sesión, nunca para una venta de otro turno.
            if ($monto === null && $pagoMovIds->isEmpty() && $venta->created_at) {
                $monto = in_array($venta->estado, ['pagado', 'parcial'], true)
                    ? $venta->pagado
                    : 0;
            }

            $venta->setAttribute('reporte_cobrado', round((float) ($monto ?? 0), 2));
        });

        $ventasCobradas   = $ventas->filter(fn ($v) => (float) $v->reporte_cobrado > 0);
        $ventasCanjeadas  = $ventas->where('estado', 'canjeada');

        // Solo sumar lo cobrado dentro de esta sesión. No usar Venta.pagado
        // directamente porque contiene el acumulado de toda la vida de la venta.
        $totalCobradas   = round(
            $ventasCobradas->sum(fn ($v) => (float) $v->reporte_cobrado),
            2
        );
        $totalOtros      = round($movActivos->filter(fn ($m) => $m->tipo === 'ingreso' && $m->subtipo !== 'pago_venta')->sum('monto'), 2);
        $totalSalidas    = round($movActivos->where('tipo', 'salida')->sum('monto'), 2);
        $balance         = round($totalCobradas + $totalOtros - $totalSalidas, 2);

        // Métodos de pago
        $metodos = self::calcularMetodos($movActivos, $ventasCobradas);
        $movimientosDetalle = self::desglosarMovimientosPorMetodo($movimientos);
        $flujo = self::calcularFlujo($movimientosDetalle, $metodos);

        // Efectivo esperado en caja al momento del cierre
        $efectivoEnCaja = round(
            (float) $sesion->monto_apertura
            + $flujo['ingresos_efectivo']
            - $flujo['salidas_efectivo'],
            2
        );

        $diferencia = $sesion->monto_cierre !== null
            ? round((float)$sesion->monto_cierre - $efectivoEnCaja, 2)
            : null;

        // KPIs adicionales
        $ventasPagadasList  = $ventasCobradas;
        $ticketPromedio     = $ventasPagadasList->count() > 0
            ? round($ventasPagadasList->sum(fn ($v) => (float) $v->reporte_cobrado) / $ventasPagadasList->count(), 2)
            : 0;
        $mayorVenta = $ventasPagadasList->max(fn ($v) => (float) $v->reporte_cobrado) ?? 0;
        $menorVenta = $ventasPagadasList->min(fn ($v) => (float) $v->reporte_cobrado) ?? 0;

        $kpis = [
            'monto_apertura'      => (float) $sesion->monto_apertura,
            'monto_cierre'        => $sesion->monto_cierre !== null ? (float) $sesion->monto_cierre : null,
            'efectivo_en_caja'    => $efectivoEnCaja,
            'efectivo_inicial'    => (float) $sesion->monto_apertura,
            'efectivo_ingresos'   => $flujo['ingresos_efectivo'],
            'depositos_ingresos'  => $flujo['ingresos_depositos'],
            'efectivo_salidas'    => $flujo['salidas_efectivo'],
            'depositos_salidas'   => $flujo['salidas_depositos'],
            'diferencia'          => $diferencia,
            'total_cobradas'      => $totalCobradas,
            'total_otros'         => $totalOtros,
            'total_salidas'       => $totalSalidas,
            'balance'             => $balance,
            'total_ventas'        => $ventas->count(),
            'ventas_cobradas'     => $ventasCobradas->count(),
            'ventas_canjeadas'    => $ventasCanjeadas->count(),
            'ventas_pendientes'   => $ventas->whereIn('estado', ['pendiente','parcial'])->count(),
            'ticket_promedio'     => $ticketPromedio,
            'mayor_venta'         => $mayorVenta,
            'menor_venta'         => $menorVenta,
            'operaciones'         => $ventasPagadasList->count(),
            'hora_apertura'       => $sesion->created_at?->format('H:i'),
            'hora_cierre'         => $sesion->estado === 'cerrada' ? $sesion->updated_at?->format('H:i') : null,
        ];

        // Por vendedor
        $porVendedor = $ventasCobradas
            ->groupBy(fn ($v) => $v->vendedor->nombre ?? 'Sin vendedor')
            ->map(fn ($g) => round($g->sum(fn ($v) => (float) $v->reporte_cobrado), 2));

        // ── Devoluciones de esta sesión ───────────────────────────────────
        // La fecha de negocio no distingue dos sesiones del mismo día; created_at
        // delimita la devolución al turno que realmente la registró.
        $devQuery = Devolucion::with(['venta:id,documento_tipo,documento_numero', 'user:id,name'])
            ->where('devoluciones.created_at', '>=', $inicioSesion)
            ->where('devoluciones.created_at', '<=', $finSesion);
        if ($caja) {
            $devQuery->where(function ($q) use ($caja) {
                $q->where('devoluciones.caja_id', $caja->id)
                  ->orWhereNull('devoluciones.caja_id');
            });
        }
        $devoluciones = $devQuery->orderBy('devoluciones.id')->get();

        // ── Ventas anuladas en esta sesión ────────────────────────────────
        $ventasAnuladas = $ventas->where('estado', 'anulado');

        return [$ventas, $movimientos, $kpis, $metodos, $porVendedor, $devoluciones, $ventasAnuladas, $movimientosDetalle];
    }

    /**
     * Obtiene los límites reales de una sesión de caja.
     *
     * Una sesión cerrada termina en su updated_at, que es actualizado por
     * CajaService::cerrarCaja(). Para reportes de una sesión aún abierta se
     * usa el momento actual como límite superior.
     */
    private static function limitesSesion(CajaSesion $sesion, \Carbon\Carbon $fecha): array
    {
        $inicio = $sesion->created_at
            ? \Carbon\Carbon::parse($sesion->created_at)
            : $fecha->copy()->startOfDay();

        $fin = $sesion->updated_at && $sesion->estado === 'cerrada'
            ? \Carbon\Carbon::parse($sesion->updated_at)
            : now();

        return [$inicio, $fin];
    }

    /**
     * Divide los movimientos de un pago mixto en una fila por método.
     * Así un cobro mitad efectivo y mitad depósito no termina clasificado
     * entero en una sola categoría del Excel.
     */
    private static function desglosarMovimientosPorMetodo($movimientos): \Illuminate\Support\Collection
    {
        $pagoIds = $movimientos
            ->filter(fn ($m) => $m->subtipo === 'pago_venta' && $m->referencia_tipo === 'pago')
            ->pluck('referencia_id')
            ->filter()
            ->unique();

        $metodosPorPago = $pagoIds->isNotEmpty()
            ? PagoMetodo::whereIn('pago_id', $pagoIds)->get()->groupBy('pago_id')
            : collect();

        return $movimientos->flatMap(function ($movimiento) use ($metodosPorPago) {
            if ($movimiento->subtipo !== 'pago_venta' || $movimiento->referencia_tipo !== 'pago') {
                return [$movimiento];
            }

            $metodos = $metodosPorPago->get($movimiento->referencia_id, collect());
            if ($metodos->isEmpty()) {
                return [$movimiento];
            }

            return $metodos->map(function ($pagoMetodo) use ($movimiento) {
                $detalle = clone $movimiento;
                $detalle->metodo_pago = $pagoMetodo->metodo;
                $detalle->monto = $pagoMetodo->monto;
                $detalle->observaciones = trim(
                    ($movimiento->observaciones ?? '')
                    . ' [' . ucfirst($pagoMetodo->metodo) . ']'
                );
                return $detalle;
            })->all();
        })->values();
    }

    /**
     * Resume ingresos y salidas en efectivo frente a depósitos/medios
     * electrónicos. Los cobros de ventas vienen de $metodos para respetar
     * pagos mixtos; los demás ingresos y todas las salidas vienen del ledger.
     */
    private static function calcularFlujo($movimientos, $metodos): array
    {
        $otrosIngresos = $movimientos->filter(
            fn ($m) => $m->estado === 'activo'
                && $m->tipo === 'ingreso'
                && $m->subtipo !== 'pago_venta'
        );
        $salidas = $movimientos->filter(
            fn ($m) => $m->estado === 'activo' && $m->tipo === 'salida'
        );

        $ingresosEfectivo = $metodos->get('efectivo', 0)
            + $otrosIngresos->filter(fn ($m) => self::esEfectivo($m->metodo_pago))->sum('monto');
        $ingresosDepositos = $metodos->except(['efectivo'])->sum()
            + $otrosIngresos->reject(fn ($m) => self::esEfectivo($m->metodo_pago))->sum('monto');

        return [
            'ingresos_efectivo'  => round((float) $ingresosEfectivo, 2),
            'ingresos_depositos' => round((float) $ingresosDepositos, 2),
            'salidas_efectivo'   => round((float) $salidas->filter(fn ($m) => self::esEfectivo($m->metodo_pago))->sum('monto'), 2),
            'salidas_depositos'  => round((float) $salidas->reject(fn ($m) => self::esEfectivo($m->metodo_pago))->sum('monto'), 2),
        ];
    }

    private static function esEfectivo(?string $metodo): bool
    {
        return strtolower(trim((string) $metodo)) === 'efectivo';
    }

    /**
     * Misma lógica que CajaController::calcularMetodosDePago:
     *  1. Primario: movimientos pago_venta → PagoMetodo
     *  2. Fallback 1: DetallePagoFactura → PagoMetodo
     *  3. Fallback 2: ventas sin DetallePagoFactura → metodo_pago directo
     * Los tres pasos deben estar en ambos lugares para que el efectivo en
     * pantalla y en el reporte siempre coincidan.
     */
    private static function calcularMetodos($movActivos, $ventasCobradas): \Illuminate\Support\Collection
    {
        $pagoIds = $movActivos
            ->where('subtipo', 'pago_venta')
            ->where('referencia_tipo', 'pago')
            ->pluck('referencia_id')
            ->filter()->unique();

        if ($pagoIds->isNotEmpty()) {
            return PagoMetodo::whereIn('pago_id', $pagoIds)
                ->selectRaw('metodo, SUM(monto) as total')
                ->groupBy('metodo')
                ->pluck('total', 'metodo')
                ->map(fn ($t) => round((float) $t, 2))
                ->sortKeys();
        }

        // Fallback 1: pagos registrados en DetallePagoFactura
        $vIds = $ventasCobradas->pluck('id');
        $pIds = $vIds->isNotEmpty()
            ? DetallePagoFactura::whereIn('venta_id', $vIds)->pluck('pago_id')->unique()
            : collect();

        $metodosDePagos = $pIds->isNotEmpty()
            ? PagoMetodo::whereIn('pago_id', $pIds)
                ->selectRaw('metodo, SUM(monto) as total')
                ->groupBy('metodo')
                ->pluck('total', 'metodo')
                ->map(fn ($t) => round((float) $t, 2))
            : collect();

        // Fallback 2: ventas cobradas sin DetallePagoFactura → usar metodo_pago directo
        $ventasConPago = $pIds->isNotEmpty()
            ? DetallePagoFactura::whereIn('venta_id', $vIds)->pluck('venta_id')->unique()
            : collect();

        $ventasSinPago = $ventasCobradas->filter(
            fn ($v) => !$ventasConPago->contains($v->id) && !empty($v->metodo_pago)
        );

        $metodosDirectos = $ventasSinPago
            ->groupBy('metodo_pago')
            ->map(fn ($g) => round($g->sum(fn ($v) => (float) ($v->total_cobrado ?? $v->pagado ?? $v->total)), 2));

        // Combinar ambos
        $todos = $metodosDePagos->keys()->merge($metodosDirectos->keys())->unique();

        return $todos->mapWithKeys(fn ($m) => [
            $m => round(($metodosDePagos->get($m, 0)) + ($metodosDirectos->get($m, 0)), 2),
        ])->sortKeys();
    }

    // ── Hoja 1: Resumen de sesión ─────────────────────────────────────────

    private static function hojaSesion(
        Spreadsheet $sp,
        CajaSesion $sesion,
        ?Caja $caja,
        \Carbon\Carbon $fecha,
        array $kpis,
        $metodos,
        $porVendedor,
        $ventas         = null,
        $movimientos    = null,
        $devoluciones   = null,
        $ventasAnuladas = null
    ): void {
        $sheet = $sp->getActiveSheet();
        $sheet->setTitle('Resumen');

        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => self::BLANCO]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::AZUL]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ];
        $subHeaderStyle = [
            'font'      => ['bold' => true],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        $montoFormat = '#,##0.00';
        $COLS        = 7;   // ancho máximo de la sección detalle (A–G)

        $row = 1;

        // ── Bloque: Info de sesión ─────────────────────────────────────
        $fechaCierre = $sesion->estado === 'cerrada' && $sesion->updated_at
            ? \Carbon\Carbon::parse($sesion->updated_at)->format('d/m/Y')
            : $fecha->format('d/m/Y');

        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue("A{$row}", "REPORTE DE CIERRE DE CAJA — FECHA DE CIERRE: {$fechaCierre}");
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($headerStyle);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;

        self::fila2col($sheet, $row, 'Caja',  $caja?->codigo . ' — ' . $caja?->nombre);         $row++;
        self::fila2col($sheet, $row, 'Fecha', $fecha->format('d/m/Y'));                           $row++;
        self::fila2col($sheet, $row, 'Sesión ID', $sesion->id);                                  $row++;
        self::fila2col($sheet, $row, 'Estado', ucfirst($sesion->estado));                        $row++;
        self::fila2col($sheet, $row, 'Hora apertura', $kpis['hora_apertura'] ?? 'N/D');          $row++;
        if ($kpis['hora_cierre'] !== null) {
            self::fila2col($sheet, $row, 'Hora cierre', $kpis['hora_cierre']);                   $row++;
        }
        self::fila2col($sheet, $row, 'Apertura (S/)', $kpis['monto_apertura'], $montoFormat);    $row++;
        self::fila2col($sheet, $row, 'Cierre declarado (S/)',
            $kpis['monto_cierre'] ?? 'N/D', $kpis['monto_cierre'] !== null ? $montoFormat : null); $row++;

        $row++;

        // ── Bloque: ARQUEO DE EFECTIVO ────────────────────────────────
        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue("A{$row}", 'ARQUEO DE EFECTIVO');
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($headerStyle);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;

        self::fila2col($sheet, $row, 'Efectivo inicial (S/)',      $kpis['efectivo_inicial'],  $montoFormat); $row++;
        self::fila2col($sheet, $row, 'Ingresos en efectivo (S/)',  $kpis['efectivo_ingresos'], $montoFormat); $row++;
        self::fila2col($sheet, $row, 'Salidas en efectivo (S/)',   $kpis['efectivo_salidas'],  $montoFormat); $row++;
        self::fila2col($sheet, $row, 'Efectivo esperado (S/)',     $kpis['efectivo_en_caja'],  $montoFormat);
        $sheet->getStyle("B{$row}")->getFont()->setBold(true); $row++;
        self::fila2col($sheet, $row, 'Conteo físico (cierre) (S/)',
            $kpis['monto_cierre'] ?? 'N/D', $kpis['monto_cierre'] !== null ? $montoFormat : null); $row++;

        if ($kpis['diferencia'] !== null) {
            self::fila2col($sheet, $row, 'Diferencia (S/)', $kpis['diferencia'], $montoFormat);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            if ($kpis['diferencia'] != 0) {
                $color = $kpis['diferencia'] < 0 ? 'FEE2E2' : 'D1FAE5';
                $sheet->getStyle("A{$row}:B{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
            }
            $row++;
        }

        $row++;

        // ── Bloque: FLUJO SEPARADO POR MEDIO ─────────────────────────
        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue("A{$row}", 'INGRESOS Y SALIDAS POR MEDIO');
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($headerStyle);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;

        self::fila2col($sheet, $row, 'Ingresos en efectivo (S/)', $kpis['efectivo_ingresos'], $montoFormat); $row++;
        self::fila2col($sheet, $row, 'Ingresos por depósitos (S/)', $kpis['depositos_ingresos'], $montoFormat); $row++;
        self::fila2col($sheet, $row, 'Salidas en efectivo (S/)', $kpis['efectivo_salidas'], $montoFormat); $row++;
        self::fila2col($sheet, $row, 'Salidas por depósitos (S/)', $kpis['depositos_salidas'], $montoFormat); $row++;
        self::fila2col($sheet, $row, 'Total ingresos (S/)',
            round($kpis['efectivo_ingresos'] + $kpis['depositos_ingresos'], 2), $montoFormat); $row++;
        self::fila2col($sheet, $row, 'Total salidas (S/)',
            round($kpis['efectivo_salidas'] + $kpis['depositos_salidas'], 2), $montoFormat);
        $sheet->getStyle("B{$row}")->getFont()->setBold(true); $row++;

        $row++;

        // ── Bloque: Conteo ventas ─────────────────────────────────────
        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue("A{$row}", 'CONTEO DE VENTAS');
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($headerStyle);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;

        self::fila2col($sheet, $row, 'Total documentos emitidos', $kpis['total_ventas']);        $row++;
        self::fila2col($sheet, $row, 'Cobradas',                  $kpis['ventas_cobradas']);     $row++;
        self::fila2col($sheet, $row, 'Canjeadas',                 $kpis['ventas_canjeadas']);    $row++;
        self::fila2col($sheet, $row, 'Pendientes / Parcial',      $kpis['ventas_pendientes']);   $row++;

        $row++;

        // ══════════════════════════════════════════════════════════════
        // ── Bloque: DETALLE DE VALES COBRADOS ────────────────────────
        // ══════════════════════════════════════════════════════════════
        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue("A{$row}", 'DETALLE DE VALES / DOCUMENTOS COBRADOS');
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($headerStyle);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;

        // Cabecera de tabla
        $colsVales = ['#', 'Tipo Doc.', 'Nro. Documento', 'Cliente', 'Vendedor', 'Método Pago', 'Cobrado en sesión (S/)'];
        foreach ($colsVales as $ci => $label) {
            $col = chr(65 + $ci); // A, B, C…
            $sheet->setCellValue("{$col}{$row}", $label);
        }
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($subHeaderStyle);
        $row++;

        // Solo mostrar vales con un cobro aplicado durante esta sesión.
        // Una venta pendiente o cobrada en otro turno no debe aparecer aquí.
        $ventasList = ($ventas ?? collect())
            ->filter(fn ($v) => (float) ($v->reporte_cobrado ?? 0) > 0)
            ->values();
        $n = 1;
        foreach ($ventasList as $v) {
            $sheet->setCellValue("A{$row}", $n++);
            $sheet->setCellValue("B{$row}", ucfirst($v->documento_tipo ?? 'Venta'));
            $sheet->setCellValue("C{$row}", $v->documento_numero ?? '—');
            $sheet->setCellValue("D{$row}", $v->cliente->nombre ?? 'Sin cliente');
            $sheet->setCellValue("E{$row}", $v->vendedor->nombre ?? '—');
            $sheet->setCellValue("F{$row}", ucfirst($v->metodo_pago ?? '—'));
            $sheet->setCellValue("G{$row}", (float) ($v->reporte_cobrado ?? $v->pagado ?? $v->total));
            $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode($montoFormat);

            $color = match ($v->estado ?? '') {
                'pagado'   => self::VERDE,
                'anulado'  => self::ROJO,
                'parcial'  => self::AMARILLO,
                default    => null,
            };
            if ($color) {
                $sheet->getStyle("A{$row}:G{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
            }
            $row++;
        }
        if ($ventasList->isEmpty()) {
            $sheet->mergeCells("A{$row}:G{$row}");
            $sheet->setCellValue("A{$row}", 'Sin vales cancelados durante esta sesión.');
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        }

        $row++;

        // ══════════════════════════════════════════════════════════════
        // ── Bloques separados de ingresos y salidas ─────────────────
        // ══════════════════════════════════════════════════════════════
        $movActivosReporte = ($movimientos ?? collect())->where('estado', 'activo');

        self::bloqueMovimientos(
            $sheet,
            $row,
            'INGRESOS EN EFECTIVO',
            $movActivosReporte->filter(fn ($m) => $m->tipo === 'ingreso' && self::esEfectivo($m->metodo_pago)),
            $headerStyle,
            $subHeaderStyle,
            $montoFormat
        );
        self::bloqueMovimientos(
            $sheet,
            $row,
            'INGRESOS POR DEPÓSITOS / MEDIOS ELECTRÓNICOS',
            $movActivosReporte->filter(fn ($m) => $m->tipo === 'ingreso' && !self::esEfectivo($m->metodo_pago)),
            $headerStyle,
            $subHeaderStyle,
            $montoFormat
        );
        self::bloqueMovimientos(
            $sheet,
            $row,
            'SALIDAS EN EFECTIVO',
            $movActivosReporte->filter(fn ($m) => $m->tipo === 'salida' && self::esEfectivo($m->metodo_pago)),
            $headerStyle,
            $subHeaderStyle,
            $montoFormat,
            self::ROJO
        );
        self::bloqueMovimientos(
            $sheet,
            $row,
            'SALIDAS POR DEPÓSITOS / MEDIOS ELECTRÓNICOS',
            $movActivosReporte->filter(fn ($m) => $m->tipo === 'salida' && !self::esEfectivo($m->metodo_pago)),
            $headerStyle,
            $subHeaderStyle,
            $montoFormat,
            self::ROJO
        );

        // ══════════════════════════════════════════════════════════════
        // ── Bloque: DEVOLUCIONES ──────────────────────────────────────
        // ══════════════════════════════════════════════════════════════
        $devList = $devoluciones ?? collect();

        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue("A{$row}", 'DEVOLUCIONES');
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($headerStyle);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;

        $colsDev = ['#', 'Venta / Doc.', 'Tipo', 'Motivo', 'Registrado por', 'Saldo generado (S/)', 'Monto devuelto (S/)'];
        foreach ($colsDev as $ci => $label) {
            $col = chr(65 + $ci);
            $sheet->setCellValue("{$col}{$row}", $label);
        }
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($subHeaderStyle);
        $row++;

        $n = 1;
        foreach ($devList as $d) {
            $docRef = $d->venta?->documento_numero ?? ('Venta #' . ($d->venta_id ?? '—'));
            $sheet->setCellValue("A{$row}", $n++);
            $sheet->setCellValue("B{$row}", $docRef);
            $sheet->setCellValue("C{$row}", ucfirst($d->tipo ?? 'parcial'));
            $sheet->setCellValue("D{$row}", $d->motivo ?? '—');
            $sheet->setCellValue("E{$row}", $d->user?->name ?? '—');
            $sheet->setCellValue("F{$row}", (float) ($d->saldo_generado ?? 0));
            $sheet->setCellValue("G{$row}", (float) ($d->monto_devuelto ?? 0));
            $sheet->getStyle("F{$row}:G{$row}")->getNumberFormat()->setFormatCode($montoFormat);
            $sheet->getStyle("A{$row}:G{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');
            $row++;
        }
        if ($devList->isEmpty()) {
            $sheet->mergeCells("A{$row}:G{$row}");
            $sheet->setCellValue("A{$row}", 'Sin devoluciones en esta sesión.');
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        }

        $row++;

        // ══════════════════════════════════════════════════════════════
        // ── Bloque: VENTAS ANULADAS ───────────────────────────────────
        // ══════════════════════════════════════════════════════════════
        $anuladasList = $ventasAnuladas ?? collect();

        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue("A{$row}", 'VENTAS ANULADAS');
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($headerStyle);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;

        $colsAnul = ['#', 'Tipo Doc.', 'Nro. Documento', 'Cliente', 'Vendedor', 'Total original (S/)', 'Estado'];
        foreach ($colsAnul as $ci => $label) {
            $col = chr(65 + $ci);
            $sheet->setCellValue("{$col}{$row}", $label);
        }
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($subHeaderStyle);
        $row++;

        $n = 1;
        foreach ($anuladasList as $v) {
            $sheet->setCellValue("A{$row}", $n++);
            $sheet->setCellValue("B{$row}", ucfirst($v->documento_tipo ?? 'Venta'));
            $sheet->setCellValue("C{$row}", $v->documento_numero ?? '—');
            $sheet->setCellValue("D{$row}", $v->cliente?->nombre ?? 'Sin cliente');
            $sheet->setCellValue("E{$row}", $v->vendedor?->nombre ?? '—');
            $sheet->setCellValue("F{$row}", (float) $v->total);
            $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode($montoFormat);
            $sheet->setCellValue("G{$row}", 'Anulado');
            $sheet->getStyle("A{$row}:G{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::ROJO);
            $row++;
        }
        if ($anuladasList->isEmpty()) {
            $sheet->mergeCells("A{$row}:G{$row}");
            $sheet->setCellValue("A{$row}", 'Sin ventas anuladas en esta sesión.');
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        }

        // ── Anchos de columna ─────────────────────────────────────────
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(16);
        $sheet->getColumnDimension('C')->setWidth(22);
        $sheet->getColumnDimension('D')->setWidth(28);
        $sheet->getColumnDimension('E')->setWidth(20);
        $sheet->getColumnDimension('F')->setWidth(22);
        $sheet->getColumnDimension('G')->setWidth(14);
    }

    private static function fila2col($sheet, int $row, string $label, $valor, ?string $format = null): void
    {
        $sheet->setCellValue("A{$row}", $label);
        $sheet->setCellValue("B{$row}", $valor);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        if ($format && is_numeric($valor)) {
            $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode($format);
        }
    }

    private static function bloqueMovimientos(
        $sheet,
        int &$row,
        string $titulo,
        $movimientos,
        array $headerStyle,
        array $subHeaderStyle,
        string $montoFormat,
        ?string $colorFila = null
    ): void {
        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue("A{$row}", $titulo);
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($headerStyle);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;

        $columnas = ['#', 'Tipo', 'Subtipo', 'Categoría', 'Método Pago', 'Observaciones', 'Monto (S/)'];
        foreach ($columnas as $indice => $label) {
            $columna = chr(65 + $indice);
            $sheet->setCellValue("{$columna}{$row}", $label);
        }
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($subHeaderStyle);
        $row++;

        $n = 1;
        foreach ($movimientos as $movimiento) {
            $sheet->setCellValue("A{$row}", $n++);
            $sheet->setCellValue("B{$row}", ucfirst($movimiento->tipo ?? ''));
            $sheet->setCellValue("C{$row}", $movimiento->subtipo ?? '');
            $sheet->setCellValue("D{$row}", $movimiento->categoria ?? '');
            $sheet->setCellValue("E{$row}", ucfirst($movimiento->metodo_pago ?? ''));
            $sheet->setCellValue("F{$row}", $movimiento->observaciones ?? '');
            $sheet->setCellValue("G{$row}", (float) $movimiento->monto);
            $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode($montoFormat);

            if ($colorFila) {
                $sheet->getStyle("A{$row}:G{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($colorFila);
            }
            $row++;
        }

        if ($movimientos->isEmpty()) {
            $sheet->mergeCells("A{$row}:G{$row}");
            $sheet->setCellValue("A{$row}", 'Sin movimientos en esta categoría.');
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        }

        $row++;
    }

    // ── Hoja 2: Ventas ────────────────────────────────────────────────────

    private static function hojaVentas(Spreadsheet $sp, $ventas): void
    {
        $sheet = $sp->createSheet();
        $sheet->setTitle('Ventas');

        $headers = ['#', 'Fecha', 'Documento', 'Nro. Doc.', 'Cliente', 'Vendedor', 'Método Pago', 'Total (S/)', 'Cobrado (S/)', 'Estado'];
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => self::BLANCO]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::AZUL]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A1:J1')->applyFromArray($headerStyle);

        $row = 2;
        $n   = 1;
        foreach ($ventas as $v) {
            $sheet->setCellValue("A{$row}", $n++);
            $sheet->setCellValue("B{$row}", $v->fecha ? \Carbon\Carbon::parse($v->fecha)->format('d/m/Y') : '');
            $sheet->setCellValue("C{$row}", ucfirst($v->documento_tipo ?? ''));
            $sheet->setCellValue("D{$row}", $v->documento_numero ?? '');
            $sheet->setCellValue("E{$row}", $v->cliente->nombre ?? 'Sin cliente');
            $sheet->setCellValue("F{$row}", $v->vendedor->nombre ?? '');
            $sheet->setCellValue("G{$row}", $v->metodo_pago ?? '');
            $sheet->setCellValue("H{$row}", (float) $v->total);
            $sheet->setCellValue("I{$row}", (float) ($v->reporte_cobrado ?? $v->pagado ?? 0));
            $sheet->setCellValue("J{$row}", ucfirst($v->estado ?? 'pendiente'));

            $color = match ($v->estado ?? '') {
                'pagado'  => self::VERDE,
                'anulado' => self::ROJO,
                default   => null,
            };
            if (!$color && str_contains(strtolower($v->metodo_pago ?? ''), 'efectivo')) {
                $color = self::AMARILLO;
            }
            if ($color) {
                $sheet->getStyle("A{$row}:J{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
            }
            $row++;
        }

        if ($ventas->isEmpty()) {
            $sheet->mergeCells('A2:J2');
            $sheet->setCellValue('A2', 'Sin ventas en esta sesión.');
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        if ($row > 2) {
            $sheet->getStyle("H2:I" . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        }
    }

    // ── Hoja 3: Movimientos ───────────────────────────────────────────────

    private static function hojaMovimientos(Spreadsheet $sp, $movimientos, float $montoApertura = 0): void
    {
        $sheet = $sp->createSheet();
        $sheet->setTitle('Movimientos');

        // 11 columnas: añadimos Hora y Saldo acumulado
        $headers = ['#', 'Hora', 'Fecha', 'Tipo', 'Subtipo', 'Categoría', 'Método Pago', 'Cliente', 'Observaciones', 'Monto (S/)', 'Saldo (S/)', 'Estado'];
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => self::BLANCO]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::AZUL]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A1:L1')->applyFromArray($headerStyle);

        // Fila de apertura como punto de partida del saldo acumulado
        $saldoAcum = $montoApertura;
        $sheet->setCellValue('A2', '—');
        $sheet->setCellValue('B2', '');
        $sheet->setCellValue('C2', '');
        $sheet->setCellValue('D2', 'Apertura');
        $sheet->setCellValue('E2', '');
        $sheet->setCellValue('F2', '');
        $sheet->setCellValue('G2', 'Efectivo');
        $sheet->setCellValue('H2', '');
        $sheet->setCellValue('I2', 'Monto inicial de caja');
        $sheet->setCellValue('J2', $montoApertura);
        $sheet->setCellValue('K2', $saldoAcum);
        $sheet->setCellValue('L2', 'Activo');
        $sheet->getStyle('J2:K2')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('A2:L2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DBEAFE');

        $row = 3;
        $n   = 1;
        foreach ($movimientos as $m) {
            $esIngreso = $m->tipo === 'ingreso' && $m->estado === 'activo';
            $esSalida  = $m->tipo === 'salida'  && $m->estado === 'activo';
            $monto     = (float) $m->monto;

            if ($esIngreso) $saldoAcum = round($saldoAcum + $monto, 2);
            if ($esSalida)  $saldoAcum = round($saldoAcum - $monto, 2);

            $hora = $m->created_at
                ? \Carbon\Carbon::parse($m->created_at)->format('H:i')
                : '';

            $sheet->setCellValue("A{$row}", $n++);
            $sheet->setCellValue("B{$row}", $hora);
            $sheet->setCellValue("C{$row}", $m->fecha ? \Carbon\Carbon::parse($m->fecha)->format('d/m/Y') : '');
            $sheet->setCellValue("D{$row}", ucfirst($m->tipo ?? ''));
            $sheet->setCellValue("E{$row}", $m->subtipo ?? '');
            $sheet->setCellValue("F{$row}", $m->categoria ?? '');
            $sheet->setCellValue("G{$row}", $m->metodo_pago ?? '');
            $sheet->setCellValue("H{$row}", $m->cliente->nombre ?? '');
            $sheet->setCellValue("I{$row}", $m->observaciones ?? '');
            $sheet->setCellValue("J{$row}", $monto);
            $sheet->setCellValue("K{$row}", ($m->estado === 'activo') ? $saldoAcum : '—');
            $sheet->setCellValue("L{$row}", ucfirst($m->estado ?? ''));
            $sheet->getStyle("J{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            if ($m->estado === 'activo') {
                $sheet->getStyle("K{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            }

            if ($m->tipo === 'salida' && $m->estado === 'activo') {
                $sheet->getStyle("A{$row}:L{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::ROJO);
            } elseif ($m->estado === 'anulado') {
                $sheet->getStyle("A{$row}:L{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GRIS);
            }
            $row++;
        }

        if ($movimientos->isEmpty()) {
            $sheet->mergeCells('A3:L3');
            $sheet->setCellValue('A3', 'Sin movimientos en esta sesión.');
            $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
