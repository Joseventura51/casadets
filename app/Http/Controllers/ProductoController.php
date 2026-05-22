<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\StockMovimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductoController extends Controller
{
    public function index(Request $request)
    {
        $query = Producto::query();

        if ($request->filled('q')) {
            $q = '%' . $request->q . '%';
            $query->where(function ($sub) use ($q) {
                $sub->where('nombre', 'like', $q)
                    ->orWhere('codigo', 'like', $q);
            });
        }
        if ($request->filled('empresa')) {
            $query->where('empresa', $request->empresa);
        }
        if ($request->input('activo') !== null && $request->input('activo') !== '') {
            $query->where('activo', (bool) $request->activo);
        }
        if ($request->input('stock') === 'bajo') {
            $query->where('stock_actual', '<=', 0);
        }

        $productos = $query->orderBy('nombre')->paginate(50)->withQueryString();

        $stockBajoCount = Producto::where('activo', true)->where('stock_actual', '<=', 0)->count();
        $totalActivos   = Producto::where('activo', true)->count();

        return view('casadets.productos.index', compact('productos', 'stockBajoCount', 'totalActivos'));
    }

    public function create()
    {
        return view('casadets.productos.create', ['producto' => new Producto()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'       => 'required|string|max:255',
            'codigo'       => 'nullable|string|max:100',
            'empresa'      => 'required|in:casadets,zendy',
            'precio_venta' => 'required|numeric|min:0',
            'precio_costo' => 'nullable|numeric|min:0',
        ]);

        // Verificar duplicado por nombre (case-insensitive)
        $existe = Producto::whereRaw('LOWER(nombre) = ?', [strtolower($data['nombre'])])->exists();
        if ($existe) {
            return back()->withErrors(['nombre' => 'Ya existe un producto con ese nombre.'])->withInput();
        }

        $producto = Producto::create(array_merge($data, [
            'precio_costo' => $data['precio_costo'] ?? 0,
            'stock_actual' => 0,
            'activo'       => true,
        ]));

        return redirect('/casadets/productos/' . $producto->id)
            ->with('success', 'Producto creado. Usa ajuste de stock para ingresar el stock inicial.');
    }

    public function show(Producto $producto)
    {
        // Kardex ordenado cronológicamente para calcular saldo acumulado
        $kardexRaw = $producto->stockMovimientos()
            ->orderBy('fecha', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $saldo = 0.0;
        $kardex = $kardexRaw->map(function ($k) use (&$saldo) {
            $delta = $k->tipo === 'entrada' ? (float) $k->cantidad : -(float) $k->cantidad;
            $saldo = round($saldo + $delta, 2);
            $k->saldo_acumulado = $saldo;
            return $k;
        })->reverse()->values(); // Mostrar más reciente primero

        // Últimas ventas con este producto
        $ultimasVentas = $producto->detallesVenta()
            ->with('venta:id,fecha,estado,documento_tipo,documento_numero,total')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        // Últimas compras con este producto
        $ultimasCompras = $producto->lineasCompra()
            ->with('compra:id,empresa,fecha,documento_tipo,documento_numero,monto_total')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return view('casadets.productos.show', compact(
            'producto', 'kardex', 'ultimasVentas', 'ultimasCompras'
        ));
    }

    public function edit(Producto $producto)
    {
        return view('casadets.productos.edit', compact('producto'));
    }

    public function update(Request $request, Producto $producto)
    {
        $data = $request->validate([
            'nombre'       => 'required|string|max:255',
            'codigo'       => 'nullable|string|max:100',
            'empresa'      => 'required|in:casadets,zendy',
            'precio_venta' => 'required|numeric|min:0',
            'precio_costo' => 'nullable|numeric|min:0',
            'activo'       => 'boolean',
        ]);

        // Verificar nombre único (excluyendo este producto)
        $existe = Producto::whereRaw('LOWER(nombre) = ?', [strtolower($data['nombre'])])
            ->where('id', '!=', $producto->id)
            ->exists();
        if ($existe) {
            return back()->withErrors(['nombre' => 'Ya existe otro producto con ese nombre.'])->withInput();
        }

        $producto->update([
            'nombre'       => $data['nombre'],
            'codigo'       => $data['codigo'] ?? null,
            'empresa'      => $data['empresa'],
            'precio_venta' => $data['precio_venta'],
            'precio_costo' => $data['precio_costo'] ?? 0,
            'activo'       => $request->boolean('activo'),
        ]);

        return redirect('/casadets/productos/' . $producto->id)
            ->with('success', 'Producto actualizado.');
    }

    /**
     * Ajuste manual de stock (entrada o salida por corrección).
     * Genera StockMovimiento tipo='ajuste' y recalcula stock_actual.
     */
    public function storeAjuste(Request $request, Producto $producto)
    {
        $data = $request->validate([
            'tipo'         => 'required|in:entrada,salida,ajuste_absoluto',
            'cantidad'     => 'required|numeric|min:0.01',
            'observaciones'=> 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($data, $producto) {
            if ($data['tipo'] === 'ajuste_absoluto') {
                // Ajuste a valor exacto: crear entrada/salida de diferencia
                $diferencia = round((float) $data['cantidad'] - (float) $producto->stock_actual, 2);
                if (abs($diferencia) < 0.01) return;
                $tipo     = $diferencia > 0 ? 'entrada' : 'salida';
                $cantidad = abs($diferencia);
            } else {
                $tipo     = $data['tipo'];
                $cantidad = (float) $data['cantidad'];
            }

            StockMovimiento::create([
                'producto_id'     => $producto->id,
                'tipo'            => $tipo,
                'cantidad'        => $cantidad,
                'precio_unitario' => $producto->precio_costo ?? 0,
                'referencia_tipo' => 'ajuste',
                'referencia_id'   => null,
                'fecha'           => now()->toDateString(),
                'observaciones'   => $data['observaciones'] ?? 'Ajuste manual',
            ]);

            $producto->recalcularStock();
        });

        return redirect('/casadets/productos/' . $producto->id)
            ->with('success', 'Ajuste de stock aplicado. Stock recalculado desde kardex.');
    }
}
