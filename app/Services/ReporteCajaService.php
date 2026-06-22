<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\CajaSesion;
use App\Models\Compra;
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
        [$ventas, $movimientos, $kpis, $metodos, $porVendedor, $devoluciones, $ventasAnuladas] = self::recopilarDatos($sesion, $caja, $fecha);

        // ── Generar Excel ─────────────────────────────────────────────────
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle("Reporte Caja {$caja?->codigo} {$fecha->format('d/m/Y')}");

        self::hojaSesion($spreadsheet, $sesion, $caja, $fecha, $kpis, $metodos, $porVendedor, $ventas, $movimientos, $devoluciones, $ventasAnuladas);
        self::hojaVentas($spreadsheet, $ventas);
        self::hojaMovimientos($spreadsheet, $movimientos);

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
        $desde = $fecha->toDateString();
        $hasta = $desde;

        // Movimientos — acotados al rango temporal de esta sesión específica
        // para que múltiples sesiones del mismo día no se mezclen en el reporte.
        $movQuery = Movimiento::with('cliente:id,nombre')
            ->whereDate('fecha', $desde);
        if ($caja) {
            $movQuery->where('caja_id', $caja->id);
        } else {
            $movQuery->where('empresa', $sesion->empresa)->whereNull('caja_id');
        }
        // Desde la apertura de esta sesión
        $movQuery->where('movimientos.created_at', '>=', $sesion->created_at);
        // Hasta el cierre (updated_at al momento de cerrar) si la sesión ya está cerrada
        if ($sesion->monto_cierre !== null && $sesion->updated_at) {
            $movQuery->where('movimientos.created_at', '<=', $sesion->updated_at);
        }
        $movimientos = $movQuery->orderBy('id')->get();
        $movActivos  = $movimientos->where('estado', 'activo');

        // Ventas
        $ventasQuery = Venta::with(['vendedor', 'cliente', 'detalles'])
            ->whereDate('fecha', $desde);
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

        $ventasCobradas   = $ventas->where('estado', 'pagado');

        // KPIs
        $totalCobradas   = round($movActivos->where('subtipo', 'pago_venta')->sum('monto'), 2);
        $totalOtros      = round($movActivos->filter(fn ($m) => $m->tipo === 'ingreso' && $m->subtipo !== 'pago_venta')->sum('monto'), 2);
        $totalSalidas    = round($movActivos->where('tipo', 'salida')->sum('monto'), 2);
        $balance         = round($totalCobradas + $totalOtros - $totalSalidas, 2);

        // Efectivo
        $comprasEfectivo = 0;
        if ($caja) {
            $comprasEfectivo = round(Compra::where('metodo_pago', 'efectivo')
                ->where('caja_id', $caja->id)
                ->whereDate('fecha', $desde)
                ->sum('monto_total'), 2);
        }
        $ingManualEfec = round($movActivos->filter(fn ($m) => $m->tipo === 'ingreso' && $m->subtipo === 'manual' && $m->metodo_pago === 'efectivo')->sum('monto'), 2);
        $salManualEfec = round($movActivos->filter(fn ($m) => $m->tipo === 'salida'  && $m->subtipo === 'manual' && $m->metodo_pago === 'efectivo')->sum('monto'), 2);

        // Métodos de pago
        $metodos = self::calcularMetodos($movActivos, $ventasCobradas);

        // Efectivo esperado en caja al momento del cierre
        $efectivoEnCaja = round((float)$sesion->monto_apertura + ($metodos->get('efectivo', 0)) + $ingManualEfec - $comprasEfectivo - $salManualEfec, 2);

        $diferencia = $sesion->monto_cierre !== null
            ? round((float)$sesion->monto_cierre - $efectivoEnCaja, 2)
            : null;

        $kpis = [
            'monto_apertura'   => (float) $sesion->monto_apertura,
            'monto_cierre'     => $sesion->monto_cierre !== null ? (float) $sesion->monto_cierre : null,
            'efectivo_en_caja' => $efectivoEnCaja,
            'diferencia'       => $diferencia,
            'total_cobradas'   => $totalCobradas,
            'total_otros'      => $totalOtros,
            'total_salidas'    => $totalSalidas,
            'balance'          => $balance,
            'total_ventas'     => $ventas->count(),
            'ventas_cobradas'  => $ventasCobradas->count(),
            'ventas_pendientes'=> $ventas->whereIn('estado', ['pendiente','parcial'])->count(),
        ];

        // Por vendedor
        $porVendedor = $ventasCobradas
            ->groupBy(fn ($v) => $v->vendedor->nombre ?? 'Sin vendedor')
            ->map(fn ($g) => round($g->sum(fn ($v) => $v->total_cobrado ?? 0), 2));

        // ── Devoluciones de esta sesión ───────────────────────────────────
        // Filtramos solo por fecha. caja_id puede ser nulo en varias devoluciones,
        // por eso aceptamos también las que no tienen caja asignada (orWhereNull).
        $devQuery = Devolucion::with(['venta:id,documento_tipo,documento_numero', 'user:id,name'])
            ->whereDate('devoluciones.fecha', $desde);
        if ($caja) {
            $devQuery->where(function ($q) use ($caja) {
                $q->where('devoluciones.caja_id', $caja->id)
                  ->orWhereNull('devoluciones.caja_id');
            });
        }
        $devoluciones = $devQuery->orderBy('devoluciones.id')->get();

        // ── Ventas anuladas en esta sesión ────────────────────────────────
        $ventasAnuladas = $ventas->where('estado', 'anulado');

        return [$ventas, $movimientos, $kpis, $metodos, $porVendedor, $devoluciones, $ventasAnuladas];
    }

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

        // Fallback
        $vIds    = $ventasCobradas->pluck('id');
        $pIds    = $vIds->isNotEmpty()
            ? DetallePagoFactura::whereIn('venta_id', $vIds)->pluck('pago_id')->unique()
            : collect();

        $metodos = $pIds->isNotEmpty()
            ? PagoMetodo::whereIn('pago_id', $pIds)
                ->selectRaw('metodo, SUM(monto) as total')
                ->groupBy('metodo')
                ->pluck('total', 'metodo')
                ->map(fn ($t) => (float) $t)
            : collect();

        return $metodos->sortKeys();
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
        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue("A{$row}", 'REPORTE DE CIERRE DE CAJA');
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($headerStyle);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;

        self::fila2col($sheet, $row, 'Caja',  $caja?->codigo . ' — ' . $caja?->nombre);         $row++;
        self::fila2col($sheet, $row, 'Fecha', $fecha->format('d/m/Y'));                           $row++;
        self::fila2col($sheet, $row, 'Sesión ID', $sesion->id);                                  $row++;
        self::fila2col($sheet, $row, 'Estado', ucfirst($sesion->estado));                        $row++;
        self::fila2col($sheet, $row, 'Apertura (S/)', $kpis['monto_apertura'], $montoFormat);    $row++;
        self::fila2col($sheet, $row, 'Cierre declarado (S/)',
            $kpis['monto_cierre'] ?? 'N/D', $kpis['monto_cierre'] !== null ? $montoFormat : null); $row++;
        self::fila2col($sheet, $row, 'Efectivo esperado (S/)', $kpis['efectivo_en_caja'], $montoFormat); $row++;

        if ($kpis['diferencia'] !== null) {
            self::fila2col($sheet, $row, 'Diferencia (S/)', $kpis['diferencia'], $montoFormat);
            if ($kpis['diferencia'] != 0) {
                $color = $kpis['diferencia'] < 0 ? 'FEE2E2' : 'D1FAE5';
                $sheet->getStyle("B{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
            }
            $row++;
        }

        $row++;

        // ── Bloque: KPIs ─────────────────────────────────────────────
        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue("A{$row}", 'RESUMEN FINANCIERO');
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($headerStyle);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;

        self::fila2col($sheet, $row, 'Ventas cobradas (S/)',  $kpis['total_cobradas'], $montoFormat); $row++;
        self::fila2col($sheet, $row, 'Otros ingresos (S/)',   $kpis['total_otros'],    $montoFormat); $row++;
        self::fila2col($sheet, $row, 'Salidas (S/)',          $kpis['total_salidas'],  $montoFormat); $row++;
        self::fila2col($sheet, $row, 'Balance neto (S/)',     $kpis['balance'],        $montoFormat);
        $sheet->getStyle("B{$row}")->getFont()->setBold(true);
        $row++;

        $row++;

        // ── Bloque: Métodos de pago ───────────────────────────────────
        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue("A{$row}", 'INGRESOS POR MÉTODO DE PAGO');
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($headerStyle);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;

        foreach ($metodos as $metodo => $monto) {
            self::fila2col($sheet, $row, ucfirst($metodo) . ' (S/)', $monto, $montoFormat);
            $row++;
        }
        if ($metodos->isEmpty()) {
            $sheet->setCellValue("A{$row}", 'Sin datos'); $row++;
        }

        $row++;

        // ── Bloque: Por vendedor ──────────────────────────────────────
        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue("A{$row}", 'VENTAS COBRADAS POR VENDEDOR');
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($headerStyle);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;

        foreach ($porVendedor as $vendedor => $total) {
            self::fila2col($sheet, $row, $vendedor . ' (S/)', $total, $montoFormat);
            $row++;
        }
        if ($porVendedor->isEmpty()) {
            $sheet->setCellValue("A{$row}", 'Sin datos'); $row++;
        }

        $row++;

        // ── Bloque: Conteo ventas ─────────────────────────────────────
        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue("A{$row}", 'CONTEO DE VENTAS');
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($headerStyle);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;

        self::fila2col($sheet, $row, 'Total documentos',  $kpis['total_ventas']);      $row++;
        self::fila2col($sheet, $row, 'Cobradas',          $kpis['ventas_cobradas']);    $row++;
        self::fila2col($sheet, $row, 'Pendientes/Parcial',$kpis['ventas_pendientes']);  $row++;

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
        $colsVales = ['#', 'Tipo Doc.', 'Nro. Documento', 'Cliente', 'Vendedor', 'Método Pago', 'Total (S/)'];
        foreach ($colsVales as $ci => $label) {
            $col = chr(65 + $ci); // A, B, C…
            $sheet->setCellValue("{$col}{$row}", $label);
        }
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($subHeaderStyle);
        $row++;

        $ventasList = $ventas ?? collect();
        $n = 1;
        foreach ($ventasList as $v) {
            $sheet->setCellValue("A{$row}", $n++);
            $sheet->setCellValue("B{$row}", ucfirst($v->documento_tipo ?? 'Venta'));
            $sheet->setCellValue("C{$row}", $v->documento_numero ?? '—');
            $sheet->setCellValue("D{$row}", $v->cliente->nombre ?? 'Sin cliente');
            $sheet->setCellValue("E{$row}", $v->vendedor->nombre ?? '—');
            $sheet->setCellValue("F{$row}", ucfirst($v->metodo_pago ?? '—'));
            $sheet->setCellValue("G{$row}", (float) ($v->pagado ?? $v->total));
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
            $sheet->setCellValue("A{$row}", 'Sin vales registrados en esta sesión.');
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        }

        $row++;

        // ══════════════════════════════════════════════════════════════
        // ── Bloque: DETALLE DE INGRESOS Y SALIDAS ────────────────────
        // ══════════════════════════════════════════════════════════════
        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue("A{$row}", 'DETALLE DE INGRESOS Y SALIDAS (MOVIMIENTOS)');
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($headerStyle);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;

        $colsMov = ['#', 'Tipo', 'Subtipo', 'Categoría', 'Método Pago', 'Observaciones', 'Monto (S/)'];
        foreach ($colsMov as $ci => $label) {
            $col = chr(65 + $ci);
            $sheet->setCellValue("{$col}{$row}", $label);
        }
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($subHeaderStyle);
        $row++;

        $movList = ($movimientos ?? collect())->where('estado', 'activo');
        $n = 1;
        foreach ($movList as $m) {
            $sheet->setCellValue("A{$row}", $n++);
            $sheet->setCellValue("B{$row}", ucfirst($m->tipo ?? ''));
            $sheet->setCellValue("C{$row}", $m->subtipo ?? '');
            $sheet->setCellValue("D{$row}", $m->categoria ?? '');
            $sheet->setCellValue("E{$row}", ucfirst($m->metodo_pago ?? ''));
            $sheet->setCellValue("F{$row}", $m->observaciones ?? '');
            $sheet->setCellValue("G{$row}", (float) $m->monto);
            $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode($montoFormat);

            if ($m->tipo === 'salida') {
                $sheet->getStyle("A{$row}:G{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::ROJO);
            }
            $row++;
        }
        if ($movList->isEmpty()) {
            $sheet->mergeCells("A{$row}:G{$row}");
            $sheet->setCellValue("A{$row}", 'Sin movimientos activos en esta sesión.');
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        }

        $row++;

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
            $sheet->setCellValue("I{$row}", (float) ($v->pagado ?? 0));
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

    private static function hojaMovimientos(Spreadsheet $sp, $movimientos): void
    {
        $sheet = $sp->createSheet();
        $sheet->setTitle('Movimientos');

        $headers = ['#', 'Fecha', 'Tipo', 'Subtipo', 'Categoría', 'Método Pago', 'Cliente', 'Observaciones', 'Monto (S/)', 'Estado'];
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => self::BLANCO]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::AZUL]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A1:J1')->applyFromArray($headerStyle);

        $row = 2;
        $n   = 1;
        foreach ($movimientos as $m) {
            $sheet->setCellValue("A{$row}", $n++);
            $sheet->setCellValue("B{$row}", $m->fecha ? \Carbon\Carbon::parse($m->fecha)->format('d/m/Y') : '');
            $sheet->setCellValue("C{$row}", ucfirst($m->tipo ?? ''));
            $sheet->setCellValue("D{$row}", $m->subtipo ?? '');
            $sheet->setCellValue("E{$row}", $m->categoria ?? '');
            $sheet->setCellValue("F{$row}", $m->metodo_pago ?? '');
            $sheet->setCellValue("G{$row}", $m->cliente->nombre ?? '');
            $sheet->setCellValue("H{$row}", $m->observaciones ?? '');
            $sheet->setCellValue("I{$row}", (float) $m->monto);
            $sheet->setCellValue("J{$row}", ucfirst($m->estado ?? ''));

            if ($m->tipo === 'salida') {
                $sheet->getStyle("A{$row}:J{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::ROJO);
            } elseif ($m->estado === 'anulado') {
                $sheet->getStyle("A{$row}:J{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GRIS);
            }
            $row++;
        }

        if ($movimientos->isEmpty()) {
            $sheet->mergeCells('A2:J2');
            $sheet->setCellValue('A2', 'Sin movimientos en esta sesión.');
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        if ($row > 2) {
            $sheet->getStyle("I2:I" . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        }
    }
}
