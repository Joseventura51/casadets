<?php

namespace App\Http\Controllers;

use App\Models\Despacho;
use App\Models\Serie;
use App\Models\Venta;
use App\Services\DespachoService;
use App\Services\UtilidadVentaService;
use App\Services\VendedorScope;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DespachoController extends Controller
{
    public function index(Request $request, DespachoService $despachoService, UtilidadVentaService $utilidadService)
    {
        $query = Venta::query()
            ->with(['cliente:id,nombre,documento', 'vendedor:id,nombre'])
            ->whereNotIn('estado', ['anulado', 'anulado_nc'])
            ->where('documento_tipo', '!=', 'nota_credito')
            ->where('es_referencia_fiscal', false);

        VendedorScope::aplicar($query);

        if (session('caja_id') && VendedorScope::modo() !== 'vendedor') {
            $cajaId = (int) session('caja_id');
            $series = Serie::where('caja_id', $cajaId)->pluck('codigo');
            $query->where(function ($q) use ($cajaId, $series) {
                $q->where('caja_id', $cajaId);
                foreach ($series as $codigo) {
                    $q->orWhere('documento_numero', 'like', $codigo . '-%');
                }
            });
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(function ($q) use ($term) {
                $q->where('documento_numero', 'like', '%' . $term . '%')
                    ->orWhereHas('cliente', fn ($c) => $c
                        ->where('nombre', 'like', '%' . $term . '%')
                        ->orWhere('documento', 'like', '%' . $term . '%'))
                    ->orWhereHas('vendedor', fn ($v) => $v->where('nombre', 'like', '%' . $term . '%'));
            });
        }
        if ($request->filled('desde')) {
            $query->whereDate('fecha', '>=', $request->input('desde'));
        }
        if ($request->filled('hasta')) {
            $query->whereDate('fecha', '<=', $request->input('hasta'));
        }
        if ($request->filled('vendedor')) {
            $query->where('vendedor_id', $request->integer('vendedor'));
        }

        $pendienteSql = $this->sqlCantidadPendiente();
        $despachadoSql = $this->sqlCantidadDespachada();
        $estado = $request->input('estado');
        if ($estado === 'pendiente') {
            $query->whereRaw("({$pendienteSql}) > 0")->whereRaw("({$despachadoSql}) = 0");
        } elseif ($estado === 'parcial') {
            $query->whereRaw("({$pendienteSql}) > 0")->whereRaw("({$despachadoSql}) > 0");
        } elseif ($estado === 'completo') {
            $query->whereRaw("({$pendienteSql}) <= 0");
        } else {
            // The main list focuses on sales that still have items to deliver.
            $query->whereRaw("({$pendienteSql}) > 0");
        }

        $ventas = $query->orderByDesc('fecha')->orderByDesc('id')->paginate(30)->withQueryString();
        $resumenes = $utilidadService->resumenes($ventas->getCollection());
        $ventas->getCollection()->transform(function (Venta $venta) use ($resumenes) {
            $venta->setAttribute('estado_despacho_calculado', $resumenes[$venta->id]['estado_despacho'] ?? 'pendiente');
            $venta->setAttribute('cantidad_pendiente_despacho', $resumenes[$venta->id]['detalles'] ?? []);
            return $venta;
        });

        return view('casadets.despachos.index', compact('ventas', 'estado'));
    }

    public function show(Venta $venta, DespachoService $despachoService, UtilidadVentaService $utilidadService)
    {
        $this->authorizeVenta($venta);
        $venta->load(['cliente', 'vendedor', 'caja', 'detalles']);
        $lineas = $despachoService->detalleVenta($venta);
        $historial = $venta->despachos()
            ->with(['detalles.ventaDetalle', 'detalles.costos', 'user'])
            ->orderByDesc('fecha_hora')
            ->get();
        $resumen = $utilidadService->resumen($venta);
        $idempotencyKey = (string) Str::uuid();

        return view('casadets.despachos.show', compact(
            'venta',
            'lineas',
            'historial',
            'resumen',
            'idempotencyKey'
        ));
    }

    public function store(Request $request, Venta $venta, DespachoService $despachoService)
    {
        $this->authorizeVenta($venta);
        $data = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'cantidades' => ['required', 'array'],
            'cantidades.*' => ['nullable', 'numeric', 'min:0'],
            'costos' => ['nullable', 'array'],
            'costos.*' => ['array'],
            'costos.*.*' => ['nullable', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ]);

        $despacho = $despachoService->registrar(
            $venta,
            $data['cantidades'],
            $data['costos'] ?? [],
            $data['idempotency_key'],
            $data['observaciones'] ?? null
        );

        return redirect('/casadets/despachos/historial/' . $despacho->id)
            ->with('success', 'El despacho se registró correctamente.');
    }

    public function historial(Despacho $despacho)
    {
        $despacho->load(['venta.cliente', 'venta.vendedor', 'detalles.ventaDetalle', 'detalles.costos', 'user']);
        $this->authorizeVenta($despacho->venta);
        return view('casadets.despachos.historial', compact('despacho'));
    }

    private function authorizeVenta(Venta $venta): void
    {
        $query = Venta::query()->whereKey($venta->getKey());
        VendedorScope::aplicar($query);
        if (!$query->exists()) {
            abort(403, 'No tienes permiso para acceder a esta venta.');
        }
    }

    private function sqlCantidadDespachada(): string
    {
        return '(SELECT COALESCE(SUM(dd.cantidad), 0)
            FROM despacho_detalles dd
            INNER JOIN despachos d ON d.id = dd.despacho_id
            WHERE d.venta_id = ventas.id)';
    }

    private function sqlCantidadPendiente(): string
    {
        return '(SELECT COALESCE(SUM(CASE WHEN (vd.cantidad
                    - COALESCE((SELECT SUM(dvd.cantidad_devuelta)
                        FROM devolucion_detalles dvd
                        INNER JOIN devoluciones dev ON dev.id = dvd.devolucion_id
                        WHERE dvd.venta_detalle_id = vd.id), 0)
                    - COALESCE((SELECT SUM(dd.cantidad)
                        FROM despacho_detalles dd
                        INNER JOIN despachos d ON d.id = dd.despacho_id
                        WHERE dd.venta_detalle_id = vd.id), 0)) > 0
                THEN (vd.cantidad
                    - COALESCE((SELECT SUM(dvd.cantidad_devuelta)
                        FROM devolucion_detalles dvd
                        INNER JOIN devoluciones dev ON dev.id = dvd.devolucion_id
                        WHERE dvd.venta_detalle_id = vd.id), 0)
                    - COALESCE((SELECT SUM(dd.cantidad)
                        FROM despacho_detalles dd
                        INNER JOIN despachos d ON d.id = dd.despacho_id
                        WHERE dd.venta_detalle_id = vd.id), 0))
                ELSE 0 END), 0)
            FROM venta_detalles vd
            WHERE vd.venta_id = ventas.id)';
    }
}
