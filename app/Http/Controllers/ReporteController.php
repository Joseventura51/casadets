<?php

namespace App\Http\Controllers;

use App\Models\Vendedor;
use App\Models\Cliente;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

    private function empresasReportables(): \Illuminate\Support\Collection
    {
        $cajaIds = \App\Services\VendedorScope::cajaIds();
        $vendedorIds = \App\Services\VendedorScope::ids();
        $query = DB::table('cajas')->select('empresa')->distinct();
        if ($cajaIds !== null) {
            $query->whereIn('id', $cajaIds);
        } elseif ($vendedorIds !== null) {
            $query->whereIn('id', DB::table('ventas')
                ->whereIn('vendedor_id', $vendedorIds)
                ->whereNotNull('caja_id')
                ->select('caja_id')
                ->distinct());
        }

        return $query->orderBy('empresa')->pluck('empresa');
    }

    private function empresaReporte(Request $r): string
    {
        $disponibles = $this->empresasReportables();
        $seleccionada = $r->input('empresa');
        if (!$seleccionada && session()->has('caja_id')) {
            $seleccionada = DB::table('cajas')->where('id', session('caja_id'))->value('empresa');
        }
        if (!$seleccionada && $disponibles->count() === 1) {
            $seleccionada = $disponibles->first();
        }
        if (!$seleccionada && $disponibles->count() > 1) {
            throw ValidationException::withMessages([
                'empresa' => 'Selecciona una empresa para evitar mezclar sus reportes.',
            ]);
        }
        if (!$seleccionada || !$disponibles->contains($seleccionada)) {
            abort(403, 'No tienes acceso a los reportes de esa empresa.');
        }

        return $seleccionada;
    }

    private function aplicarEmpresaReporte($query, Request $r, string $cajaColumn)
    {
        $empresa = $this->empresaReporte($r);

        return $query->whereExists(function ($subquery) use ($empresa, $cajaColumn): void {
            $subquery->selectRaw('1')
                ->from('cajas as empresa_scope')
                ->whereColumn('empresa_scope.id', $cajaColumn)
                ->where('empresa_scope.empresa', $empresa);
        });
    }

    /**
     * Base query para ventas con filtros comunes aplicados.
     * Devuelve un query builder sobre la tabla `ventas`.
     */
    private function qVentas(Request $r, $desde, $hasta)
    {
        $q = DB::table('ventas')
            ->whereBetween('ventas.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereNotIn('ventas.estado', ['anulado', 'anulado_nc'])
            ->whereNull('ventas.deleted_at')
            ->whereNull('ventas.reporte_semanal_id')
            ->where('ventas.es_referencia_fiscal', false)
            ->where(fn($q) => $q->whereNull('ventas.documento_tipo')
                ->orWhere('ventas.documento_tipo', '!=', 'nota_credito'));

        $cajas = \App\Services\VendedorScope::cajaIds();
        if ($cajas !== null) {
            $q->whereIn('ventas.caja_id', $cajas);
        } else {
            $ids = \App\Services\VendedorScope::ids();
            if ($ids !== null) {
                $q->whereIn('ventas.vendedor_id', $ids);
            } elseif ($r->filled('vendedor_id')) {
                $q->where('ventas.vendedor_id', $r->vendedor_id);
            }
        }

        if ($r->filled('metodo_pago')) $q->where('ventas.metodo_pago', $r->metodo_pago);
        if ($r->filled('cliente_id'))  $q->where('ventas.cliente_id',  $r->cliente_id);

        return $this->aplicarEmpresaReporte($q, $r, 'ventas.caja_id');
    }

    /**
     * IDs de ventas aptas para calcular utilidad:
     * estado = 'pagado' Y todas sus líneas tienen costo completo en compra_venta_detalle.
     */
    private function ventaIdsParaUtilidad(Request $r, $desde, $hasta): \Illuminate\Support\Collection
    {
        $ids = $this->qVentas($r, $desde, $hasta)
            ->where('ventas.estado', 'pagado')
            ->pluck('id');

        return \App\Services\VentaCosteo::idsCompletamenteCosteados($ids);
    }

    /** Condiciones de ventas válidas para joins desde venta_detalles */
    private function qDetalles(Request $r, $desde, $hasta)
    {
        $q = DB::table('venta_detalles as vd')
            ->join('ventas as v', 'vd.venta_id', '=', 'v.id')
            ->leftJoin('productos as p', 'vd.producto_id', '=', 'p.id')
            ->whereBetween('v.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereNotIn('v.estado', ['anulado', 'anulado_nc'])
            ->whereNull('v.deleted_at')
            ->whereNull('v.reporte_semanal_id')
            ->where('v.es_referencia_fiscal', false)
            ->where(fn($q) => $q->whereNull('v.documento_tipo')
                ->orWhere('v.documento_tipo', '!=', 'nota_credito'));

        $cajas = \App\Services\VendedorScope::cajaIds();
        if ($cajas !== null) {
            $q->whereIn('v.caja_id', $cajas);
        } else {
            $ids = \App\Services\VendedorScope::ids();
            if ($ids !== null) {
                $q->whereIn('v.vendedor_id', $ids);
            } elseif ($r->filled('vendedor_id')) {
                $q->where('v.vendedor_id', $r->vendedor_id);
            }
        }

        if ($r->filled('metodo_pago')) $q->where('v.metodo_pago', $r->metodo_pago);
        if ($r->filled('cliente_id'))  $q->where('v.cliente_id', $r->cliente_id);

        return $this->aplicarEmpresaReporte($q, $r, 'v.caja_id');
    }

    // ── Controllers ────────────────────────────────────────
    public function index(): \Illuminate\View\View
    {
        $vendedores = Vendedor::orderBy('nombre')->get(['id', 'nombre']);
        $clientes   = Cliente::orderBy('nombre')->limit(300)->get(['id', 'nombre']);
        $empresasReportables = $this->empresasReportables();
        $empresaInicial = session()->has('caja_id')
            ? DB::table('cajas')->where('id', session('caja_id'))->value('empresa')
            : ($empresasReportables->count() === 1 ? $empresasReportables->first() : '');

        return view('reportes', compact('vendedores', 'clientes', 'empresasReportables', 'empresaInicial'));
    }

    public function datos(Request $r): JsonResponse
    {
        try {
        $periodo        = $r->input('periodo', 'diario');
        [$desde, $hasta] = $this->resolverRango($r, $periodo);

        // ── Ventas: summary ─────────────────────────────
        $vSum = $this->qVentas($r, $desde, $hasta)
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total + COALESCE(ajuste,0)),0) as total')
            ->first();

        $totalVentas = (float) ($vSum->total    ?? 0);
        $cantVentas  = (int)   ($vSum->cantidad ?? 0);
        $igvVentas   = round($totalVentas * 18 / 118, 2);
        $subtotalV   = round($totalVentas / 1.18, 2);

        // ── Por método de pago ───────────────────────────
        $porMetodo = $this->qVentas($r, $desde, $hasta)
            ->selectRaw("COALESCE(metodo_pago,'No especificado') as metodo, COUNT(*) as count, SUM(total + COALESCE(ajuste,0)) as total")
            ->groupBy('metodo_pago')
            ->orderByDesc('total')
            ->get()
            ->map(fn($m) => ['metodo' => $m->metodo, 'total' => (float)$m->total, 'count' => (int)$m->count]);

        // ── Top clientes ────────────────────────────────
        $topClientes = $this->qVentas($r, $desde, $hasta)
            ->leftJoin('clientes as cl', 'ventas.cliente_id', '=', 'cl.id')
            ->selectRaw("COALESCE(cl.nombre,'Sin cliente') as nombre, COUNT(*) as count, SUM(ventas.total + COALESCE(ventas.ajuste,0)) as total")
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
            ->selectRaw("COALESCE(documento_tipo,'Sin tipo') as tipo, COUNT(*) as count, SUM(total + COALESCE(ajuste,0)) as total")
            ->groupBy('documento_tipo')
            ->get()
            ->map(fn($c) => ['tipo' => $c->tipo, 'count' => (int)$c->count, 'total' => (float)$c->total]);

        // ── Ventas por día (sólo tabla ventas, sin JOIN a detalles para no multiplicar) ─
        $ventasPorDia = $this->qVentas($r, $desde, $hasta)
            ->selectRaw("DATE(ventas.fecha) as fecha, COUNT(*) as count, SUM(ventas.total + COALESCE(ventas.ajuste,0)) as total_ventas")
            ->groupByRaw('DATE(ventas.fecha)')
            ->orderBy('fecha')
            ->get()
            ->keyBy('fecha');

        // Financial KPIs come from historical CVD allocations; current catalog
        // costs are intentionally not used in economic reporting.
        $ventaIdsReporte = $this->qVentas($r, $desde, $hasta)->pluck('ventas.id');
        $finanzas = app(\App\Services\UtilidadVentaService::class)->agregados($ventaIdsReporte);
        $finanzasPorDia = collect($finanzas['por_fecha'])->keyBy('fecha');

        // ── Merge por fecha ──────────────────────────────────────────────────────
        $porDia = $ventasPorDia->map(function ($v) use ($finanzasPorDia) {
            $f = $finanzasPorDia->get($v->fecha, []);
            return [
                'fecha'    => $v->fecha,
                'count'    => (int)   $v->count,
                'total'    => (float) $v->total_ventas,
                'costo'    => (float) ($f['costo_historico'] ?? 0),
                'utilidad' => (float) ($f['utilidad_conocida'] ?? 0),
                'utilidad_economica' => (float) ($f['utilidad_economica'] ?? 0),
                'utilidad_despachada' => (float) ($f['utilidad_despachada'] ?? 0),
                'comision_liberada' => (float) ($f['comision_liberada'] ?? 0),
            ];
        })->values();

        $totalVentasValidas = (float) $finanzas['ingreso_costeado'];
        $totalCosto = (float) $finanzas['costo_costeado'];
        $utilidad = (float) $finanzas['utilidad_economica'];
        $utilidadConocida = (float) $finanzas['utilidad_conocida'];
        $margen = $totalVentasValidas > 0 ? round($utilidad / $totalVentasValidas * 100, 1) : 0;

        // ── Compras ──────────────────────────────────────
        $cmpSum = DB::table('compras')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereNull('deleted_at')
            ->whereNull('reporte_semanal_id')
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(monto_total),0) as total')
            ->first();

        $totalCompras = (float) ($cmpSum->total    ?? 0);
        $cantCompras  = (int)   ($cmpSum->cantidad ?? 0);
        $igvCompras   = round($totalCompras * 18 / 118, 2);

        $porProveedor = DB::table('compras')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereNull('deleted_at')
            ->whereNull('reporte_semanal_id')
            ->selectRaw("COALESCE(empresa,'Sin proveedor') as proveedor, COUNT(*) as count, SUM(monto_total) as total")
            ->groupBy('empresa')
            ->orderByDesc('total')
            ->get()
            ->map(fn($p) => ['proveedor' => $p->proveedor, 'total' => (float)$p->total, 'count' => (int)$p->count]);

        $topProductosCompra = DB::table('compra_lineas as cl')
            ->join('compras as c', 'cl.compra_id', '=', 'c.id')
            ->whereBetween('c.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereNull('c.deleted_at')
            ->whereNull('c.reporte_semanal_id')
            ->selectRaw("COALESCE(cl.producto,'Manual') as nombre, SUM(cl.cantidad) as cantidad, SUM(cl.monto_total) as total")
            ->groupBy('cl.producto')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn($p) => ['nombre' => $p->nombre, 'cantidad' => (float)$p->cantidad, 'total' => (float)$p->total]);

        // Comisión de vendedores sobre Total a Cobrar
        $vendorIdsComision = \App\Services\VendedorScope::ids();
        $cajaIdsComision = \App\Services\VendedorScope::cajaIds();
        $comisionTotal = (float) DB::table('ventas as v')
            ->join('vendedores as vend', 'v.vendedor_id', '=', 'vend.id')
            ->whereBetween('v.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereNotIn('v.estado', ['anulado', 'anulado_nc'])
            ->whereNull('v.deleted_at')
            ->whereNull('v.reporte_semanal_id')
            ->where('v.es_referencia_fiscal', false)
            ->where(fn($q) => $q->whereNull('v.documento_tipo')->orWhere('v.documento_tipo', '!=', 'nota_credito'))
            ->when($cajaIdsComision !== null, fn($q) => $q->whereIn('v.caja_id', $cajaIdsComision))
            ->when($cajaIdsComision === null && $vendorIdsComision !== null, fn($q) => $q->whereIn('v.vendedor_id', $vendorIdsComision))
            ->when($cajaIdsComision === null && $vendorIdsComision === null && $r->filled('vendedor_id'), fn($q) => $q->where('v.vendedor_id', $r->vendedor_id))
            ->selectRaw('COALESCE(SUM((v.total + COALESCE(v.ajuste, 0)) * COALESCE(vend.comision_porcentaje, 0) / 100), 0) as total')
            ->value('total');

        // Fully costed economic profit is distinct from known partial profit,
        // dispatched profit, and commissions released by the ledger.
        $ingresoReal  = (float) $finanzas['ingreso_costeado'];
        $costoReal    = (float) $finanzas['costo_costeado'];
        $utilidadReal = (float) $finanzas['utilidad_economica'];
        $margenReal   = $ingresoReal > 0 ? round($utilidadReal / $ingresoReal * 100, 1) : 0;

        // Released commission is the ledger net; pending is limited to
        // fully costed dispatched units and remains separate from potential.
        $comisionUtilidad = (float) $finanzas['comision_liberada'];
        $comisionPendiente = (float) $finanzas['comision_pendiente'];

        // ── FASE 2: Cobertura de costeo (sobre todas las ventas del período, informativo) ──
        $cajas = \App\Services\VendedorScope::cajaIds();
        $ids   = \App\Services\VendedorScope::ids();
        $cobertura = DB::table('venta_detalles as vd')
            ->join('ventas as v', 'vd.venta_id', '=', 'v.id')
            ->whereBetween('v.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereNotIn('v.estado', ['anulado', 'anulado_nc'])
            ->whereNull('v.deleted_at')
            ->whereNull('v.reporte_semanal_id')
            ->where('v.es_referencia_fiscal', false)
            ->where(fn($q) => $q->whereNull('v.documento_tipo')->orWhere('v.documento_tipo', '!=', 'nota_credito'))
            ->when($cajas !== null, fn($q) => $q->whereIn('v.caja_id', $cajas))
            ->when($cajas === null && $ids !== null, fn($q) => $q->whereIn('v.vendedor_id', $ids))
            ->when($cajas === null && $ids === null && $r->filled('vendedor_id'), fn($q) => $q->where('v.vendedor_id', $r->vendedor_id))
            ->when($r->filled('metodo_pago'), fn($q) => $q->where('v.metodo_pago', $r->metodo_pago))
            ->when($r->filled('cliente_id'),  fn($q) => $q->where('v.cliente_id',  $r->cliente_id))
            ->leftJoin(
                DB::raw('(SELECT venta_detalle_id, SUM(cantidad) as cant_costeada FROM compra_venta_detalle GROUP BY venta_detalle_id) as cvd_sum'),
                'vd.id', '=', 'cvd_sum.venta_detalle_id'
            )
            ->selectRaw("
                COUNT(*) as total_lineas,
                SUM(CASE WHEN COALESCE(cvd_sum.cant_costeada, 0) = 0 THEN 1 ELSE 0 END) as sin_costear,
                SUM(CASE WHEN COALESCE(cvd_sum.cant_costeada, 0) > 0 AND COALESCE(cvd_sum.cant_costeada, 0) < vd.cantidad THEN 1 ELSE 0 END) as parcial,
                SUM(CASE WHEN COALESCE(cvd_sum.cant_costeada, 0) >= vd.cantidad THEN 1 ELSE 0 END) as costeada
            ")
            ->first();

        $totalLineas  = (int) ($cobertura->total_lineas ?? 0);
        $sinCostear   = (int) ($cobertura->sin_costear  ?? 0);
        $parcial      = (int) ($cobertura->parcial      ?? 0);
        $costeada     = (int) ($cobertura->costeada     ?? 0);
        $pctCobertura = $totalLineas > 0 ? round($costeada / $totalLineas * 100, 1) : 0;

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
                'comision_comercial' => round($comisionTotal, 2),
                'comision_utilidad' => round($comisionUtilidad, 2),
                'comision_pendiente' => round($comisionPendiente, 2),
                'comision_despachada_potencial' => (float) $finanzas['comision_despachada_potencial'],
                'utilidad_conocida' => round($utilidadConocida, 2),
                'utilidad_despachada' => (float) $finanzas['utilidad_despachada'],
                'costo_despachado' => (float) $finanzas['costo_despachado'],
                // FASE 4: Utilidad Real
                'utilidad_real'  => $utilidadReal,
                'margen_real'    => $margenReal,
                'ingreso_real'   => round($ingresoReal, 2),
                'costo_real'     => round($costoReal, 2),
                // FASE 2: Cobertura
                'cobertura' => [
                    'total'       => $totalLineas,
                    'sin_costear' => $sinCostear,
                    'parcial'     => $parcial,
                    'costeada'    => $costeada,
                    'pct'         => $pctCobertura,
                ],
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

        // Show economic and known profit separately, including unpaid sales.
        $ventaIds = $this->qVentas($r, $desde, $hasta)->pluck('ventas.id');

        if ($ventaIds->isEmpty()) {
            return response()->json(['detalle' => []]);
        }

        $ventas = DB::table('ventas as v')
            ->leftJoin('clientes as c', 'v.cliente_id', '=', 'c.id')
            ->whereIn('v.id', $ventaIds)
            ->selectRaw("v.id, v.documento_tipo, v.documento_numero, v.fecha, COALESCE(c.nombre,'Sin cliente') as cliente")
            ->orderByDesc('v.fecha')
            ->get()
            ->keyBy('id');

        $resumenes = app(\App\Services\UtilidadVentaService::class)->resumenesPorIds($ventaIds);
        $resultado = collect($resumenes)->map(function ($resumen, $id) use ($ventas) {
            $v = $ventas->get($id);
            if (!$v) {
                return null;
            }
            $lineas = collect($resumen['detalles'])->map(fn ($linea) => [
                'producto' => $linea['producto'],
                'codigo' => $linea['codigo'],
                'cantidad' => $linea['cantidad_neta'],
                'precio_venta' => $linea['precio_unitario'],
                'costo' => $linea['costo_historico'],
                'subtotal' => $linea['subtotal'],
                'ganancia' => $linea['utilidad_conocida'],
                'estado_costeo' => $linea['estado_costeo'],
            ])->values();
            $numero = trim(ucfirst($v->documento_tipo ?? 'Venta') . ' ' . ($v->documento_numero ?? '#' . $v->id));
            return [
                'id'          => $v->id,
                'numero'      => $numero,
                'fecha'       => Carbon::parse($v->fecha)->format('d/m/Y'),
                'cliente'     => $v->cliente,
                'total'       => $resumen['ingreso_neto'],
                'ganancia'    => $resumen['utilidad_economica'],
                'utilidad_conocida' => $resumen['utilidad_conocida'],
                'utilidad_despachada' => $resumen['utilidad_despachada'],
                'cantidad_despachada' => $resumen['cantidad_despachada'],
                'cantidad_despachada_costeada' => $resumen['cantidad_despachada_costeada'],
                'cantidad_pendiente' => array_sum(array_map(
                    fn ($linea) => (float) $linea['cantidad_pendiente'],
                    $resumen['detalles']
                )),
                'estado'      => $resumen['estado_pago'],
                'estado_despacho' => $resumen['estado_despacho'],
                'comision_potencial' => $resumen['comision_potencial'],
                'comision_liberada' => $resumen['comision_liberada'],
                'comision_pendiente' => $resumen['comision_pendiente'],
                'estado_costeo' => $resumen['estado_costeo'],
                'sin_costear' => $resumen['estado_costeo'] !== 'costeada',
                'lineas'      => $lineas,
            ];
        })->filter()->values();

        return response()->json(['detalle' => $resultado]);
    }

    public function ventasDetalle(Request $r): JsonResponse
    {
        $periodo        = $r->input('periodo', 'diario');
        [$desde, $hasta] = $this->resolverRango($r, $periodo);

        $ventas = $this->qVentas($r, $desde, $hasta)
            ->leftJoin('clientes as cl', 'ventas.cliente_id', '=', 'cl.id')
            ->selectRaw("ventas.id, ventas.documento_tipo, ventas.documento_numero, ventas.fecha, ventas.estado,
                         COALESCE(ventas.metodo_pago,'No especificado') as metodo_pago,
                         COALESCE(cl.nombre,'Sin cliente') as cliente,
                         (ventas.total + COALESCE(ventas.ajuste,0)) as total")
            ->orderByDesc('ventas.fecha')
            ->get()
            ->map(function ($v) {
                $numero = trim(ucfirst($v->documento_tipo ?? 'Venta') . ' ' . ($v->documento_numero ?? '#' . $v->id));
                return [
                    'id'          => $v->id,
                    'numero'      => $numero,
                    'fecha'       => Carbon::parse($v->fecha)->format('d/m/Y'),
                    'cliente'     => $v->cliente,
                    'metodo_pago' => self::formatMetodoPago($v->metodo_pago),
                    'estado'      => ucfirst($v->estado),
                    'total'       => round((float) $v->total, 2),
                ];
            });

        return response()->json([
            'detalle' => $ventas,
            'total'   => round((float) $ventas->sum('total'), 2),
            'cantidad'=> $ventas->count(),
        ]);
    }

    public function comprasDetalle(Request $r): JsonResponse
    {
        $periodo        = $r->input('periodo', 'diario');
        [$desde, $hasta] = $this->resolverRango($r, $periodo);

        $cajaIds = \App\Services\VendedorScope::cajaIds();
        $empresaReporte = $this->empresaReporte($r);

        $compras = DB::table('compras')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereNull('deleted_at')
            ->when($cajaIds !== null, fn($q) => $q->whereIn('caja_id', $cajaIds))
            ->whereIn('caja_id', DB::table('cajas')->where('empresa', $empresaReporte)->select('id'))
            ->selectRaw("id, fecha, empresa, documento_tipo, documento_numero,
                         COALESCE(metodo_pago,'No especificado') as metodo_pago, monto_total")
            ->orderByDesc('fecha')
            ->get()
            ->map(fn($c) => [
                'id'          => $c->id,
                'numero'      => trim(($c->documento_tipo ? ucfirst($c->documento_tipo) : 'Compra') . ' ' . ($c->documento_numero ?? '#' . $c->id)),
                'fecha'       => Carbon::parse($c->fecha)->format('d/m/Y'),
                'proveedor'   => $c->empresa,
                'metodo_pago' => ucfirst($c->metodo_pago),
                'total'       => round((float) $c->monto_total, 2),
            ]);

        return response()->json([
            'detalle'  => $compras,
            'total'    => round((float) $compras->sum('total'), 2),
            'cantidad' => $compras->count(),
        ]);
    }

    public function exportExcel(Request $r): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $periodo        = $r->input('periodo', 'diario');
        [$desde, $hasta] = $this->resolverRango($r, $periodo);

        $vendorIds = \App\Services\VendedorScope::ids();
        $cajaIds   = \App\Services\VendedorScope::cajaIds();
        $empresaReporte = $this->empresaReporte($r);

        $ventas = DB::table('ventas as v')
            ->leftJoin('clientes as c', 'v.cliente_id', '=', 'c.id')
            ->leftJoin('vendedores as vend', 'v.vendedor_id', '=', 'vend.id')
            ->whereBetween('v.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereNotIn('v.estado', ['anulado', 'anulado_nc'])
            ->whereNull('v.deleted_at')
            ->whereNull('v.reporte_semanal_id')
            ->where(fn($q) => $q->whereNull('v.documento_tipo')->orWhere('v.documento_tipo', '!=', 'nota_credito'))
            ->whereIn('v.caja_id', DB::table('cajas')->where('empresa', $empresaReporte)->select('id'))
            ->when($cajaIds !== null, fn($q) => $q->whereIn('v.caja_id', $cajaIds))
            ->when($cajaIds === null && $vendorIds !== null, fn($q) => $q->whereIn('v.vendedor_id', $vendorIds))
            ->when($cajaIds === null && $vendorIds === null && $r->filled('vendedor_id'), fn($q) => $q->where('v.vendedor_id', $r->vendedor_id))
            ->when($r->filled('metodo_pago'), fn($q) => $q->where('v.metodo_pago', $r->metodo_pago))
            ->when($r->filled('cliente_id'),  fn($q) => $q->where('v.cliente_id',  $r->cliente_id))
            ->selectRaw("v.id, v.fecha, v.documento_tipo, v.documento_numero,
                         COALESCE(c.nombre,'') as cliente, COALESCE(vend.nombre,'') as vendedor,
                         COALESCE(v.metodo_pago,'') as metodo_pago,
                         v.total + COALESCE(v.ajuste, 0) as total, v.estado")
            ->orderBy('v.fecha')
            ->get();

        $compras = DB::table('compras')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereNull('deleted_at')
            ->whereNull('reporte_semanal_id')
            ->when($cajaIds !== null, fn($q) => $q->whereIn('caja_id', $cajaIds))
            ->whereIn('caja_id', DB::table('cajas')->where('empresa', $empresaReporte)->select('id'))
            ->selectRaw("fecha, empresa, documento_tipo, documento_numero, metodo_pago, monto_total")
            ->orderBy('fecha')
            ->get();

        $totalV  = $ventas->sum('total');
        $finanzasExcel = app(\App\Services\UtilidadVentaService::class)
            ->agregados($ventas->pluck('id'));
        $costoT  = (float) $finanzasExcel['costo_historico'];
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
            ['Costo histórico conocido', round($costoT, 2)],
            ['Utilidad conocida (parcial incluida)', (float) $finanzasExcel['utilidad_conocida']],
            ['Utilidad económica (costeo completo)', (float) $finanzasExcel['utilidad_economica']],
            ['Ingreso de ventas completamente costeadas', (float) $finanzasExcel['ingreso_costeado']],
            ['Margen de utilidad económica %', (float) $finanzasExcel['ingreso_costeado'] > 0
                ? round((float) $finanzasExcel['utilidad_economica'] / (float) $finanzasExcel['ingreso_costeado'] * 100, 1)
                : 0],
            ['Utilidad de productos despachados y costeados', (float) $finanzasExcel['utilidad_despachada']],
            ['Costo de productos despachados', (float) $finanzasExcel['costo_despachado']],
            ['',''],
            ['Comisión liberada (ledger)', (float) $finanzasExcel['comision_liberada']],
            ['Comisión pendiente de unidades despachadas', (float) $finanzasExcel['comision_pendiente']],
            ['Comisión potencial despachada y costeada', (float) $finanzasExcel['comision_despachada_potencial']],
        ], null, 'A2');
        $sr->getColumnDimension('A')->setWidth(22);
        $sr->getColumnDimension('B')->setAutoSize(true);
        $sr->getStyle('B2:B30')->getNumberFormat()->setFormatCode('#,##0.00');

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
        $cajaIds   = \App\Services\VendedorScope::cajaIds();
        $empresaReporte = $this->empresaReporte($r);

        $ventasSummary = DB::table('ventas')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereNotIn('estado', ['anulado', 'anulado_nc'])
            ->whereNull('deleted_at')
            ->whereNull('reporte_semanal_id')
            ->where(fn($q) => $q->whereNull('documento_tipo')->orWhere('documento_tipo', '!=', 'nota_credito'))
            ->when($cajaIds !== null, fn($q) => $q->whereIn('caja_id', $cajaIds))
            ->when($cajaIds === null && $vendorIds !== null, fn($q) => $q->whereIn('vendedor_id', $vendorIds))
            ->when($cajaIds === null && $vendorIds === null && $r->filled('vendedor_id'), fn($q) => $q->where('vendedor_id', $r->vendedor_id))
            ->when($r->filled('metodo_pago'), fn($q) => $q->where('metodo_pago', $r->metodo_pago))
            ->when($r->filled('cliente_id'),  fn($q) => $q->where('cliente_id',  $r->cliente_id))
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total + COALESCE(ajuste, 0)),0) as total')
            ->first();

        $totalVentas = (float) ($ventasSummary->total    ?? 0);
        $cantVentas  = (int)   ($ventasSummary->cantidad ?? 0);

        $comprasSummary = DB::table('compras')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereNull('deleted_at')
            ->whereNull('reporte_semanal_id')
            ->when($cajaIds !== null, fn($q) => $q->whereIn('caja_id', $cajaIds))
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(monto_total),0) as total')
            ->first();

        $totalCompras = (float) ($comprasSummary->total    ?? 0);
        $cantCompras  = (int)   ($comprasSummary->cantidad ?? 0);

        $ventaIdsPdf = DB::table('ventas')
                ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
                ->whereNotIn('estado', ['anulado', 'anulado_nc'])
                ->whereNull('deleted_at')
                ->whereNull('reporte_semanal_id')
                ->where('es_referencia_fiscal', false)
                ->where(fn($q) => $q->whereNull('documento_tipo')->orWhere('documento_tipo', '!=', 'nota_credito'))
                ->whereIn('caja_id', DB::table('cajas')->where('empresa', $empresaReporte)->select('id'))
                ->when($cajaIds !== null, fn($q) => $q->whereIn('caja_id', $cajaIds))
                ->when($cajaIds === null && $vendorIds !== null, fn($q) => $q->whereIn('vendedor_id', $vendorIds))
                ->when($r->filled('metodo_pago'), fn($q) => $q->where('metodo_pago', $r->metodo_pago))
                ->when($r->filled('cliente_id'), fn($q) => $q->where('cliente_id', $r->cliente_id))
                ->pluck('id');
        $finanzasPdf = app(\App\Services\UtilidadVentaService::class)->agregados($ventaIdsPdf);
        $totalCosto = (float) $finanzasPdf['costo_historico'];

        $topClientes = DB::table('ventas as v')
            ->leftJoin('clientes as c', 'v.cliente_id', '=', 'c.id')
            ->whereBetween('v.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereNotIn('v.estado', ['anulado', 'anulado_nc'])
            ->whereNull('v.deleted_at')
            ->whereIn('v.caja_id', DB::table('cajas')->where('empresa', $empresaReporte)->select('id'))
            ->when($cajaIds !== null, fn($q) => $q->whereIn('v.caja_id', $cajaIds))
            ->when($cajaIds === null && $vendorIds !== null, fn($q) => $q->whereIn('v.vendedor_id', $vendorIds))
            ->selectRaw("COALESCE(c.nombre,'Sin cliente') as nombre, COUNT(*) as count, SUM(v.total + COALESCE(v.ajuste,0)) as total")
            ->groupBy('v.cliente_id', 'c.nombre')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $topProductos = DB::table('venta_detalles as vd')
            ->join('ventas as v', 'vd.venta_id', '=', 'v.id')
            ->whereBetween('v.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereNotIn('v.estado', ['anulado', 'anulado_nc'])
            ->whereNull('v.deleted_at')
            ->whereIn('v.caja_id', DB::table('cajas')->where('empresa', $empresaReporte)->select('id'))
            ->when($cajaIds !== null, fn($q) => $q->whereIn('v.caja_id', $cajaIds))
            ->when($cajaIds === null && $vendorIds !== null, fn($q) => $q->whereIn('v.vendedor_id', $vendorIds))
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
        $data['utilidad'] = (float) $finanzasPdf['utilidad_conocida'];
        $data['utilidadEconomica'] = (float) $finanzasPdf['utilidad_economica'];
        $data['utilidadDespachada'] = (float) $finanzasPdf['utilidad_despachada'];
        $data['comisionLiberada'] = (float) $finanzasPdf['comision_liberada'];
        $data['comisionPendiente'] = (float) $finanzasPdf['comision_pendiente'];
        $data['margen'] = (float) $finanzasPdf['ingreso_costeado'] > 0
            ? round((float) $finanzasPdf['utilidad_economica'] / (float) $finanzasPdf['ingreso_costeado'] * 100, 1)
            : 0;
        $data['igvVentas']  = round($totalVentas * 18 / 118, 2);
        $data['igvCompras'] = round($totalCompras * 18 / 118, 2);
        $data['comisionUtilidad'] = round((float) $finanzasPdf['comision_liberada'], 2);

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

    public static function formatMetodoPago(?string $raw): string
    {
        if (!$raw || $raw === 'No especificado') return 'No especificado';
        $mapa = [
            'saldo_favor'  => 'Saldo a favor',
            'efectivo'     => 'Efectivo',
            'transferencia'=> 'Transferencia',
            'tarjeta'      => 'Tarjeta',
            'yape'         => 'Yape',
            'plin'         => 'Plin',
            'credito'      => 'Crédito',
        ];
        return collect(explode(',', $raw))
            ->map(fn($m) => $mapa[trim($m)] ?? ucfirst(trim(str_replace('_', ' ', $m))))
            ->filter()
            ->implode(', ');
    }
}
