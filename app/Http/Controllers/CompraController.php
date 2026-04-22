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
        $query = Compra::with('detalles.venta.vendedor')->orderBy('fecha', 'desc')->orderBy('id', 'desc');

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
        return view('casadets.compras.create', compact('facturas'));
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);
        DB::transaction(function () use ($data, $request) {
            $compra = Compra::create($data);
            $compra->detalles()->sync($request->input('detalles', []));
        });
        return redirect('/casadets/compras')->with('success', 'Compra registrada.');
    }

    public function show(Compra $compra)
    {
        $compra->load(['detalles.venta.vendedor']);
        return view('casadets.compras.show', compact('compra'));
    }

    public function edit(Compra $compra)
    {
        $compra->load('detalles.venta');
        $facturas = $this->facturasDisponibles();
        $detallesSeleccionados = $compra->detalles->pluck('id')->toArray();

        // Asegurar que las facturas con detalles ya seleccionados estén en la lista
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
            $compra->update($data);
            $compra->detalles()->sync($request->input('detalles', []));
        });
        return redirect('/casadets/compras')->with('success', 'Compra actualizada.');
    }

    public function destroy(Compra $compra)
    {
        $compra->delete();
        return redirect('/casadets/compras')->with('success', 'Compra eliminada.');
    }

    /**
     * Endpoint JSON: devuelve productos (detalles) de una venta para el AJAX del formulario.
     */
    public function detallesVenta(Venta $venta)
    {
        $venta->load(['detalles', 'vendedor']);
        return response()->json([
            'venta' => [
                'id' => $venta->id,
                'fecha' => $venta->fecha->format('d/m/Y'),
                'documento' => trim(ucfirst((string) $venta->documento_tipo) . ' ' . (string) $venta->documento_numero),
                'vendedor' => $venta->vendedor->nombre ?? '—',
            ],
            'detalles' => $venta->detalles->map(fn($d) => [
                'id' => $d->id,
                'producto' => $d->producto,
                'cantidad' => (float) $d->cantidad,
                'precio_unitario' => (float) $d->precio_unitario,
                'subtotal' => (float) $d->subtotal,
            ]),
        ]);
    }

    private function facturasDisponibles()
    {
        return Venta::with('vendedor')
            ->where('documento_tipo', 'factura')
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->limit(300)
            ->get();
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'empresa' => 'required|string|max:255',
            'documento_tipo' => 'nullable|string|max:50',
            'documento_numero' => 'nullable|string|max:100',
            'fecha' => 'required|date',
            'producto' => 'nullable|string|max:255',
            'cantidad' => 'required|numeric|min:0',
            'monto_unitario' => 'required|numeric|min:0',
            'monto_total' => 'required|numeric|min:0',
            'observaciones' => 'nullable|string',
            'detalles' => 'nullable|array',
            'detalles.*' => 'integer|exists:venta_detalles,id',
        ]);
    }
}
