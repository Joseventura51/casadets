<?php

namespace App\Http\Controllers;

use App\Models\ReporteSemanal;
use App\Services\ComisionUtilidad;
use App\Services\VentaCosteo;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
                'total_ventas' => round($totalVentas, 2),
                'cantidad_ventas' => $cantVentas,
                'total_compras' => round($totalCompras, 2),
                'cantidad_compras' => $cantCompras,
                'total_costo' => round($totalCosto, 2),
                'utilidad' => $utilidad,
                'margen' => $margen,
                'comision_utilidad' => round($comisionUtilidad, 2),
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

        return response()->json([
            'reportes' => $reportes,
            'inicio_sugerido' => $inicioSugerido?->format('Y-m-d'),
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
            'periodo_inicio' => $inicio->format('d/m/Y'),
            'periodo_fin'    => $fin->format('d/m/Y'),
            'totales'        => $resultado['totales'],
        ]);
    }

    public function cerrar(Request $r): JsonResponse
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
                'ventas_pendientes' => $resultado['totales']['ventas_pendientes'],
                'cerrado_por_id'    => $r->user()?->id,
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
