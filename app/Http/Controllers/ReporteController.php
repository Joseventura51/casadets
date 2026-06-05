<?php

namespace App\Http\Controllers;

use App\Models\Vendedor;
use App\Models\Cliente;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ReporteController extends Controller
{
    // ── Helpers ────────────────────────────────────────────
    private function rango(string $periodo): array
    {
        return match ($periodo) {
            'semanal' => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
            'mensual' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
            default   => [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()],
        };
    }

    private function resolverRango(Request $r, string $periodo): array
    {
        [$d, $h] = $this->rango($periodo);
        if ($r->filled('desde')) $d = Carbon::parse($r->desde)->startOfDay();
        if ($r->filled('hasta')) $h = Carbon::parse($r->hasta)->endOfDay();
        return [$d, $h];
    }

    /**
     * Base query para ventas con filtros comunes aplicados.
     * Devuelve un query builder sobre la tabla `ventas`.
     */
    private function qVentas(Request $r, $desde, $hasta)
    {
        $q = DB::table('ventas')
            ->whereBetween('ventas.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->where('ventas.estado', '!=', 'anulado')
            ->whereNull('ventas.deleted_at')
            ->where('ventas.es_referencia_fiscal', false)
            ->where(fn($q) => $q->whereNull('ventas.documento_tipo')
                ->orWhere('ventas.documento_tipo', '!=', 'nota_credito'));

        $ids = \App\Services\VendedorScope::ids();
        if ($ids !== null) {
            $q->whereIn('ventas.vendedor_id', $ids);
        } elseif ($r->filled('vendedor_id')) {
            $q->where('ventas.vendedor_id', $r->vendedor_id);
        }

        if ($r->filled('metodo_pago')) $q->where('ventas.metodo_pago', $r->metodo_pago);
        if ($r->filled('cliente_id'))  $q->where('ventas.cliente_id',  $r->cliente_id);

        return $q;
    }

    /** Condiciones de ventas válidas para joins desde venta_detalles */
    private function qDetalles(Request $r, $desde, $hasta)
    {
        $q = DB::table('venta_detalles as vd')
            ->join('ventas as v', 'vd.venta_id', '=', 'v.id')
            ->leftJoin('productos as p', 'vd.producto_id', '=', 'p.id')
            ->whereBetween('v.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->where('v.estado', '!=', 'anulado')
            ->whereNull('v.deleted_at')
            ->where('v.es_referencia_fiscal', false)
            ->where(fn($q) => $q->whereNull('v.documento_tipo')
                ->orWhere('v.documento_tipo', '!=', 'nota_credito'));

        $ids = \App\Services\VendedorScope::ids();
        if ($ids !== null) {
            $q->whereIn('v.vendedor_id', $ids);
        } elseif ($r->filled('vendedor_id')) {
            $q->where('v.vendedor_id', $r->vendedor_id);
        }

        if ($r->filled('metodo_pago')) $q->where('v.metodo_pago', $r->metodo_pago);
        if ($r->filled('cliente_id'))  $q->where('v.cliente_id', $r->cliente_id);

        return $q;
    }

    // ── Controllers ────────────────────────────────────────
    public function index(): \Illuminate\View\View
    {
        $vendedores = Vendedor::orderBy('nombre')->get(['id', 'nombre']);
        $clientes   = Cliente::orderBy('nombre')->limit(300)->get(['id', 'nombre']);
        return view('reportes', compact('vendedores', 'clientes'));
    }

    public function datos(Request $r): JsonResponse
    {
        try {
        $periodo        = $r->input('periodo', 'diario');
        [$desde, $hasta] = $this->resolverRango($r, $periodo);

        // ── Ventas: summary ─────────────────────────────
        $vSum = $this->qVentas($r, $desde, $hasta)
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total),0) as total')
            ->first();

        $totalVentas = (float) ($vSum->total    ?? 0);
        $cantVentas  = (int)   ($vSum->cantidad ?? 0);
        $igvVentas   = round($totalVentas * 18 / 118, 2);
        $subtotalV   = round($totalVentas / 1.18, 2);

        // ── Por método de pago ───────────────────────────
        $porMetodo = $this->qVentas($r, $desde, $hasta)
            ->selectRaw("COALESCE(metodo_pago,'No especificado') as metodo, COUNT(*) as count, SUM(total) as total")
            ->groupBy('metodo_pago')
            ->orderByDesc('total')
            ->get()
            ->map(fn($m) => ['metodo' => $m->metodo, 'total' => (float)$m->total, 'count' => (int)$m->count]);

        // ── Top clientes ────────────────────────────────
        $topClientes = $this->qVentas($r, $desde, $hasta)
            ->leftJoin('clientes as cl', 'ventas.cliente_id', '=', 'cl.id')
            ->selectRaw("COALESCE(cl.nombre,'Sin cliente') as nombre, COUNT(*) as count, SUM(ventas.total) as total")
            ->groupBy('ventas.cliente_id', 'cl.nombre')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn($c) => ['nombre' => $c->nombre, 'total' => (float)$c->total, 'count' => (int)$c->count]);

        // ── Top productos vendidos ───────────────────────
        $topProductos = $this->qDetalles($r, $desde, $hasta)
            ->selectRaw("COALESCE(vd.producto,'Manual') as nombre, SUM(vd.cantidad) as cantidad, SUM(vd.subtotal) as total")
            ->groupBy('vd.producto')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn($p) => ['nombre' => $p->nombre, 'cantidad' => (float)$p->cantidad, 'total' => (float)$p->total]);

        // ── Comprobantes ─────────────────────────────────
        $comprobantes = $this->qVentas($r, $desde, $hasta)
            ->selectRaw("COALESCE(documento_tipo,'Sin tipo') as tipo, COUNT(*) as count, SUM(total) as total")
            ->groupBy('documento_tipo')
            ->get()
            ->map(fn($c) => ['tipo' => $c->tipo, 'count' => (int)$c->count, 'total' => (float)$c->total]);

        // ── Ventas por día (sólo tabla ventas, sin JOIN a detalles para no multiplicar) ─
        $ventasPorDia = $this->qVentas($r, $desde, $hasta)
            ->selectRaw("DATE(ventas.fecha) as fecha, COUNT(*) as count, SUM(ventas.total) as total_ventas")
            ->groupByRaw('DATE(ventas.fecha)')
            ->orderBy('fecha')
            ->get()
            ->keyBy('fecha');

        // ── Costo por día (desde venta_detalles, con los mismos filtros) ─────────
        $costoPorDia = $this->qDetalles($r, $desde, $hasta)
            ->selectRaw("DATE(v.fecha) as fecha, SUM(vd.cantidad * COALESCE(p.precio_costo, 0)) as total_costo")
            ->groupByRaw('DATE(v.fecha)')
            ->get()
            ->keyBy('fecha');

        // ── Merge por fecha ──────────────────────────────────────────────────────
        $porDia = $ventasPorDia->map(function ($v) use ($costoPorDia) {
            $costo = (float) ($costoPorDia[$v->fecha]->total_costo ?? 0);
            return [
                'fecha'    => $v->fecha,
                'count'    => (int)   $v->count,
                'total'    => (float) $v->total_ventas,
                'costo'    => round($costo, 2),
                'utilidad' => round((float)$v->total_ventas - $costo, 2),
            ];
        })->values();

        // ── Costo y utilidad totales ─────────────────────
        $totalCosto = (float) $this->qDetalles($r, $desde, $hasta)
            ->selectRaw('SUM(vd.cantidad * COALESCE(p.precio_costo, 0)) as total')
            ->value('total');

        $utilidad = round($totalVentas - $totalCosto, 2);
        $margen   = $totalVentas > 0 ? round($utilidad / $totalVentas * 100, 1) : 0;

        // ── Compras ──────────────────────────────────────
        $cmpSum = DB::table('compras')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(monto_total),0) as total')
            ->first();

        $totalCompras = (float) ($cmpSum->total    ?? 0);
        $cantCompras  = (int)   ($cmpSum->cantidad ?? 0);
        $igvCompras   = round($totalCompras * 18 / 118, 2);

        $porProveedor = DB::table('compras')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereNull('deleted_at')
            ->selectRaw("COALESCE(empresa,'Sin proveedor') as proveedor, COUNT(*) as count, SUM(monto_total) as total")
            ->groupBy('empresa')
            ->orderByDesc('total')
            ->get()
            ->map(fn($p) => ['proveedor' => $p->proveedor, 'total' => (float)$p->total, 'count' => (int)$p->count]);

        $topProductosCompra = DB::table('compra_lineas as cl')
            ->join('compras as c', 'cl.compra_id', '=', 'c.id')
            ->whereBetween('c.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereNull('c.deleted_at')
            ->selectRaw("COALESCE(cl.producto,'Manual') as nombre, SUM(cl.cantidad) as cantidad, SUM(cl.monto_total) as total")
            ->groupBy('cl.producto')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn($p) => ['nombre' => $p->nombre, 'cantidad' => (float)$p->cantidad, 'total' => (float)$p->total]);

        // Comisión de vendedores sobre Total a Cobrar
        $vendorIdsComision = \App\Services\VendedorScope::ids();
        $comisionTotal = (float) DB::table('ventas as v')
            ->join('vendedores as vend', 'v.vendedor_id', '=', 'vend.id')
            ->whereBetween('v.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->where('v.estado', '!=', 'anulado')
            ->whereNull('v.deleted_at')
            ->where('v.es_referencia_fiscal', false)
            ->where(fn($q) => $q->whereNull('v.documento_tipo')->orWhere('v.documento_tipo', '!=', 'nota_credito'))
            ->when($vendorIdsComision !== null, fn($q) => $q->whereIn('v.vendedor_id', $vendorIdsComision))
            ->when($vendorIdsComision === null && $r->filled('vendedor_id'), fn($q) => $q->where('v.vendedor_id', $r->vendedor_id))
            ->selectRaw('COALESCE(SUM((v.total + COALESCE(v.ajuste, 0)) * COALESCE(vend.comision_porcentaje, 0) / 100), 0) as total')
            ->value('total');

        return response()->json([
            'periodo' => $periodo,
            'desde'   => $desde->format('d/m/Y'),
            'hasta'   => $hasta->format('d/m/Y'),
            'ventas'  => [
                'total'         => $totalVentas,
                'cantidad'      => $cantVentas,
                'igv'           => $igvVentas,
                'subtotal'      => $subtotalV,
                'por_metodo'    => $porMetodo,
                'top_clientes'  => $topClientes,
                'top_productos' => $topProductos,
                'comprobantes'  => $comprobantes,
                'por_dia'       => $porDia,
            ],
            'compras' => [
                'total'         => $totalCompras,
                'cantidad'      => $cantCompras,
                'igv'           => $igvCompras,
                'neto'          => $totalCompras,
                'por_proveedor' => $porProveedor,
                'top_productos' => $topProductosCompra,
            ],
            'utilidad' => [
                'total_ventas'   => round($totalVentas, 2),
                'total_costo'    => round($totalCosto, 2),
                'utilidad'       => $utilidad,
                'margen'         => $margen,
                'invertido'      => round($totalCompras, 2),
                'recuperado'     => round($totalVentas, 2),
                'comision_total' => round($comisionTotal, 2),
            ],
        ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => '[' . class_basename($e) . '] ' . $e->getMessage()
                         . ' (línea ' . $e->getLine() . ' en ' . basename($e->getFile()) . ')',
            ], 500);
        }
    }

    public function utilidadDetalle(Request $r): JsonResponse
    {
        $periodo        = $r->input('periodo', 'diario');
        [$desde, $hasta] = $this->resolverRango($r, $periodo);

        // IDs de ventas válidas
        $ventaIds = $this->qVentas($r, $desde, $hasta)->pluck('id');

        if ($ventaIds->isEmpty()) {
            return response()->json(['detalle' => []]);
        }

        // Traer ventas con sus detalles y costos en una sola consulta
        $detalles = DB::table('venta_detalles as vd')
            ->leftJoin('productos as p', 'vd.producto_id', '=', 'p.id')
            ->whereIn('vd.venta_id', $ventaIds)
            ->selectRaw("vd.venta_id,
                         COALESCE(vd.producto,'Manual')      as nombre_producto,
                         vd.codigo,
                         vd.cantidad,
                         vd.precio_unitario,
                         vd.subtotal,
                         COALESCE(p.precio_costo, 0)         as precio_costo,
                         (vd.precio_unitario - COALESCE(p.precio_costo,0)) * vd.cantidad as ganancia")
            ->orderBy('vd.venta_id')
            ->get()
            ->groupBy('venta_id');

        $ventas = DB::table('ventas as v')
            ->leftJoin('clientes as c', 'v.cliente_id', '=', 'c.id')
            ->whereIn('v.id', $ventaIds)
            ->selectRaw("v.id, v.documento_tipo, v.documento_numero, v.fecha, v.total,
                         COALESCE(c.nombre,'Sin cliente') as cliente")
            ->orderByDesc('v.fecha')
            ->get();

        $resultado = $ventas->map(function ($v) use ($detalles) {
            $lineas = ($detalles[$v->id] ?? collect())->map(fn($d) => [
                'producto'    => $d->nombre_producto,
                'codigo'      => $d->codigo ?? '',
                'cantidad'    => (float) $d->cantidad,
                'precio_venta'=> (float) $d->precio_unitario,
                'costo'       => (float) $d->precio_costo,
                'subtotal'    => (float) $d->subtotal,
                'ganancia'    => round((float) $d->ganancia, 2),
            ])->values();

            $numero = trim(ucfirst($v->documento_tipo ?? 'Venta') . ' ' . ($v->documento_numero ?? '#' . $v->id));
            return [
                'id'       => $v->id,
                'numero'   => $numero,
                'fecha'    => Carbon::parse($v->fecha)->format('d/m/Y'),
                'cliente'  => $v->cliente,
                'total'    => (float) $v->total,
                'ganancia' => round($lineas->sum('ganancia'), 2),
                'lineas'   => $lineas,
            ];
        });

        return response()->json(['detalle' => $resultado]);
    }

    public function exportExcel(Request $r): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $periodo        = $r->input('periodo', 'diario');
        [$desde, $hasta] = $this->resolverRango($r, $periodo);

        $vendorIds = \App\Services\VendedorScope::ids();

        $ventas = DB::table('ventas as v')
            ->leftJoin('clientes as c', 'v.cliente_id', '=', 'c.id')
            ->leftJoin('vendedores as vend', 'v.vendedor_id', '=', 'vend.id')
            ->whereBetween('v.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->where('v.estado', '!=', 'anulado')
            ->whereNull('v.deleted_at')
            ->where(fn($q) => $q->whereNull('v.documento_tipo')->orWhere('v.documento_tipo', '!=', 'nota_credito'))
            ->when($vendorIds !== null, fn($q) => $q->whereIn('v.vendedor_id', $vendorIds))
            ->when($vendorIds === null && $r->filled('vendedor_id'), fn($q) => $q->where('v.vendedor_id', $r->vendedor_id))
            ->when($r->filled('metodo_pago'), fn($q) => $q->where('v.metodo_pago', $r->metodo_pago))
            ->when($r->filled('cliente_id'),  fn($q) => $q->where('v.cliente_id',  $r->cliente_id))
            ->selectRaw("v.fecha, v.documento_tipo, v.documento_numero,
                         COALESCE(c.nombre,'') as cliente, COALESCE(vend.nombre,'') as vendedor,
                         COALESCE(v.metodo_pago,'') as metodo_pago, v.total, v.estado")
            ->orderBy('v.fecha')
            ->get();

        $compras = DB::table('compras')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereNull('deleted_at')
            ->selectRaw("fecha, empresa, documento_tipo, documento_numero, metodo_pago, monto_total")
            ->orderBy('fecha')
            ->get();

        // Resumen utilidad (mismos filtros que ventas: excluye anulado + nota_credito)
        $totalV  = $ventas->sum('total');
        $costoT  = (float) DB::table('venta_detalles as vd')
            ->join('ventas as v', 'vd.venta_id', '=', 'v.id')
            ->leftJoin('productos as p', 'vd.producto_id', '=', 'p.id')
            ->whereBetween('v.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->where('v.estado', '!=', 'anulado')
            ->whereNull('v.deleted_at')
            ->where(fn($q) => $q->whereNull('v.documento_tipo')->orWhere('v.documento_tipo', '!=', 'nota_credito'))
            ->when($vendorIds !== null, fn($q) => $q->whereIn('v.vendedor_id', $vendorIds))
            ->when($vendorIds === null && $r->filled('vendedor_id'), fn($q) => $q->where('v.vendedor_id', $r->vendedor_id))
            ->when($r->filled('metodo_pago'), fn($q) => $q->where('v.metodo_pago', $r->metodo_pago))
            ->when($r->filled('cliente_id'),  fn($q) => $q->where('v.cliente_id',  $r->cliente_id))
            ->selectRaw('SUM(vd.cantidad * COALESCE(p.precio_costo,0)) as total')
            ->value('total');
        $totalC = $compras->sum('monto_total');

        $spreadsheet = new Spreadsheet();

        // ── Hoja Ventas ──────────────────────────────────
        $sv = $spreadsheet->getActiveSheet()->setTitle('Ventas');
        $sv->fromArray(['Fecha','Documento','Nro.','Cliente','Vendedor','Método','Total','IGV','Base','Estado'], null, 'A1');
        $this->estiloHeader($sv, 'A1:J1', '2563EB');
        $row = 2;
        foreach ($ventas as $v) {
            $total = (float) $v->total;
            $sv->fromArray([
                Carbon::parse($v->fecha)->format('d/m/Y'),
                ucfirst($v->documento_tipo ?? ''),
                $v->documento_numero ?? '',
                $v->cliente,
                $v->vendedor,
                ucfirst($v->metodo_pago),
                $total,
                round($total * 18 / 118, 2),
                round($total / 1.18, 2),
                ucfirst($v->estado ?? ''),
            ], null, "A{$row}");
            $row++;
        }
        foreach (range('G','I') as $col) {
            $sv->getStyle("{$col}2:{$col}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        }
        foreach (range('A','J') as $col) { $sv->getColumnDimension($col)->setAutoSize(true); }

        // ── Hoja Compras ─────────────────────────────────
        $sc = $spreadsheet->createSheet()->setTitle('Compras');
        $sc->fromArray(['Fecha','Empresa','Tipo Doc.','Nro. Doc.','Método','Total','IGV'], null, 'A1');
        $this->estiloHeader($sc, 'A1:G1', '059669');
        $row = 2;
        foreach ($compras as $c) {
            $total = (float) $c->monto_total;
            $sc->fromArray([
                Carbon::parse($c->fecha)->format('d/m/Y'),
                $c->empresa ?? '',
                ucfirst($c->documento_tipo ?? ''),
                $c->documento_numero ?? '',
                ucfirst($c->metodo_pago ?? ''),
                $total,
                round($total * 18 / 118, 2),
            ], null, "A{$row}");
            $row++;
        }
        foreach (range('F','G') as $col) {
            $sc->getStyle("{$col}2:{$col}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        }
        foreach (range('A','G') as $col) { $sc->getColumnDimension($col)->setAutoSize(true); }

        // ── Hoja Resumen ─────────────────────────────────
        $sr = $spreadsheet->createSheet()->setTitle('Resumen');
        $sr->fromArray(['Métrica','Valor'], null, 'A1');
        $this->estiloHeader($sr, 'A1:B1', '7C3AED');
        $sr->fromArray([
            ['Período',         ucfirst($periodo)],
            ['Desde',           $desde->format('d/m/Y')],
            ['Hasta',           $hasta->format('d/m/Y')],
            ['',''],
            ['Total Ventas',    round($totalV, 2)],
            ['Cantidad Ventas', $ventas->count()],
            ['IGV Ventas',      round($totalV * 18 / 118, 2)],
            ['Subtotal Ventas', round($totalV / 1.18, 2)],
            ['',''],
            ['Total Compras',   round($totalC, 2)],
            ['Cantidad Compras',$compras->count()],
            ['',''],
            ['Costo Productos', round($costoT, 2)],
            ['Utilidad',        round($totalV - $costoT, 2)],
            ['Margen %',        $totalV > 0 ? round(($totalV - $costoT) / $totalV * 100, 1) : 0],
        ], null, 'A2');
        $sr->getColumnDimension('A')->setWidth(22);
        $sr->getColumnDimension('B')->setAutoSize(true);

        $spreadsheet->setActiveSheetIndex(0);
        $filename = 'reporte-' . $periodo . '-' . now()->format('Y-m-d') . '.xlsx';
        $path     = storage_path('app/' . $filename);
        (new Xlsx($spreadsheet))->save($path);
        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    public function exportPdf(Request $r): \Illuminate\Http\Response
    {
        $periodo        = $r->input('periodo', 'diario');
        [$desde, $hasta] = $this->resolverRango($r, $periodo);

        // Reusa la misma lógica de datos pero con colección pequeña para el PDF
        $vendorIds = \App\Services\VendedorScope::ids();

        $ventasSummary = DB::table('ventas')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->where('estado', '!=', 'anulado')
            ->whereNull('deleted_at')
            ->where(fn($q) => $q->whereNull('documento_tipo')->orWhere('documento_tipo', '!=', 'nota_credito'))
            ->when($vendorIds !== null, fn($q) => $q->whereIn('vendedor_id', $vendorIds))
            ->when($vendorIds === null && $r->filled('vendedor_id'), fn($q) => $q->where('vendedor_id', $r->vendedor_id))
            ->when($r->filled('metodo_pago'), fn($q) => $q->where('metodo_pago', $r->metodo_pago))
            ->when($r->filled('cliente_id'),  fn($q) => $q->where('cliente_id',  $r->cliente_id))
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total),0) as total')
            ->first();

        $totalVentas = (float) ($ventasSummary->total    ?? 0);
        $cantVentas  = (int)   ($ventasSummary->cantidad ?? 0);

        $comprasSummary = DB::table('compras')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(monto_total),0) as total')
            ->first();

        $totalCompras = (float) ($comprasSummary->total    ?? 0);
        $cantCompras  = (int)   ($comprasSummary->cantidad ?? 0);

        $totalCosto = (float) DB::table('venta_detalles as vd')
            ->join('ventas as v', 'vd.venta_id', '=', 'v.id')
            ->leftJoin('productos as p', 'vd.producto_id', '=', 'p.id')
            ->whereBetween('v.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->where('v.estado', '!=', 'anulado')
            ->whereNull('v.deleted_at')
            ->where(fn($q) => $q->whereNull('v.documento_tipo')->orWhere('v.documento_tipo', '!=', 'nota_credito'))
            ->when($vendorIds !== null, fn($q) => $q->whereIn('v.vendedor_id', $vendorIds))
            ->when($vendorIds === null && $r->filled('vendedor_id'), fn($q) => $q->where('v.vendedor_id', $r->vendedor_id))
            ->when($r->filled('metodo_pago'), fn($q) => $q->where('v.metodo_pago', $r->metodo_pago))
            ->when($r->filled('cliente_id'),  fn($q) => $q->where('v.cliente_id',  $r->cliente_id))
            ->selectRaw('SUM(vd.cantidad * COALESCE(p.precio_costo,0)) as total')
            ->value('total');

        $topClientes = DB::table('ventas as v')
            ->leftJoin('clientes as c', 'v.cliente_id', '=', 'c.id')
            ->whereBetween('v.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->where('v.estado', '!=', 'anulado')
            ->whereNull('v.deleted_at')
            ->when($vendorIds !== null, fn($q) => $q->whereIn('v.vendedor_id', $vendorIds))
            ->selectRaw("COALESCE(c.nombre,'Sin cliente') as nombre, COUNT(*) as count, SUM(v.total) as total")
            ->groupBy('v.cliente_id', 'c.nombre')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $topProductos = DB::table('venta_detalles as vd')
            ->join('ventas as v', 'vd.venta_id', '=', 'v.id')
            ->whereBetween('v.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->where('v.estado', '!=', 'anulado')
            ->whereNull('v.deleted_at')
            ->when($vendorIds !== null, fn($q) => $q->whereIn('v.vendedor_id', $vendorIds))
            ->selectRaw("COALESCE(vd.producto,'Manual') as nombre, SUM(vd.cantidad) as cantidad, SUM(vd.subtotal) as total")
            ->groupBy('vd.producto')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $data = compact(
            'periodo', 'desde', 'hasta',
            'totalVentas', 'cantVentas',
            'totalCompras', 'cantCompras',
            'totalCosto', 'topClientes', 'topProductos'
        );
        $data['utilidad'] = round($totalVentas - $totalCosto, 2);
        $data['margen']   = $totalVentas > 0 ? round($data['utilidad'] / $totalVentas * 100, 1) : 0;
        $data['igvVentas']  = round($totalVentas * 18 / 118, 2);
        $data['igvCompras'] = round($totalCompras * 18 / 118, 2);

        $pdf = app('dompdf.wrapper');
        $pdf->loadView('reportes-pdf', $data);
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download('reporte-' . $periodo . '-' . now()->format('Y-m-d') . '.pdf');
    }

    private function estiloHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $rango, string $color): void
    {
        $sheet->getStyle($rango)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders'   => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => strtoupper($color)]]],
        ]);
    }
}
