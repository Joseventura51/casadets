<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use App\Models\Producto;
use App\Models\SalidaAlmacen;
use App\Services\InventarioAlmacenService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class SalidaAlmacenController extends Controller
{
    public function __construct(private InventarioAlmacenService $inventario)
    {
    }

    public function index(Request $request)
    {
        $salidas = SalidaAlmacen::with(['origen', 'destino', 'usuario'])
            ->withCount('detalles')
            ->when($request->filled('documento'), fn ($q) =>
                $q->where('documento', 'like', '%' . trim($request->documento) . '%'))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha', '>=', $request->desde))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha', '<=', $request->hasta))
            ->when($request->filled('origen_id'), fn ($q) => $q->where('almacen_origen_id', $request->origen_id))
            ->when($request->filled('destino_id'), fn ($q) => $q->where('almacen_destino_id', $request->destino_id))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $almacenes = Almacen::where('activo', true)->orderBy('nombre')->get();

        return view('casadets.salidas_almacen.index', compact('salidas', 'almacenes'));
    }

    public function create()
    {
        return view('casadets.salidas_almacen.create', [
            'almacenes' => Almacen::where('activo', true)->orderBy('nombre')->get(),
            'productos' => Producto::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'documento' => ['required', 'string', 'max:100', Rule::unique('salidas_almacen', 'documento')],
            'fecha' => ['required', 'date'],
            'almacen_origen_id' => ['required', 'integer', 'exists:almacenes,id'],
            'almacen_destino_id' => ['required', 'integer', 'exists:almacenes,id', 'different:almacen_origen_id'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'productos' => ['required', 'array', 'min:1'],
            'productos.*.producto_id' => ['required', 'integer', 'distinct', 'exists:productos,id'],
            'productos.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'productos.*.precio_unitario' => ['nullable', 'numeric', 'min:0'],
            'productos.*.lote' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $salida = \DB::transaction(function () use ($data) {
                $salida = SalidaAlmacen::create([
                    'documento' => trim($data['documento']),
                    'fecha' => $data['fecha'],
                    'almacen_origen_id' => $data['almacen_origen_id'],
                    'almacen_destino_id' => $data['almacen_destino_id'],
                    'estado' => SalidaAlmacen::REALIZADA,
                    'observaciones' => $data['observaciones'] ?? null,
                    'usuario_id' => auth()->id(),
                ]);

                $origen = Almacen::findOrFail($data['almacen_origen_id']);
                foreach ($data['productos'] as $linea) {
                    $producto = Producto::findOrFail($linea['producto_id']);
                    $precio = (float) ($linea['precio_unitario'] ?? $producto->precio_costo ?? 0);
                    $salida->detalles()->create([
                        'producto_id' => $producto->id,
                        'codigo_producto' => $producto->codigo,
                        'descripcion_original' => $producto->nombre,
                        'unidad_medida' => 'NIU',
                        'cantidad' => $linea['cantidad'],
                        'precio_unitario' => $precio,
                        'monto' => round((float) $linea['cantidad'] * $precio, 2),
                        'lote' => $linea['lote'] ?? null,
                    ]);
                    $this->inventario->mover(
                        $producto, $origen, 'salida', (float) $linea['cantidad'],
                        'salida_almacen', $salida->id,
                        'Salida entre almacenes ' . $salida->documento, $precio
                    );
                }

                return $salida;
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect("/casadets/salidas-almacen/{$salida->id}")
            ->with('success', 'Salida de almacén registrada y descontada del origen.');
    }

    public function show(SalidaAlmacen $salida)
    {
        $salida->load([
            'origen', 'destino', 'usuario',
            'detalles.producto',
            'devoluciones.detalles',
            'devoluciones.usuario',
        ]);

        $movimientos = \App\Models\StockMovimiento::whereIn('referencia_tipo', ['salida_almacen', 'devolucion_salida', 'anulacion_salida'])
            ->where(function ($query) use ($salida) {
                $query->where('referencia_id', $salida->id)
                    ->orWhereIn('referencia_id', $salida->devoluciones->pluck('id'));
            })
            ->with(['producto', 'almacen'])
            ->orderBy('id')
            ->get();

        return view('casadets.salidas_almacen.show', compact('salida', 'movimientos'));
    }

    public function anular(SalidaAlmacen $salida)
    {
        try {
            $this->inventario->anularSalida($salida);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Salida anulada. El stock del almacén origen fue restaurado.');
    }

    public function devolucionForm(SalidaAlmacen $salida)
    {
        $salida->load(['origen', 'destino', 'detalles.producto']);
        if ($salida->estado === SalidaAlmacen::ANULADA) {
            return back()->with('error', 'No se puede devolver una salida anulada.');
        }

        $devueltos = \App\Models\DevolucionSalidaDetalle::whereHas(
            'devolucion',
            fn ($q) => $q->where('salida_almacen_id', $salida->id)->where('estado', 'registrada')
        )->selectRaw('salida_almacen_detalle_id, SUM(cantidad_devuelta) AS total')
            ->groupBy('salida_almacen_detalle_id')
            ->pluck('total', 'salida_almacen_detalle_id');

        return view('casadets.salidas_almacen.devolucion', compact('salida', 'devueltos'));
    }

    public function devolucion(Request $request, SalidaAlmacen $salida)
    {
        $data = $request->validate([
            'documento' => ['required', 'string', 'max:100'],
            'fecha' => ['required', 'date'],
            'motivo' => ['nullable', 'string', 'max:500'],
            'cantidades' => ['required', 'array'],
            'cantidades.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $this->inventario->registrarDevolucion($salida, $data);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect("/casadets/salidas-almacen/{$salida->id}")
            ->with('success', 'Devolución registrada y stock restaurado en el almacén origen.');
    }
}