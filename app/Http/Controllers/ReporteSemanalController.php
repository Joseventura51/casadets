<?php

namespace App\Http\Controllers;

use App\Models\ReporteSemanal;
use App\Services\ComisionUtilidad;
use App\Services\VentaCosteo;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ReporteSemanalController extends Controller
{
    /**
     * Determina la fecha de inicio del próximo período a cerrar:
     * el día siguiente al último cierre, o la fecha más antigua de
     * datos abiertos (sin reporte_semanal_id) si nunca se ha cerrado nada.
     */
    private function resolverInicio(): ?Carbon
    {
        $ultimo = ReporteSemanal::orderByDesc('periodo_fin')->first();
        if ($ultimo) {
            return Carbon::parse($ultimo->periodo_fin)->addDay()->startOfDay();
        }

        $minVenta  = DB::table('ventas')->whereNull('reporte_semanal_id')->whereNull('deleted_at')->min('fecha');
        $minCompra = DB::table('compras')->whereNull('reporte_semanal_id')->whereNull('deleted_at')->min('fecha');

        $fechas = collect([$minVenta, $minCompra])->filter();
        if ($fechas->isEmpty()) {
            return null;
        }

        return Carbon::parse($fechas->min())->startOfDay();
    }

    /**
     * Calcula, para un rango [inicio, fin], qué ventas se archivarían y cuáles
     * quedan pendientes (ruedan a la siguiente semana), junto con los totales
     * resultantes. No persiste nada.
     */
    private function calcular(Carbon $inicio, Carbon $fin): array
    {
        $ventasRango = DB::table('ventas')
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->whereNull('deleted_at')
            ->whereNull('reporte_semanal_id')
            ->where('es_referencia_fiscal', false)
            ->where(fn($q) => $q->whereNull('documento_tipo')->orWhere('documento_tipo', '!=', 'nota_credito'))
            ->select('id', 'estado', 'fecha', 'total', 'ajuste')
            ->get();

        $pagadasIds = $ventasRango->where('estado', 'pagado')->pluck('id');
        $costeadasCompletasIds = VentaCosteo::idsCompletamenteCosteados($pagadasIds);

        $anuladasIds = $ventasRango->where('estado', 'anulado')->pluck('id');

        // Archivables: anuladas, o pagadas Y completamente costeadas.
        $archivarIds = $anuladasIds->merge($costeadasCompletasIds)->unique()->values();

        // Ruedan a la siguiente semana: todo lo demás (pendiente, parcial, o pagado sin costeo completo).
        $pendientesIds = $ventasRango->pluck('id')->diff($archivarIds)->values();

        $ventasArchivar = $ventasRango->whereIn('id', $archivarIds);

        $totalVentas = (float) $ventasArchivar->sum(fn($v) => (float) $v->total + (float) $v->ajuste);
        $cantVentas  = $ventasArchivar->count();

        $validIdsUtilidad = $costeadasCompletasIds; // subset de $archivarIds válido para utilidad/comisión

        $totalCosto = $validIdsUtilidad->isEmpty() ? 0.0 : (float) DB::table('compra_venta_detalle as cvd')
            ->join('venta_detalles as vd', 'cvd.venta_detalle_id', '=', 'vd.id')
            ->whereIn('vd.venta_id', $validIdsUtilidad)
            ->whereNotNull('cvd.costo_total')
            ->sum('cvd.costo_total');

        $totalVentasValidas = $validIdsUtilidad->isEmpty() ? 0.0 : (float) DB::table('compra_venta_detalle as cvd')
            ->join('venta_detalles as vd', 'cvd.venta_detalle_id', '=', 'vd.id')
            ->whereIn('vd.venta_id', $validIdsUtilidad)
            ->whereNotNull('cvd.costo_total')
            ->selectRaw('SUM(cvd.cantidad * vd.precio_unitario) as t')
            ->value('t');

        $utilidad = round($totalVentasValidas - $totalCosto, 2);
        $margen   = $totalVentasValidas > 0 ? round($utilidad / $totalVentasValidas * 100, 1) : 0;
        $comisionUtilidad = ComisionUtilidad::calcular($validIdsUtilidad);

        // Ajuste por precios supuestos reconciliados pero aún no aplicados a un cierre
        $ajusteSupuestos = round(-(float) DB::table('ajustes_precio_supuesto')
            ->where('aplicado', false)
            ->whereNotNull('compra_real_id')
            ->whereNotNull('diferencia_total')
            ->sum('diferencia_total'), 2);

        $comprasRango = DB::table('compras')
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->whereNull('deleted_at')
            ->whereNull('reporte_semanal_id')
            ->select('id', 'monto_total')
            ->get();

        $totalCompras = (float) $comprasRango->sum('monto_total');
        $cantCompras  = $comprasRango->count();

        return [
            'inicio' => $inicio,
            'fin' => $fin,
            'archivar_venta_ids' => $archivarIds,
            'pendiente_venta_ids' => $pendientesIds,
            'compra_ids' => $comprasRango->pluck('id'),
            'totales' => [
                'total_ventas'      => round($totalVentas, 2),
                'cantidad_ventas'   => $cantVentas,
                'total_compras'     => round($totalCompras, 2),
                'cantidad_compras'  => $cantCompras,
                'total_costo'       => round($totalCosto, 2),
                'utilidad'          => $utilidad,
                'margen'            => $margen,
                'comision_utilidad' => round($comisionUtilidad, 2),
                'ajuste_supuestos'  => $ajusteSupuestos,
                'ventas_pendientes' => $pendientesIds->count(),
            ],
        ];
    }

    public function index(): JsonResponse
    {
        $reportes = ReporteSemanal::with('cerradoPor:id,name')
            ->orderByDesc('periodo_fin')
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'periodo_inicio' => $r->periodo_inicio->format('d/m/Y'),
                'periodo_fin' => $r->periodo_fin->format('d/m/Y'),
                'total_ventas' => (float) $r->total_ventas,
                'cantidad_ventas' => $r->cantidad_ventas,
                'total_compras' => (float) $r->total_compras,
                'cantidad_compras' => $r->cantidad_compras,
                'total_costo' => (float) $r->total_costo,
                'utilidad' => (float) $r->utilidad,
                'margen' => (float) $r->margen,
                'comision_utilidad' => (float) $r->comision_utilidad,
                'ventas_pendientes' => $r->ventas_pendientes,
                'cerrado_por' => $r->cerradoPor?->name,
                'cerrado_en' => $r->created_at->format('d/m/Y H:i'),
            ]);

        $inicioSugerido = $this->resolverInicio();

        $pendientesSupuestos = \DB::table('ajustes_precio_supuesto')
            ->where('aplicado', false)
            ->whereNotNull('compra_real_id')
            ->whereNotNull('diferencia_total')
            ->count();

        return response()->json([
            'reportes'              => $reportes,
            'inicio_sugerido'       => $inicioSugerido?->format('Y-m-d'),
            'pendientes_supuestos'  => $pendientesSupuestos,
        ]);
    }

    public function preview(Request $r): JsonResponse
    {
        $r->validate(['fin' => 'required|date']);

        $inicio = $this->resolverInicio();
        $fin    = Carbon::parse($r->fin)->endOfDay();

        if (!$inicio) {
            return response()->json(['error' => 'No hay datos abiertos para cerrar.'], 422);
        }

        if ($fin->lt($inicio)) {
            return response()->json(['error' => 'La fecha de cierre debe ser posterior al inicio del período (' . $inicio->format('d/m/Y') . ').'], 422);
        }

        $resultado = $this->calcular($inicio, $fin);

        return response()->json([
            'periodo_inicio'       => $inicio->format('d/m/Y'),
            'periodo_fin'          => $fin->format('d/m/Y'),
            'totales'              => $resultado['totales'],
        ]);
    }

    public function cerrar(Request $r): JsonResponse
    {
        abort_if(
            !$r->user()?->esCajero() && !$r->user()?->esAdmin(),
            403,
            'Solo el cajero o un administrador pueden cerrar la semana.'
        );

        $r->validate(['fin' => 'required|date']);

        $inicio = $this->resolverInicio();
        $fin    = Carbon::parse($r->fin)->endOfDay();

        if (!$inicio) {
            return response()->json(['error' => 'No hay datos abiertos para cerrar.'], 422);
        }

        if ($fin->lt($inicio)) {
            return response()->json(['error' => 'La fecha de cierre debe ser posterior al inicio del período (' . $inicio->format('d/m/Y') . ').'], 422);
        }

        $resultado = $this->calcular($inicio, $fin);

        $reporte = DB::transaction(function () use ($resultado, $inicio, $fin, $r) {
            $reporte = ReporteSemanal::create([
                'periodo_inicio'    => $inicio->toDateString(),
                'periodo_fin'       => $fin->toDateString(),
                'total_ventas'      => $resultado['totales']['total_ventas'],
                'cantidad_ventas'   => $resultado['totales']['cantidad_ventas'],
                'total_compras'     => $resultado['totales']['total_compras'],
                'cantidad_compras'  => $resultado['totales']['cantidad_compras'],
                'total_costo'       => $resultado['totales']['total_costo'],
                'utilidad'          => $resultado['totales']['utilidad'],
                'margen'            => $resultado['totales']['margen'],
                'comision_utilidad' => $resultado['totales']['comision_utilidad'],
                'ajuste_supuestos'  => $resultado['totales']['ajuste_supuestos'],
                'ventas_pendientes' => $resultado['totales']['ventas_pendientes'],
                'cerrado_por_id'    => $r->user()?->id,
            ]);

            // Marcar ajustes de supuestos como aplicados en este cierre
            DB::table('ajustes_precio_supuesto')
                ->where('aplicado', false)
                ->whereNotNull('compra_real_id')
                ->whereNotNull('diferencia_total')
                ->update([
                    'aplicado'           => true,
                    'reporte_semanal_id' => $reporte->id,
                ]);

            if ($resultado['archivar_venta_ids']->isNotEmpty()) {
                DB::table('ventas')
                    ->whereIn('id', $resultado['archivar_venta_ids'])
                    ->update(['reporte_semanal_id' => $reporte->id]);
            }

            if ($resultado['compra_ids']->isNotEmpty()) {
                DB::table('compras')
                    ->whereIn('id', $resultado['compra_ids'])
                    ->update(['reporte_semanal_id' => $reporte->id]);
            }

            return $reporte;
        });

        return response()->json(['ok' => true, 'id' => $reporte->id]);
    }

    public function exportExcel(int $id): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $reporte = ReporteSemanal::with('cerradoPor:id,name')->findOrFail($id);

        // Todas las ventas del período (archivadas + pendientes)
        $ventasBase = DB::table('ventas as v')
            ->leftJoin('clientes as c', 'v.cliente_id', '=', 'c.id')
            ->leftJoin('vendedores as vend', 'v.vendedor_id', '=', 'vend.id')
            ->whereBetween('v.fecha', [$reporte->periodo_inicio->toDateString(), $reporte->periodo_fin->toDateString()])
            ->whereNull('v.deleted_at')
            ->where('v.es_referencia_fiscal', false)
            ->where(fn($q) => $q->whereNull('v.documento_tipo')->orWhere('v.documento_tipo', '!=', 'nota_credito'))
            ->selectRaw("v.id, v.fecha, v.documento_tipo, v.documento_numero,
                         COALESCE(c.nombre,'') as cliente, COALESCE(vend.nombre,'') as vendedor,
                         COALESCE(v.metodo_pago,'') as metodo_pago,
                         v.total + COALESCE(v.ajuste, 0) as total, v.estado,
                         v.reporte_semanal_id")
            ->orderBy('v.fecha')
            ->get();

        $ventasArchivadas  = $ventasBase->where('reporte_semanal_id', $id);
        $ventasPendientes  = $ventasBase->where('reporte_semanal_id', '!=', $id);

        $compras = DB::table('compras')
            ->where('reporte_semanal_id', $id)
            ->selectRaw('fecha, empresa, documento_tipo, documento_numero, metodo_pago, monto_total')
            ->orderBy('fecha')
            ->get();

        $spreadsheet = new Spreadsheet();

        // ── Hoja 1: Vista general (igual que la tabla en pantalla) ────────────
        $sg = $spreadsheet->getActiveSheet()->setTitle('Vista general');
        $sg->fromArray(
            ['Período','Ventas','Compras','Utilidad','Comisión','Pendientes','Cerrado por','Cerrado el'],
            null, 'A1'
        );
        $this->headerStyle($sg, 'A1:H1', '1E3A5F');
        $sg->fromArray([[
            $reporte->periodo_inicio->format('d/m/Y') . ' — ' . $reporte->periodo_fin->format('d/m/Y'),
            round((float)$reporte->total_ventas,     2),
            round((float)$reporte->total_compras,    2),
            round((float)$reporte->utilidad,         2),
            round((float)$reporte->comision_utilidad,2),
            $reporte->ventas_pendientes,
            $reporte->cerradoPor?->name ?? '—',
            $reporte->created_at->format('d/m/Y H:i'),
        ]], null, 'A2');
        foreach (['B','C','D','E'] as $col) {
            $sg->getStyle("{$col}2")->getNumberFormat()->setFormatCode('#,##0.00');
        }
        $sg->getStyle('F2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        foreach (range('A','H') as $col) { $sg->getColumnDimension($col)->setAutoSize(true); }

        // ── Hoja 2: Histórico de todas las semanas cerradas ───────────────────
        $historico = ReporteSemanal::with('cerradoPor:id,name')
            ->orderBy('periodo_inicio')
            ->get();

        $sh = $spreadsheet->createSheet()->setTitle('Histórico');
        $sh->fromArray(
            ['#','Período','Ventas','Compras','Utilidad','Comisión','Pendientes','Cerrado por','Cerrado el'],
            null, 'A1'
        );
        $this->headerStyle($sh, 'A1:I1', '1E3A5F');

        $hRow = 2;
        foreach ($historico as $i => $rep) {
            $esCurrent = ($rep->id === $id);
            $sh->fromArray([[
                $i + 1,
                $rep->periodo_inicio->format('d/m/Y') . ' — ' . $rep->periodo_fin->format('d/m/Y'),
                round((float)$rep->total_ventas,      2),
                round((float)$rep->total_compras,     2),
                round((float)$rep->utilidad,          2),
                round((float)$rep->comision_utilidad, 2),
                $rep->ventas_pendientes,
                $rep->cerradoPor?->name ?? '—',
                $rep->created_at->format('d/m/Y H:i'),
            ]], null, "A{$hRow}");

            // Destacar la semana actual con fondo amarillo claro
            if ($esCurrent) {
                $sh->getStyle("A{$hRow}:I{$hRow}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFF9C4');
            }
            $hRow++;
        }

        // Totales al final
        if ($historico->count() > 0) {
            $sh->setCellValue("A{$hRow}", 'TOTAL');
            $sh->getStyle("A{$hRow}")->getFont()->setBold(true);
            foreach (['C','D','E','F'] as $col) {
                $col1 = $col . '2';
                $colN = $col . ($hRow - 1);
                $sh->setCellValue("{$col}{$hRow}", "=SUM({$col1}:{$colN})");
                $sh->getStyle("{$col}{$hRow}")->getFont()->setBold(true);
            }
            $sh->getStyle("A{$hRow}:I{$hRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '1E3A5F']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
                'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1E3A5F']]],
            ]);
        }

        foreach (['C','D','E','F'] as $col) {
            $sh->getStyle("{$col}2:{$col}{$hRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        }
        $sh->getStyle("G2:G{$hRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        foreach (range('A','I') as $col) { $sh->getColumnDimension($col)->setAutoSize(true); }

        // ── Hoja 3: Resumen de métricas ───────────────────
        $sr = $spreadsheet->createSheet()->setTitle('Resumen');
        $titulo = 'Semana ' . $reporte->periodo_inicio->format('d/m/Y') . ' — ' . $reporte->periodo_fin->format('d/m/Y');
        $sr->setCellValue('A1', $titulo);
        $sr->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sr->setCellValue('A2', 'Cerrado por: ' . ($reporte->cerradoPor?->name ?? '—') . '   el ' . $reporte->created_at->format('d/m/Y H:i'));
        $sr->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('6c757d');

        $sr->fromArray(['Métrica', 'Valor'], null, 'A4');
        $this->headerStyle($sr, 'A4:B4', '2563EB');

        $sr->fromArray([
            ['Total ventas archivadas',   round((float)$reporte->total_ventas, 2)],
            ['Cantidad ventas',           $reporte->cantidad_ventas],
            ['Total compras archivadas',  round((float)$reporte->total_compras, 2)],
            ['Cantidad compras',          $reporte->cantidad_compras],
            ['Costo de productos',        round((float)$reporte->total_costo, 2)],
            ['Utilidad',                  round((float)$reporte->utilidad, 2)],
            ['Margen %',                  round((float)$reporte->margen, 1)],
            ['Comisión por utilidad',     round((float)$reporte->comision_utilidad, 2)],
            ['Ventas no archivadas',      $reporte->ventas_pendientes],
        ], null, 'A5');

        foreach (['B5','B7','B8','B9','B10','B11','B12'] as $cell) {
            $sr->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00');
        }
        $sr->getColumnDimension('A')->setWidth(28);
        $sr->getColumnDimension('B')->setAutoSize(true);

        // ── Hoja 2: Ventas archivadas ─────────────────────
        $sv = $spreadsheet->createSheet()->setTitle('Ventas archivadas');
        $sv->fromArray(['Fecha','Documento','Nro.','Cliente','Vendedor','Método pago','Total','Estado'], null, 'A1');
        $this->headerStyle($sv, 'A1:H1', '059669');
        $row = 2;
        foreach ($ventasArchivadas as $v) {
            $sv->fromArray([
                Carbon::parse($v->fecha)->format('d/m/Y'),
                ucfirst($v->documento_tipo ?? ''),
                $v->documento_numero ?? '',
                $v->cliente,
                $v->vendedor,
                ucfirst($v->metodo_pago),
                (float) $v->total,
                ucfirst($v->estado ?? ''),
            ], null, "A{$row}");
            $row++;
        }
        $sv->getStyle("G2:G{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        foreach (range('A','H') as $col) { $sv->getColumnDimension($col)->setAutoSize(true); }

        // ── Hoja 3: Ventas no archivadas ──────────────────
        $sp = $spreadsheet->createSheet()->setTitle('Ventas no archivadas');
        $sp->fromArray(['Fecha','Documento','Nro.','Cliente','Vendedor','Método pago','Total','Estado al cerrar','Situación actual'], null, 'A1');
        $this->headerStyle($sp, 'A1:I1', 'D97706');
        $row = 2;
        foreach ($ventasPendientes as $v) {
            $situacion = $v->reporte_semanal_id !== null ? 'Archivada en semana posterior' : 'Aún abierta';
            $sp->fromArray([
                Carbon::parse($v->fecha)->format('d/m/Y'),
                ucfirst($v->documento_tipo ?? ''),
                $v->documento_numero ?? '',
                $v->cliente,
                $v->vendedor,
                ucfirst($v->metodo_pago),
                (float) $v->total,
                ucfirst($v->estado ?? ''),
                $situacion,
            ], null, "A{$row}");
            $row++;
        }
        $sp->getStyle("G2:G{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        foreach (range('A','I') as $col) { $sp->getColumnDimension($col)->setAutoSize(true); }

        // ── Hoja 4: Compras ───────────────────────────────
        $sc = $spreadsheet->createSheet()->setTitle('Compras');
        $sc->fromArray(['Fecha','Proveedor','Tipo doc.','Nro. doc.','Método pago','Total'], null, 'A1');
        $this->headerStyle($sc, 'A1:F1', '7C3AED');
        $row = 2;
        foreach ($compras as $c) {
            $sc->fromArray([
                Carbon::parse($c->fecha)->format('d/m/Y'),
                $c->empresa ?? '',
                ucfirst($c->documento_tipo ?? ''),
                $c->documento_numero ?? '',
                ucfirst($c->metodo_pago ?? ''),
                (float) $c->monto_total,
            ], null, "A{$row}");
            $row++;
        }
        $sc->getStyle("F2:F{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        foreach (range('A','F') as $col) { $sc->getColumnDimension($col)->setAutoSize(true); }

        $spreadsheet->setActiveSheetIndex(0);
        $filename = 'semana-' . $reporte->periodo_inicio->format('Y-m-d') . '_' . $reporte->periodo_fin->format('Y-m-d') . '.xlsx';
        $path     = storage_path('app/' . $filename);
        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    private function headerStyle(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $range, string $color): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders'   => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $color]]],
        ]);
    }

    public function detalle(int $id): JsonResponse
    {
        $reporte = ReporteSemanal::with('cerradoPor:id,name')->findOrFail($id);

        $ventasBase = DB::table('ventas as v')
            ->leftJoin('clientes as c', 'v.cliente_id', '=', 'c.id')
            ->leftJoin('vendedores as vend', 'v.vendedor_id', '=', 'vend.id')
            ->whereBetween('v.fecha', [$reporte->periodo_inicio->toDateString(), $reporte->periodo_fin->toDateString()])
            ->whereNull('v.deleted_at')
            ->where('v.es_referencia_fiscal', false)
            ->where(fn($q) => $q->whereNull('v.documento_tipo')->orWhere('v.documento_tipo', '!=', 'nota_credito'))
            ->selectRaw("v.id, v.fecha, v.documento_tipo, v.documento_numero,
                         COALESCE(c.nombre,'') as cliente, COALESCE(vend.nombre,'') as vendedor,
                         COALESCE(v.metodo_pago,'') as metodo_pago,
                         v.total + COALESCE(v.ajuste, 0) as total, v.estado,
                         v.reporte_semanal_id")
            ->orderBy('v.fecha')
            ->get();

        // Archivadas en este reporte
        $ventas = $ventasBase->where('reporte_semanal_id', $id)->values();

        // Pendientes al momento del cierre: estaban en el rango pero NO se archivaron en este reporte
        // Pueden estar aún abiertas (null) o haber sido archivadas en una semana posterior.
        $ventasPendientes = $ventasBase->where('reporte_semanal_id', '!=', $id)->values()
            ->map(fn($v) => (object) array_merge((array) $v, [
                'archivada_en_otra_semana' => $v->reporte_semanal_id !== null,
            ]));

        $compras = DB::table('compras')
            ->where('reporte_semanal_id', $id)
            ->selectRaw('fecha, empresa, documento_tipo, documento_numero, metodo_pago, monto_total')
            ->orderBy('fecha')
            ->get();

        return response()->json([
            'reporte' => [
                'id' => $reporte->id,
                'periodo_inicio' => $reporte->periodo_inicio->format('d/m/Y'),
                'periodo_fin' => $reporte->periodo_fin->format('d/m/Y'),
                'total_ventas' => (float) $reporte->total_ventas,
                'cantidad_ventas' => $reporte->cantidad_ventas,
                'total_compras' => (float) $reporte->total_compras,
                'cantidad_compras' => $reporte->cantidad_compras,
                'total_costo' => (float) $reporte->total_costo,
                'utilidad' => (float) $reporte->utilidad,
                'margen' => (float) $reporte->margen,
                'comision_utilidad' => (float) $reporte->comision_utilidad,
                'ventas_pendientes' => $reporte->ventas_pendientes,
                'cerrado_por' => $reporte->cerradoPor?->name,
                'cerrado_en' => $reporte->created_at->format('d/m/Y H:i'),
            ],
            'ventas' => $ventas,
            'ventas_pendientes' => $ventasPendientes,
            'compras' => $compras,
        ]);
    }
}
