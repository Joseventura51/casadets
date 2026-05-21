<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompraController extends Controller
{
    public function index(Request $request)
    {
        $query = Compra::with(['detalles.venta.vendedor', 'lineas'])->orderBy('fecha', 'desc')->orderBy('id', 'desc');

        if ($request->filled('empresa')) {
            $query->where('empresa', 'like', '%' . $request->empresa . '%');
        }
        if ($request->filled('desde')) {
            $query->whereDate('fecha', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $query->whereDate('fecha', '<=', $request->hasta);
        }

        $compras = $query->get();
        return view('casadets.compras.index', compact('compras'));
    }

    public function create()
    {
        $facturas = $this->facturasDisponibles();
        $compra = new Compra();
        $detallesSeleccionados = [];
        return view('casadets.compras.create', compact('facturas', 'compra', 'detallesSeleccionados'));
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);
        DB::transaction(function () use ($data, $request) {
            $lineas  = $data['lineas'] ?? [];
            $total   = collect($lineas)->sum('monto_total');
            $compra  = Compra::create(array_merge(
                collect($data)->except('lineas')->toArray(),
                ['monto_total' => $total]
            ));
            $lineasCreadas = [];
            foreach ($lineas as $idx => $l) {
                $linea = $compra->lineas()->create($l);
                $lineasCreadas[(int) $idx] = $linea->id;
            }
            $compra->detalles()->sync($this->buildDetallesSync($request, $lineasCreadas));
        });
        return redirect('/casadets/compras')->with('success', 'Compra registrada.');
    }

    public function show(Compra $compra)
    {
        $compra->load(['lineas', 'detalles.venta.vendedor']);
        return view('casadets.compras.show', compact('compra'));
    }

    public function edit(Compra $compra)
    {
        $compra->load(['lineas', 'detalles.venta']);
        $facturas = $this->facturasDisponibles();
        $detallesSeleccionados = $compra->detalles->pluck('id')->toArray();

        $ventasYa = $compra->detalles->pluck('venta')->filter()->unique('id');
        foreach ($ventasYa as $v) {
            if (!$facturas->contains('id', $v->id)) {
                $facturas->push($v);
            }
        }

        return view('casadets.compras.edit', compact('compra', 'facturas', 'detallesSeleccionados'));
    }

    public function update(Request $request, Compra $compra)
    {
        $data = $this->validar($request);
        DB::transaction(function () use ($data, $request, $compra) {
            $lineas = $data['lineas'] ?? [];
            $total  = collect($lineas)->sum('monto_total');
            $compra->update(array_merge(
                collect($data)->except('lineas')->toArray(),
                ['monto_total' => $total]
            ));
            $compra->lineas()->delete();
            $lineasCreadas = [];
            foreach ($lineas as $idx => $l) {
                $linea = $compra->lineas()->create($l);
                $lineasCreadas[(int) $idx] = $linea->id;
            }
            $compra->detalles()->sync($this->buildDetallesSync($request, $lineasCreadas));
        });
        return redirect('/casadets/compras/' . $compra->id)->with('success', 'Compra actualizada.');
    }

    public function destroy(Compra $compra)
    {
        $compra->delete();
        return redirect('/casadets/compras')->with('success', 'Compra eliminada.');
    }

    public function detallesVenta(Venta $venta)
    {
        $venta->load(['detalles', 'vendedor']);
        return response()->json([
            'venta' => [
                'id'        => $venta->id,
                'fecha'     => $venta->fecha->format('d/m/Y'),
                'documento' => trim(ucfirst((string) $venta->documento_tipo) . ' ' . (string) $venta->documento_numero),
                'vendedor'  => $venta->vendedor->nombre ?? '—',
            ],
            'detalles' => $venta->detalles->map(fn($d) => [
                'id'              => $d->id,
                'producto'        => $d->producto,
                'cantidad'        => (float) $d->cantidad,
                'precio_unitario' => (float) $d->precio_unitario,
                'subtotal'        => (float) $d->subtotal,
            ]),
        ]);
    }

    /* ── Helpers ─────────────────────────────────────────────── */

    private function buildDetallesSync(Request $request, array $lineasCreadas = []): array
    {
        $ids           = $request->input('detalles', []);
        $cantidades    = $request->input('detalles_cantidad', []);
        $detallesLinea = $request->input('detalles_linea', []);
        $sync = [];
        foreach ($ids as $id) {
            $lineaIdx = $detallesLinea[$id] ?? null;
            $lineaId  = ($lineaIdx !== null && $lineaIdx !== '' && isset($lineasCreadas[(int) $lineaIdx]))
                ? $lineasCreadas[(int) $lineaIdx]
                : null;
            $sync[(int) $id] = [
                'cantidad'        => (float) ($cantidades[$id] ?? 1),
                'compra_linea_id' => $lineaId,
            ];
        }
        return $sync;
    }

    private function facturasDisponibles()
    {
        return Venta::with(['vendedor', 'detalles'])
            ->whereIn('documento_tipo', ['factura', 'boleta', 'proforma'])
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->limit(300)
            ->get();
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'empresa'                    => 'required|string|max:255',
            'documento_tipo'             => 'nullable|string|max:50',
            'documento_numero'           => 'nullable|string|max:100',
            'fecha'                      => 'required|date',
            'observaciones'              => 'nullable|string',
            'lineas'                     => 'required|array|min:1',
            'lineas.*.producto'          => 'nullable|string|max:255',
            'lineas.*.cantidad'          => 'required|numeric|min:0',
            'lineas.*.monto_unitario'    => 'required|numeric|min:0',
            'lineas.*.monto_total'       => 'required|numeric|min:0',
        ]);
    }
}
