<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Models\Compra;
use App\Models\Vendedor;
use App\Models\Cliente;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ReporteController extends Controller
{
    private function rango(string $periodo): array
    {
        return match ($periodo) {
            'semanal' => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
            'mensual' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
            default   => [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()],
        };
    }

    private function aplicarFiltros(\Illuminate\Database\Eloquent\Builder $q, Request $request): void
    {
        if ($request->filled('vendedor_id')) $q->where('vendedor_id', $request->vendedor_id);
        if ($request->filled('metodo_pago')) $q->where('metodo_pago', $request->metodo_pago);
        if ($request->filled('cliente_id'))  $q->where('cliente_id', $request->cliente_id);
    }

    private function resolverRango(Request $request, string $periodo): array
    {
        [$desde, $hasta] = $this->rango($periodo);
        if ($request->filled('desde')) $desde = Carbon::parse($request->desde)->startOfDay();
        if ($request->filled('hasta')) $hasta = Carbon::parse($request->hasta)->endOfDay();
        return [$desde, $hasta];
    }

    public function index(): \Illuminate\View\View
    {
        $vendedores = Vendedor::orderBy('nombre')->get(['id', 'nombre']);
        $clientes   = Cliente::orderBy('nombre')->limit(300)->get(['id', 'nombre']);
        return view('reportes', compact('vendedores', 'clientes'));
    }

    public function datos(Request $request): JsonResponse
    {
        $periodo        = $request->input('periodo', 'diario');
        [$desde, $hasta] = $this->resolverRango($request, $periodo);

        // ── Ventas ────────────────────────────────────────────
        $qVentas = Venta::whereBetween('fecha', [$desde, $hasta])
            ->where('estado', '!=', 'anulado')
            ->where(fn($q) => $q->whereNull('documento_tipo')
                ->orWhere('documento_tipo', '!=', 'nota_credito'));
        $this->aplicarFiltros($qVentas, $request);

        $ventas       = $qVentas->with(['detalles.producto', 'cliente', 'vendedor'])->get();
        $totalVentas  = (float) $ventas->sum('total');
        $cantVentas   = $ventas->count();
        $igvVentas    = round($totalVentas * 18 / 118, 2);
        $subtotalV    = round($totalVentas / 1.18, 2);

        $porMetodo = $ventas->groupBy('metodo_pago')
            ->map(fn($g, $k) => [
                'metodo' => $k ?: 'No especificado',
                'total'  => round((float) $g->sum('total'), 2),
                'count'  => $g->count(),
            ])->sortByDesc('total')->values();

        $topClientes = $ventas->groupBy('cliente_id')
            ->map(fn($g) => [
                'nombre' => $g->first()->cliente->nombre ?? 'Sin cliente',
                'total'  => round((float) $g->sum('total'), 2),
                'count'  => $g->count(),
            ])->sortByDesc('total')->take(8)->values();

        $allDetalles = $ventas->flatMap->detalles;

        $topProductos = $allDetalles
            ->groupBy(fn($d) => $d->getAttributes()['producto'] ?? 'Manual')
            ->map(fn($g, $k) => [
                'nombre'   => $k,
                'cantidad' => round((float) $g->sum('cantidad'), 2),
                'total'    => round((float) $g->sum('subtotal'), 2),
            ])->sortByDesc('total')->take(8)->values();

        $comprobantes = $ventas->groupBy('documento_tipo')
            ->map(fn($g, $k) => [
                'tipo'  => $k ?: 'Sin tipo',
                'count' => $g->count(),
                'total' => round((float) $g->sum('total'), 2),
            ])->values();

        $porDia = $ventas->groupBy(fn($v) => $v->fecha->format('Y-m-d'))
            ->map(fn($g, $k) => [
                'fecha' => $k,
                'total' => round((float) $g->sum('total'), 2),
                'count' => $g->count(),
            ])->sortBy('fecha')->values();

        // ── Costo / Utilidad ──────────────────────────────────
        $totalCosto = $allDetalles->sum(function ($d) {
            $costo = (float) ($d->producto?->precio_costo ?? 0);
            return $costo * (float) $d->cantidad;
        });
        $utilidad = round($totalVentas - $totalCosto, 2);
        $margen   = $totalVentas > 0 ? round($utilidad / $totalVentas * 100, 1) : 0;

        // ── Compras ───────────────────────────────────────────
        $qCompras = Compra::whereBetween('fecha', [$desde, $hasta]);
        $compras  = $qCompras->with(['lineas.producto'])->get();

        $totalCompras = (float) $compras->sum('monto_total');
        $cantCompras  = $compras->count();
        $igvCompras   = round($totalCompras * 18 / 118, 2);

        $porProveedor = $compras->groupBy('empresa')
            ->map(fn($g, $k) => [
                'proveedor' => $k ?: 'Sin proveedor',
                'total'     => round((float) $g->sum('monto_total'), 2),
                'count'     => $g->count(),
            ])->sortByDesc('total')->values();

        $allLineas = $compras->flatMap->lineas;
        $topProductosCompra = $allLineas
            ->groupBy(fn($l) => $l->getAttributes()['producto'] ?? 'Manual')
            ->map(fn($g, $k) => [
                'nombre'   => $k,
                'cantidad' => round((float) $g->sum('cantidad'), 2),
                'total'    => round((float) $g->sum('monto_total'), 2),
            ])->sortByDesc('total')->take(8)->values();

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
                'total'          => $totalCompras,
                'cantidad'       => $cantCompras,
                'igv'            => $igvCompras,
                'neto'           => round($totalCompras, 2),
                'por_proveedor'  => $porProveedor,
                'top_productos'  => $topProductosCompra,
            ],
            'utilidad' => [
                'total_ventas' => round($totalVentas, 2),
                'total_costo'  => round($totalCosto, 2),
                'utilidad'     => $utilidad,
                'margen'       => $margen,
                'invertido'    => round($totalCompras, 2),
                'recuperado'   => round($totalVentas, 2),
            ],
        ]);
    }

    public function utilidadDetalle(Request $request): JsonResponse
    {
        $periodo        = $request->input('periodo', 'diario');
        [$desde, $hasta] = $this->resolverRango($request, $periodo);

        $qVentas = Venta::whereBetween('fecha', [$desde, $hasta])
            ->where('estado', '!=', 'anulado')
            ->where(fn($q) => $q->whereNull('documento_tipo')
                ->orWhere('documento_tipo', '!=', 'nota_credito'));
        $this->aplicarFiltros($qVentas, $request);

        $ventas = $qVentas->with(['detalles.producto', 'cliente'])->get();

        $detalle = $ventas->map(function ($v) {
            $lineas = $v->detalles->map(function ($d) {
                $costo    = (float) ($d->producto?->precio_costo ?? 0);
                $qty      = (float) $d->cantidad;
                $pv       = (float) $d->precio_unitario;
                $ganancia = round(($pv - $costo) * $qty, 2);
                return [
                    'producto'    => $d->getAttributes()['producto'] ?? 'Manual',
                    'codigo'      => $d->codigo ?? '',
                    'cantidad'    => $qty,
                    'precio_venta'=> $pv,
                    'costo'       => $costo,
                    'subtotal'    => (float) $d->subtotal,
                    'ganancia'    => $ganancia,
                ];
            })->values();

            return [
                'id'          => $v->id,
                'numero'      => trim(ucfirst($v->documento_tipo ?? 'Venta') . ' ' . ($v->documento_numero ?? '#' . $v->id)),
                'fecha'       => $v->fecha->format('d/m/Y'),
                'cliente'     => $v->cliente?->nombre ?? 'Sin cliente',
                'total'       => (float) $v->total,
                'ganancia'    => round($lineas->sum('ganancia'), 2),
                'lineas'      => $lineas,
            ];
        })->sortByDesc('fecha')->values();

        return response()->json(['detalle' => $detalle]);
    }

    public function exportExcel(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $periodo        = $request->input('periodo', 'diario');
        [$desde, $hasta] = $this->resolverRango($request, $periodo);

        $qVentas = Venta::whereBetween('fecha', [$desde, $hasta])
            ->where('estado', '!=', 'anulado')
            ->with(['detalles.producto', 'cliente', 'vendedor']);
        $this->aplicarFiltros($qVentas, $request);
        $ventas = $qVentas->orderBy('fecha')->get();

        $qCompras = Compra::whereBetween('fecha', [$desde, $hasta])->with('lineas');
        $compras  = $qCompras->orderBy('fecha')->get();

        $spreadsheet = new Spreadsheet();

        // ── Hoja Ventas ──────────────────────────────────────
        $sv = $spreadsheet->getActiveSheet();
        $sv->setTitle('Ventas');
        $hV = ['Fecha','Documento','Nro.','Cliente','Vendedor','Método Pago','Total','IGV','Subtotal','Estado'];
        $sv->fromArray($hV, null, 'A1');
        $this->estiloHeader($sv, 'A1:J1', '2563EB');

        $row = 2;
        foreach ($ventas as $v) {
            $total = (float) $v->total;
            $sv->fromArray([
                $v->fecha->format('d/m/Y'),
                ucfirst($v->documento_tipo ?? ''),
                $v->documento_numero ?? '',
                $v->cliente?->nombre ?? '',
                $v->vendedor?->nombre ?? '',
                ucfirst($v->metodo_pago ?? ''),
                $total,
                round($total * 18 / 118, 2),
                round($total / 1.18, 2),
                ucfirst($v->estado ?? ''),
            ], null, "A{$row}");
            $row++;
        }
        foreach (range('G', 'I') as $col) {
            $sv->getStyle("{$col}2:{$col}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        }
        foreach (range('A', 'J') as $col) {
            $sv->getColumnDimension($col)->setAutoSize(true);
        }

        // ── Hoja Compras ─────────────────────────────────────
        $sc = $spreadsheet->createSheet();
        $sc->setTitle('Compras');
        $hC = ['Fecha','Empresa','Tipo Doc.','Nro. Doc.','Método Pago','Total','IGV'];
        $sc->fromArray($hC, null, 'A1');
        $this->estiloHeader($sc, 'A1:G1', '059669');
        $row = 2;
        foreach ($compras as $c) {
            $total = (float) $c->monto_total;
            $sc->fromArray([
                $c->fecha->format('d/m/Y'),
                $c->empresa ?? '',
                ucfirst($c->documento_tipo ?? ''),
                $c->documento_numero ?? '',
                ucfirst($c->metodo_pago ?? ''),
                $total,
                round($total * 18 / 118, 2),
            ], null, "A{$row}");
            $row++;
        }
        foreach (range('F', 'G') as $col) {
            $sc->getStyle("{$col}2:{$col}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        }
        foreach (range('A', 'G') as $col) {
            $sc->getColumnDimension($col)->setAutoSize(true);
        }

        // ── Hoja Resumen ─────────────────────────────────────
        $sr = $spreadsheet->createSheet();
        $sr->setTitle('Resumen');
        $totalV = $ventas->sum('total');
        $allD   = $ventas->flatMap->detalles;
        $costoT = $allD->sum(fn($d) => (float) ($d->producto?->precio_costo ?? 0) * (float) $d->cantidad);
        $totalC = $compras->sum('monto_total');

        $sr->fromArray(['Métrica', 'Valor'], null, 'A1');
        $this->estiloHeader($sr, 'A1:B1', '7C3AED');
        $sr->fromArray([
            ['Período',    ucfirst($periodo)],
            ['Desde',      $desde->format('d/m/Y')],
            ['Hasta',      $hasta->format('d/m/Y')],
            ['',''],
            ['Total Ventas',  round($totalV, 2)],
            ['Cantidad Ventas', $ventas->count()],
            ['IGV Ventas',  round($totalV * 18 / 118, 2)],
            ['Subtotal Ventas', round($totalV / 1.18, 2)],
            ['',''],
            ['Total Compras',  round($totalC, 2)],
            ['Cantidad Compras', $compras->count()],
            ['',''],
            ['Costo Productos', round($costoT, 2)],
            ['Utilidad',  round($totalV - $costoT, 2)],
            ['Margen %',  $totalV > 0 ? round(($totalV - $costoT) / $totalV * 100, 1) : 0],
        ], null, 'A2');
        $sr->getColumnDimension('A')->setWidth(22);
        $sr->getColumnDimension('B')->setAutoSize(true);

        $spreadsheet->setActiveSheetIndex(0);
        $filename = 'reporte-' . $periodo . '-' . now()->format('Y-m-d') . '.xlsx';
        $path     = storage_path('app/' . $filename);
        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, $filename)->deleteFileAfterSend(true);
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
