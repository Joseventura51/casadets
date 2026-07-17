<?php

namespace App\Http\Controllers;

use App\Models\DetallePagoFactura;
use App\Models\Movimiento;
use App\Models\Producto;
use App\Models\Serie;
use App\Models\StockMovimiento;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\Vendedor;
use App\Services\CajaService;
use App\Services\CobranzaService;
use App\Services\VendedorScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class VentaController extends Controller
{
    /* ─── Listado ──────────────────────────────────────────────── */

    public function index(Request $request)
    {
        $desde = $request->input('desde', today()->toDateString());
        $hasta = $request->input('hasta', $desde);
        if ($hasta < $desde) $hasta = $desde;

        $estado    = $request->input('estado');
        $fecha     = $request->input('fecha');
        $vendedor  = $request->input('vendedor');
        $cliente   = $request->input('cliente');
        $pago      = $request->input('pago');
        $documento = $request->input('documento');
        $serie     = $request->input('serie');
        $total     = $request->input('total');

        $query = Venta::with([
                'vendedor:id,nombre',
                'cliente:id,nombre,documento',
                'detalles:id,venta_id,producto_id,producto,cantidad,precio_unitario,subtotal',
            ])
            ->select('id', 'vendedor_id', 'cliente_id', 'fecha', 'estado',
                     'total', 'metodo_pago',
                     'documento_tipo', 'documento_numero', 'observaciones');

        VendedorScope::aplicar($query);

        // Filtrar ventas según la caja seleccionada:
        // Si la caja tiene series asignadas, solo mostrar documentos que empiecen
        // con alguna de esas series (F006-xxx, B006-xxx, P006-xxx…).
        // Si no hay series asignadas, caer en filtro por caja_id.
        $seriesDisponibles = collect();
        if (session('caja_id')) {
            $cajaId = session('caja_id');
            $seriesCodigos = Serie::where('caja_id', $cajaId)->pluck('codigo');
            $seriesDisponibles = Serie::where('caja_id', $cajaId)->orderBy('codigo')->get();

            if ($seriesCodigos->isNotEmpty()) {
                // Mostrar ventas cuyo documento_numero pertenece a esta caja.
                // Las NC aparecen automáticamente si su serie (ej. B006) está asignada.
                $query->where(function ($q) use ($cajaId, $seriesCodigos) {
                    foreach ($seriesCodigos as $cod) {
                        $q->orWhere('documento_numero', 'like', $cod . '-%');
                    }
                    // Ventas sin número de documento propias de esta caja
                    $q->orWhere(fn ($q2) => $q2->where('caja_id', $cajaId)->whereNull('documento_numero'));
                });
            } else {
                // Sin series configuradas: usar caja_id directamente
                $query->where('caja_id', $cajaId);
            }
        }

        if (!$request->boolean('todas')) {
            $query->whereDate('fecha', '>=', $desde)
                  ->whereDate('fecha', '<=', $hasta);
        }

        if ($estado) {
            $query->where('estado', $estado);
        }

        if ($fecha) {
            $query->whereDate('fecha', $fecha);
        }

        if ($vendedor) {
            $query->whereHas('vendedor', fn ($q) => $q->where('nombre', 'like', '%' . $vendedor . '%'));
        }

        if ($cliente) {
            $query->whereHas('cliente', fn ($q) => $q->where(function ($q) use ($cliente) {
                $q->where('nombre', 'like', '%' . $cliente . '%')
                  ->orWhere('documento', 'like', '%' . $cliente . '%');
            }));
        }

        if ($pago) {
            $query->where('metodo_pago', 'like', '%' . $pago . '%');
        }

        if ($documento) {
            $query->where(function ($q) use ($documento) {
                $q->where('documento_tipo', 'like', '%' . $documento . '%')
                  ->orWhere('documento_numero', 'like', '%' . $documento . '%');
            });
        }

        if ($serie) {
            $query->where('documento_numero', 'like', $serie . '-%');
        }

        if ($total !== null && $total !== '') {
            $query->whereRaw('ROUND(total, 2) = ?', [(float) $total]);
        }

        $ventas = $query
            ->orderByRaw("CASE WHEN documento_tipo='factura'  THEN 0
                               WHEN documento_tipo='boleta'   THEN 1
                               WHEN documento_tipo='proforma' THEN 2
                               ELSE 3 END")
            ->orderByRaw("LENGTH(COALESCE(documento_numero,''))")
            ->orderBy('documento_numero')
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(9999)
            ->withQueryString();

        $vendedores = Vendedor::select('id', 'nombre')
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $todas = $request->boolean('todas');

        $cajaAbierta = CajaService::cajaAbierta();

        return view('casadets.ventas.index', compact(
            'ventas', 'vendedores', 'desde', 'hasta', 'todas',
            'estado', 'fecha', 'vendedor', 'cliente', 'pago', 'documento', 'serie',
            'total', 'seriesDisponibles', 'cajaAbierta'
        ));
    }

    /* ─── Autorización de vendedor ─────────────────────────────── */

    private function authorizeVenta(Venta $venta): void
    {
        $cajas = VendedorScope::cajaIds();
        if ($cajas !== null && !in_array($venta->caja_id, $cajas)) {
            abort(403, 'No tienes permiso para acceder a esta venta.');
        }
        $ids = VendedorScope::ids();
        if ($ids !== null && !in_array($venta->vendedor_id, $ids)) {
            abort(403, 'No tienes permiso para acceder a esta venta.');
        }
    }

    /* ─── Detalle ──────────────────────────────────────────────── */

    public function show(Venta $venta)
    {
        $this->authorizeVenta($venta);
        $venta->load([
            'vendedor', 'cliente',
            'detalles.compras.lineas', 'detalles.producto',
            'devoluciones',
            'pagosAplicados.archivos',
            'nubefactComprobante',
        ]);
        return view('casadets.ventas.show', compact('venta'));
    }

    /* ─── Crear ─────────────────────────────────────────────────── */

    public function create()
    {
        $vendedores = Vendedor::where('activo', true)->orderBy('nombre')->get();
        if ($vendedores->isEmpty()) {
            return redirect('/casadets/vendedores/create')
                ->with('error', 'Primero registra al menos un vendedor.');
        }
        $clientes = \App\Models\Cliente::where('activo', true)->orderBy('nombre')->get();

        // Series activas de la caja actual (sesión o primera caja activa)
        $cajaId = session('caja_id')
            ?? DB::table('cajas')->where('activa', true)->value('id');

        $series = Serie::where('caja_id', $cajaId)
            ->where('activa', true)
            ->get()
            ->keyBy('tipo_documento');

        return view('casadets.ventas.create', compact('vendedores', 'clientes', 'series'));
    }

    public function store(Request $request)
    {
        abort_if(!auth()->user()?->puedeHacer('ventas.crear'), 403, 'No tienes permiso para crear ventas.');

        // Validación de vendedor/caja: si filtra por caja, la caja debe ser válida
        $cajas = VendedorScope::cajaIds();
        if ($cajas !== null) {
            // Validación opcional: si vendedor_id no está en sus vendedores, no importa
            // (pero si filtra por caja, el vendedor no es restrictivo)
        } else {
            $ids = VendedorScope::ids();
            if ($ids !== null && !in_array((int) $request->input('vendedor_id'), $ids)) {
                abort(403, 'No puedes crear ventas para este vendedor.');
            }
        }

        $data = $request->validate([
            'vendedor_id'          => 'required|exists:vendedores,id',
            'cliente_id'           => 'nullable|exists:clientes,id',
            'metodo_pago'          => 'nullable|string|max:100',
            'documento_tipo'       => 'nullable|in:boleta,factura,proforma,nota_credito',
            'documento_numero'     => ['nullable', 'string', 'max:255',
                Rule::unique('ventas')->where(fn ($q) => $q->where('documento_tipo', $request->documento_tipo))],
            'observaciones'        => 'nullable|string',
            'fecha'                => 'required|date',
            'es_referencia_fiscal' => 'boolean',
            'igv_incluido'         => 'boolean',
            'igv_porcentaje'       => 'nullable|numeric|min:0|max:100',
            'total_cobrar'         => 'nullable|numeric|min:0',
            'productos'            => 'required|array|min:1',
            'productos.*.producto'        => 'required|string|max:255',
            'productos.*.cantidad'        => 'required|numeric|min:0.01',
            'productos.*.precio_unitario' => 'required|numeric|min:0',
            'productos.*.unidad_medida'   => 'nullable|string|max:10',
        ], ['documento_numero.unique' => 'Ya existe otra venta con ese número de documento del mismo tipo.']);

        $venta = null;

        DB::transaction(function () use ($data, &$venta) {
            // Caja activa: sesión o primera caja activa disponible
            $cajaId = session('caja_id')
                ?? DB::table('cajas')->where('activa', true)->value('id');

            // Total Original exacto con bcmath (inmutable, suma de productos)
            $total = (float) collect($data['productos'])->reduce(
                fn ($carry, $p) => bcadd($carry, bcmul((string) $p['cantidad'], (string) $p['precio_unitario'], 4), 2),
                '0'
            );

            // Ajuste manual de cobro: el usuario puede indicar un Total a Cobrar diferente al Original
            $totalCobrar = (isset($data['total_cobrar']) && $data['total_cobrar'] !== null)
                ? max(0.0, round((float) $data['total_cobrar'], 2))
                : $total;
            $ajuste = round($totalCobrar - $total, 2);

            // Auto-generar número de documento desde la serie de la caja activa
            $docNumero = $data['documento_numero'] ?? null;
            if (!empty($data['documento_tipo']) && $cajaId) {
                $serie = Serie::where('caja_id', $cajaId)
                    ->where('tipo_documento', $data['documento_tipo'])
                    ->where('activa', true)
                    ->first();
                if ($serie) {
                    $docNumero = $serie->generarNumero();
                }
            }

            $venta = Venta::create([
                'vendedor_id'          => $data['vendedor_id'],
                'cliente_id'           => $data['cliente_id'] ?? null,
                'caja_id'              => $cajaId,
                'total'                => $total,
                'ajuste'               => $ajuste,
                'documento_tipo'       => $data['documento_tipo'] ?? null,
                'documento_numero'     => $docNumero,
                'observaciones'        => $data['observaciones'] ?? null,
                'fecha'                => $data['fecha'],
                'es_referencia_fiscal' => $data['es_referencia_fiscal'] ?? false,
                'igv_incluido'         => $data['igv_incluido'] ?? true,
                'igv_porcentaje'       => $data['igv_porcentaje'] ?? 18.00,
            ]);

            foreach ($data['productos'] as $p) {
                $producto = $this->resolverProducto($p['producto'], $p['precio_unitario']);

                $venta->detalles()->create([
                    'producto_id'     => $producto->id,
                    'producto'        => $p['producto'],
                    'unidad_medida'   => $p['unidad_medida'] ?? 'NIU',
                    'cantidad'        => $p['cantidad'],
                    'precio_unitario' => $p['precio_unitario'],
                    'subtotal'        => (float) bcmul((string) $p['cantidad'], (string) $p['precio_unitario'], 2),
                ]);

                StockMovimiento::create([
                    'producto_id'     => $producto->id,
                    'tipo'            => 'salida',
                    'cantidad'        => $p['cantidad'],
                    'precio_unitario' => $p['precio_unitario'],
                    'referencia_tipo' => 'venta',
                    'referencia_id'   => $venta->id,
                    'fecha'           => $data['fecha'],
                ]);
            }

            $this->recalcularStockVenta($venta);

            // Auto-pago si se indicó método inmediato (no crédito)
            $metodo = $data['metodo_pago'] ?? null;
            if (!empty($metodo) && $metodo !== 'ninguno') {
                app(CobranzaService::class)->registrarPago($venta, [
                    ['metodo' => $metodo, 'monto' => $totalCobrar],
                ]);
            }
        });

        // ── Auto-emisión electrónica para Factura y Boleta ──────────
        if ($venta && in_array($data['documento_tipo'] ?? '', ['factura', 'boleta'])) {
            try {
                $comprobante = app(\App\Services\NubefactService::class)->emitir($venta);
                if ($comprobante->estaAceptado()) {
                    return redirect('/casadets/ventas/' . $venta->id)
                        ->with('success', '✓ Venta registrada y emitida a SUNAT correctamente. ' . $comprobante->numeroCompleto());
                }
                return redirect('/casadets/ventas/' . $venta->id)
                    ->with('warning', 'Venta registrada. Error al emitir a SUNAT: ' . $comprobante->error_mensaje);
            } catch (\Throwable $e) {
                return redirect('/casadets/ventas/' . $venta->id)
                    ->with('warning', 'Venta registrada. Error de conexión con Nubefact: ' . $e->getMessage());
            }
        }

        return redirect('/casadets/ventas')->with('success', 'Venta registrada.');
    }

    /* ─── Editar ────────────────────────────────────────────────── */

    public function edit(Venta $venta)
    {
        $this->authorizeVenta($venta);
        $venta->load('detalles.producto');
        $vendedores = Vendedor::where('activo', true)->orderBy('nombre')->get();
        $clientes   = \App\Models\Cliente::where('activo', true)->orderBy('nombre')->get();
        return view('casadets.ventas.edit', compact('venta', 'vendedores', 'clientes'));
    }

    public function update(Request $request, Venta $venta)
    {
        abort_if(!auth()->user()?->puedeHacer('ventas.editar'), 403, 'No tienes permiso para editar ventas.');
        $this->authorizeVenta($venta);

        $ids = VendedorScope::ids();
        if ($ids !== null && !in_array((int) $request->input('vendedor_id'), $ids)) {
            abort(403, 'No puedes reasignar esta venta a un vendedor que no te pertenece.');
        }
        // Nota: si filtra por caja, permite reasignar vendedor sin restricción

        $data = $request->validate([
            'vendedor_id'          => 'required|exists:vendedores,id',
            'cliente_id'           => 'nullable|exists:clientes,id',
            'documento_tipo'       => 'nullable|in:boleta,factura,proforma',
            'documento_numero'     => ['nullable', 'string', 'max:255',
                Rule::unique('ventas')
                    ->where(fn($q) => $q->where('documento_tipo', $request->documento_tipo))
                    ->ignore($venta->id)],
            'fecha'                => 'required|date',
            'observaciones'        => 'nullable|string',
            'es_referencia_fiscal' => 'boolean',
            'total_cobrar'         => 'nullable|numeric|min:0',
            'productos'            => 'required|array|min:1',
            'productos.*.id'             => 'nullable|integer',
            'productos.*.producto'       => 'required|string|max:255',
            'productos.*.cantidad'       => 'required|numeric|min:0.01',
            'productos.*.precio_unitario'=> 'required|numeric|min:0',
        ], ['documento_numero.unique' => 'Ya existe otra venta con ese número de documento del mismo tipo.']);

        DB::transaction(function () use ($data, $venta) {
            $nuevoTotal = (float) collect($data['productos'])->reduce(
                fn ($carry, $p) => bcadd($carry, bcmul((string) $p['cantidad'], (string) $p['precio_unitario'], 4), 2),
                '0'
            );

            // Ajuste manual de cobro
            $totalCobrar = (isset($data['total_cobrar']) && $data['total_cobrar'] !== null)
                ? max(0.0, round((float) $data['total_cobrar'], 2))
                : $nuevoTotal;
            $ajuste = round($totalCobrar - $nuevoTotal, 2);

            $venta->update([
                'vendedor_id'          => $data['vendedor_id'],
                'cliente_id'           => $data['cliente_id'] ?? null,
                'documento_tipo'       => $data['documento_tipo'] ?? null,
                'documento_numero'     => $data['documento_numero'] ?? null,
                'fecha'                => $data['fecha'],
                'observaciones'        => $data['observaciones'] ?? null,
                'total'                => $nuevoTotal,
                'ajuste'               => $ajuste,
                'es_referencia_fiscal' => $data['es_referencia_fiscal'] ?? false,
            ]);

            // ── Upsert de detalles preservando IDs ────────────────────────
            $idsEnBD     = $venta->detalles()->pluck('id')->toArray();
            $idsEnviados = collect($data['productos'])
                ->pluck('id')->filter()->map(fn($id) => (int) $id)->toArray();

            $aEliminar = array_diff($idsEnBD, $idsEnviados);
            if (!empty($aEliminar)) {
                $venta->detalles()->whereIn('id', $aEliminar)->delete();
            }

            // Limpiar y recrear stock movimientos de esta venta
            StockMovimiento::where('referencia_tipo', 'venta')
                ->where('referencia_id', $venta->id)
                ->delete();

            foreach ($data['productos'] as $p) {
                $detId    = !empty($p['id']) ? (int) $p['id'] : null;
                $producto = $this->resolverProducto($p['producto'], $p['precio_unitario']);

                $detalleData = [
                    'producto_id'     => $producto->id,
                    'producto'        => $p['producto'],
                    'cantidad'        => $p['cantidad'],
                    'precio_unitario' => $p['precio_unitario'],
                    'subtotal'        => (float) bcmul((string) $p['cantidad'], (string) $p['precio_unitario'], 2),
                ];

                if ($detId && in_array($detId, $idsEnBD)) {
                    VentaDetalle::where('id', $detId)->update($detalleData);
                } else {
                    $venta->detalles()->create($detalleData);
                }

                StockMovimiento::create([
                    'producto_id'     => $producto->id,
                    'tipo'            => 'salida',
                    'cantidad'        => $p['cantidad'],
                    'precio_unitario' => $p['precio_unitario'],
                    'referencia_tipo' => 'venta',
                    'referencia_id'   => $venta->id,
                    'fecha'           => $data['fecha'],
                ]);
            }

            $this->recalcularStockVenta($venta);

            $venta->refresh();
            $venta->recalcularEstado();
        });

        return redirect('/casadets/ventas/' . $venta->id)->with('success', 'Venta actualizada.');
    }

    /* ─── Pendientes ────────────────────────────────────────────── */

    public function pendientes(Request $request)
    {
        $query = Venta::with([
                'vendedor:id,nombre',
                'cliente:id,nombre,documento',
                'detalles:id,venta_id,producto,cantidad,precio_unitario,subtotal',
            ])
            ->select('id', 'vendedor_id', 'cliente_id', 'fecha', 'estado',
                     'total', 'ajuste', 'pagado', 'metodo_pago', 'documento_tipo', 'documento_numero')
            ->whereIn('estado', ['pendiente', 'parcial'])
            ->where('es_referencia_fiscal', false)
            ->whereDate('fecha', '<=', today());

        // Si el usuario NO tiene permiso de ver todos, aplicar filtros de caja/vendedor
        if (!auth()->user()?->puedeHacer('pendientes.ver_todos')) {
            VendedorScope::aplicar($query);

            if (session('caja_id')) {
                $query->where('caja_id', session('caja_id'));
            }
        }

        if ($request->filled('vendedor_id')) $query->where('vendedor_id', $request->vendedor_id);

        $ventas     = $query->orderBy('fecha', 'asc')->get();
        $vendedores = \App\Models\Vendedor::select('id', 'nombre')->orderBy('nombre')->get();

        return view('casadets.ventas.pendientes', compact('ventas', 'vendedores'));
    }

    /* ─── Verificar pago ─────────────────────────────────────────── */

    public function pago(Venta $venta)
    {
        abort_if($venta->es_referencia_fiscal, 403, 'Las referencias fiscales no tienen cobranza.');
        $this->authorizeVenta($venta);

        if (in_array($venta->estado, ['anulado', 'anulado_nc'])) {
            return redirect()->route('ventas.index')
                ->with('error', "No se puede registrar el pago: el vale {$venta->documento_tipo} {$venta->documento_numero} está anulado.");
        }
        $venta->load([
            'vendedor:id,nombre',
            'detalles:id,venta_id,producto,cantidad,precio_unitario,subtotal',
            'cliente:id,nombre,documento',
        ]);
        $cobranza          = app(CobranzaService::class);
        $historial         = $cobranza->historialPagos($venta);
        $saldoFavor        = $venta->cliente_id ? $cobranza->saldoFavorDisponible($venta->cliente_id) : 0;
        $saldosDisponibles = $venta->cliente_id ? $cobranza->saldosDisponibles($venta->cliente_id) : collect();

        $ventasPendientesCliente = collect();
        if ($venta->cliente_id) {
            $ventasPendientesCliente = \App\Models\Venta::with([
                    'detalles:id,venta_id,producto,cantidad,precio_unitario,subtotal',
                ])
                ->where('cliente_id', $venta->cliente_id)
                ->where('id', '!=', $venta->id)
                ->whereIn('estado', ['pendiente', 'parcial'])
                ->orderBy('fecha', 'asc')
                ->get();
        }

        return view('casadets.ventas.verificar_pago', compact(
            'venta', 'historial', 'saldoFavor', 'saldosDisponibles', 'ventasPendientesCliente'
        ));
    }

    public function updatePago(Request $request, Venta $venta)
    {
        abort_if($venta->es_referencia_fiscal, 403, 'Las referencias fiscales no tienen cobranza.');
        $this->authorizeVenta($venta);

        if (in_array($venta->estado, ['anulado', 'anulado_nc'])) {
            return redirect()->route('ventas.index')
                ->with('error', "No se puede registrar el pago: el vale {$venta->documento_tipo} {$venta->documento_numero} está anulado.");
        }

        $data = $request->validate([
            'pagos'               => 'required|array|min:1',
            'pagos.*.metodo'      => 'required|in:ninguno,efectivo,tarjeta,yape,plin,transferencia',
            'pagos.*.monto'       => 'required|numeric|min:0',
            'pagos.*.descripcion' => 'nullable|string|max:200',
            'estado_manual'       => 'nullable|in:pendiente,pagado,anulado',
            'evidencias'          => 'nullable|array|max:10',
            'evidencias.*'        => 'file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $ventasAdicionales = array_values(array_filter(
            array_map('intval', $request->input('ventas_adicionales', []))
        ));

        if (!empty($ventasAdicionales)) {
            $cajasIds = VendedorScope::cajaIds();
            $idsVendedor = VendedorScope::ids();
            if ($cajasIds !== null) {
                $unauthorized = Venta::whereIn('id', $ventasAdicionales)
                    ->whereNotIn('caja_id', $cajasIds)
                    ->exists();
                if ($unauthorized) {
                    abort(403, 'No tienes permiso para pagar algunas de las ventas adicionales seleccionadas.');
                }
            } elseif ($idsVendedor !== null) {
                $unauthorized = Venta::whereIn('id', $ventasAdicionales)
                    ->whereNotIn('vendedor_id', $idsVendedor)
                    ->exists();
                if ($unauthorized) {
                    abort(403, 'No tienes permiso para pagar algunas de las ventas adicionales seleccionadas.');
                }
            }
            $allIds = array_merge([$venta->id], $ventasAdicionales);
            try {
                $result = app(CobranzaService::class)->registrarPagoMultiple($allIds, $data['pagos']);
            } catch (\Exception $e) {
                if ($request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
                }
                return back()->withErrors(['error' => $e->getMessage()])->withInput();
            }

            $cobradas = $result['ventas_cobradas'];
            $total    = count($result['ventas_actualizadas']);
            $msg      = "Pago registrado: {$cobradas} de {$total} vales quedaron pagados.";
            if ($result['sobrante'] > 0) {
                $msg .= ' Sobrante S/ ' . number_format($result['sobrante'], 2) . ' no aplicado.';
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success'         => true,
                    'venta_id'        => $venta->id,
                    'estado'          => $result['ventas_actualizadas'][0]['estado'] ?? 'pagado',
                    'saldo_favor'     => 0,
                    'saldo_pendiente' => 0,
                    'msg_saldo_favor' => $msg,
                ]);
            }

            return redirect('/casadets/ventas/' . $venta->id)->with('success', $msg);
        }

        $result = app(CobranzaService::class)->registrarPago(
            $venta,
            $data['pagos'],
            $data['estado_manual'] ?? null
        );

        // ── Guardar evidencias de pago si se subieron archivos ──────────
        $pago = $result['pago'] ?? null;
        if ($pago && $request->hasFile('evidencias')) {
            $archivoService = app(\App\Services\CobranzaArchivoService::class);
            foreach ($request->file('evidencias') as $file) {
                try {
                    $archivoService->guardar($file, $pago, auth()->id());
                } catch (\Throwable $e) {
                    \Log::warning('CobranzaArchivo: no se pudo guardar evidencia', [
                        'pago_id' => $pago->id,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success'         => true,
                'venta_id'        => $venta->id,
                'estado'          => $result['estado'],
                'saldo_favor'     => $result['saldo_favor'],
                'saldo_pendiente' => $result['saldo_pendiente'],
                'msg_saldo_favor' => $result['saldo_favor'] > 0
                    ? 'Se generó un saldo a favor de S/ ' . number_format($result['saldo_favor'], 2)
                    : null,
            ]);
        }

        $msg = 'Pago registrado correctamente.';
        if ($result['saldo_favor'] > 0) {
            $msg .= ' Se generó un saldo a favor de S/ ' . number_format($result['saldo_favor'], 2) . '.';
        }
        return redirect('/casadets/ventas/' . $venta->id)->with('success', $msg);
    }

    /* ─── Reducción de sobrante ─────────────────────────────────── */

    public function reducirSaldo(Request $request, Venta $venta)
    {
        abort_if($venta->es_referencia_fiscal, 403, 'Las referencias fiscales no tienen cobranza.');
        $this->authorizeVenta($venta);

        $data = $request->validate([
            'monto' => 'required|numeric|min:0.01',
        ]);

        try {
            $result = app(CobranzaService::class)->reducirSaldo(
                $venta,
                (float) $data['monto'],
                auth()->id()
            );
        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success'         => true,
                'monto_reducido'  => $result['monto_reducido'],
                'estado'          => $result['estado'],
                'saldo_pendiente' => $result['saldo_pendiente'],
            ]);
        }

        $msg = 'Se redujo S/ ' . number_format($result['monto_reducido'], 2) . ' del saldo pendiente.';
        if ($result['estado'] === 'pagado') {
            $msg .= ' El vale quedó marcado como Pagado.';
        }
        return redirect('/casadets/ventas/' . $venta->id)->with('success', $msg);
    }

    /* ─── Pago múltiple (varios vales a la vez) ─────────────────── */

    public function pagoMultiple(Request $request)
    {
        $ids = array_filter(array_map('intval', (array) $request->input('ventas', [])));

        if (empty($ids)) {
            return redirect('/casadets/pendientes')->with('error', 'Selecciona al menos una venta.');
        }

        $query = Venta::with(['vendedor:id,nombre', 'cliente:id,nombre', 'detalles:id,venta_id,producto,cantidad'])
            ->whereIn('id', $ids)
            ->whereIn('estado', ['pendiente', 'parcial'])
            ->orderBy('fecha', 'asc');

        VendedorScope::aplicar($query);

        $ventas = $query->get();

        if ($ventas->isEmpty()) {
            return redirect('/casadets/pendientes')->with('error', 'Las ventas seleccionadas no están pendientes.');
        }

        $totalPendiente = $ventas->sum(fn ($v) => max(0, (float) bcsub((string) $v->total, (string) $v->pagado, 2)));

        return view('casadets.ventas.pago_multiple', compact('ventas', 'totalPendiente'));
    }

    public function updatePagoMultiple(Request $request)
    {
        $data = $request->validate([
            'ventas'              => 'required|array|min:1',
            'ventas.*'            => 'integer|exists:ventas,id',
            'pagos'               => 'required|array|min:1',
            'pagos.*.metodo'      => 'required|in:ninguno,efectivo,tarjeta,yape,plin,transferencia',
            'pagos.*.monto'       => 'required|numeric|min:0',
            'pagos.*.descripcion' => 'nullable|string|max:200',
        ]);

        $cajasIds = VendedorScope::cajaIds();
        $idsVendedor = VendedorScope::ids();
        if ($cajasIds !== null) {
            $unauthorized = Venta::whereIn('id', $data['ventas'])
                ->whereNotIn('caja_id', $cajasIds)
                ->exists();
            if ($unauthorized) {
                abort(403, 'No tienes permiso para pagar algunas de las ventas seleccionadas.');
            }
        } elseif ($idsVendedor !== null) {
            $unauthorized = Venta::whereIn('id', $data['ventas'])
                ->whereNotIn('vendedor_id', $idsVendedor)
                ->exists();
            if ($unauthorized) {
                abort(403, 'No tienes permiso para pagar algunas de las ventas seleccionadas.');
            }
        }

        try {
            $result = app(CobranzaService::class)->registrarPagoMultiple(
                $data['ventas'],
                $data['pagos']
            );

            $cobradas = $result['ventas_cobradas'];
            $total    = count($result['ventas_actualizadas']);
            $msg      = "Pago registrado: {$cobradas} de {$total} ventas quedaron pagadas.";
            if ($result['sobrante'] > 0) {
                $msg .= ' Sobrante S/ ' . number_format($result['sobrante'], 2) . ' no aplicado (ventas sin cliente para saldo a favor).';
            }

            return redirect('/casadets/pendientes')->with('success', $msg);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    /* ─── Cambio de estado (solo anulado) ───────────────────────── */

    /**
     * BUG #4 CORREGIDO: updateEstado SOLO permite marcar como 'anulado'.
     * Ningún estado financiero (pagado/parcial) puede setearse aquí.
     * Esos estados solo los gestiona CobranzaService.
     *
     * Si la venta tenía pagos registrados, sus movimientos se marcan
     * como 'anulado' en el ledger para mantener integridad financiera.
     */
    public function updateEstado(Request $request, Venta $venta)
    {
        $this->authorizeVenta($venta);
        $request->validate([
            'estado' => 'required|in:anulado',
        ]);

        if (in_array($venta->estado, ['anulado', 'anulado_nc'])) {
            return back()->with('info', 'La venta ya está anulada.');
        }

        DB::transaction(function () use ($venta) {
            // Si tenía cobros, anular sus movimientos en el ledger
            if (in_array($venta->estado, ['pagado', 'parcial'])) {
                $pagoIds = DetallePagoFactura::where('venta_id', $venta->id)
                    ->pluck('pago_id');

                if ($pagoIds->isNotEmpty()) {
                    Movimiento::where('referencia_tipo', 'pago')
                        ->whereIn('referencia_id', $pagoIds)
                        ->where('estado', 'activo')
                        ->each(function ($m) use ($venta) {
                            $m->update([
                                'estado'       => 'anulado',
                                'observaciones' => trim(($m->observaciones ?? '') . ' [Anulado: venta #' . $venta->id . ' cancelada]'),
                            ]);
                        });
                }
            }

            $venta->update(['estado' => 'anulado']);
        });

        return back()->with('success', 'Venta marcada como anulada. El ledger fue actualizado.');
    }

    /* ─── Eliminar (soft delete con reversa financiera) ─────────── */

    /**
     * FIX: Los movimientos del ledger NO se borran — son inmutables.
     * Se marcan como estado='anulado' para excluirlos del balance.
     * Stock se limpia para mantener inventario exacto.
     */
    public function destroy(Venta $venta)
    {
        $this->authorizeVenta($venta);
        DB::transaction(function () use ($venta) {
            // 1. Anular movimientos del ledger (NO borrar)
            $pagoIds = DetallePagoFactura::where('venta_id', $venta->id)
                ->pluck('pago_id');

            if ($pagoIds->isNotEmpty()) {
                Movimiento::where('referencia_tipo', 'pago')
                    ->whereIn('referencia_id', $pagoIds)
                    ->where('estado', 'activo')
                    ->each(function ($m) use ($venta) {
                        $m->update([
                            'estado'       => 'anulado',
                            'observaciones' => trim(($m->observaciones ?? '') . ' [Anulado: venta #' . $venta->id . ' eliminada]'),
                        ]);
                    });
            }

            // 2. Limpiar movimientos de stock (para exactitud de inventario)
            $productoIds = $venta->detalles()->pluck('producto_id')->filter()->unique();
            StockMovimiento::where('referencia_tipo', 'venta')
                ->where('referencia_id', $venta->id)
                ->delete();

            // 3. Recalcular stock de productos afectados
            foreach ($productoIds as $pid) {
                Producto::find($pid)?->recalcularStock();
            }

            // 4. Soft delete — historial financiero se preserva en pagos/movimientos
            $venta->delete();
        });

        return redirect('/casadets/ventas')->with('success', 'Venta eliminada. Los movimientos financieros fueron anulados en el ledger.');
    }

    /* ─── Exportar Excel ─────────────────────────────────────────── */

    public function export(Request $request)
    {
        $query = Venta::with(['vendedor', 'cliente', 'detalles', 'pagosAplicados.metodos']);
        $todas = $request->boolean('todas');
        $desde = $request->input('desde', today()->toDateString());
        $hasta = $request->input('hasta', $desde);
        if ($hasta < $desde) $hasta = $desde;

        VendedorScope::aplicar($query);

        if ($request->filled('tipo'))        $query->where('documento_tipo', $request->tipo);
        if ($request->filled('estado'))      $query->where('estado', $request->estado);
        if (!$todas) {
            $query->whereDate('fecha', '>=', $desde)
                ->whereDate('fecha', '<=', $hasta);
        }
        if ($request->filled('fecha'))       $query->whereDate('fecha', $request->fecha);
        if ($request->filled('cliente_id'))  $query->where('cliente_id', $request->cliente_id);
        if ($request->filled('pago'))        $query->where('metodo_pago', 'like', '%' . $request->pago . '%');

        if ($request->filled('vendedor')) {
            $vendedor = strtolower(trim($request->vendedor));
            $query->whereHas('vendedor', function ($q) use ($vendedor) {
                $q->whereRaw('LOWER(nombre) LIKE ?', ['%' . $vendedor . '%']);
            });
        }

        if ($request->filled('cliente')) {
            $cliente = strtolower(trim($request->cliente));
            $query->whereHas('cliente', function ($q) use ($cliente) {
                $q->whereRaw('LOWER(nombre) LIKE ?', ['%' . $cliente . '%'])
                  ->orWhereRaw("LOWER(COALESCE(documento, '')) LIKE ?", ['%' . $cliente . '%']);
            });
        }

        if ($request->filled('documento')) {
            $documento = strtolower(trim($request->documento));
            $query->where(function ($q) use ($documento) {
                $q->whereRaw("LOWER(COALESCE(documento_tipo, '')) LIKE ?", ['%' . $documento . '%'])
                  ->orWhereRaw("LOWER(COALESCE(documento_numero, '')) LIKE ?", ['%' . $documento . '%']);
            });
        }

        if ($request->filled('total')) {
            $total = str_replace(',', '', trim($request->total));
            $query->where('estado', '!=', 'canjeada')
                ->where('total', 'like', '%' . $total . '%');
        }

        $query->orderByRaw("CASE WHEN documento_tipo='factura' THEN 0
                                 WHEN documento_tipo='boleta'  THEN 1
                                 WHEN documento_tipo='proforma' THEN 2
                                 ELSE 3 END")
              ->orderByRaw("LENGTH(COALESCE(documento_numero,''))")
              ->orderBy('documento_numero')
              ->orderBy('fecha', 'desc');

        $ventas = $query->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ventas');

        $headers = ['Fecha', 'Documento', 'Nro. Doc.', 'Cliente', 'Vendedor', 'Método Pago', 'Banco destino', 'Total Original', 'Total a Cobrar', 'Estado'];
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders'   => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1D4ED8']]],
        ];
        $sheet->getStyle('A1:J1')->applyFromArray($headerStyle);

        $row = 2;
        foreach ($ventas as $v) {
            $esRefFiscal = ($v->estado ?? '') === 'canjeada';

            $bancosDst = collect();
            foreach ($v->pagosAplicados as $pago) {
                foreach ($pago->metodos as $met) {
                    if ($met->descripcion) $bancosDst->push($met->descripcion);
                }
            }
            $bancoDst = $bancosDst->unique()->implode(' / ');

            $sheet->setCellValue("A{$row}", $v->fecha->format('d/m/Y'));
            $sheet->setCellValue("B{$row}", ucfirst($v->documento_tipo ?? ''));
            $sheet->setCellValue("C{$row}", $v->documento_numero ?? '');
            $sheet->setCellValue("D{$row}", $v->cliente->nombre ?? '');
            $sheet->setCellValue("E{$row}", $v->vendedor->nombre ?? '');
            $sheet->setCellValue("F{$row}", $v->metodo_pago ?? '');
            $sheet->setCellValue("G{$row}", $bancoDst);
            $sheet->setCellValue("H{$row}", $esRefFiscal ? '' : (float) $v->total);
            $sheet->setCellValue("I{$row}", $esRefFiscal ? '' : (float) $v->total_a_cobrar);
            $sheet->setCellValue("J{$row}", $esRefFiscal ? 'Ref. fiscal' : ucfirst($v->estado ?? 'pendiente'));

            $metodos    = array_map('trim', explode(',', strtolower($v->metodo_pago ?? '')));
            $esEfectivo = in_array('efectivo', $metodos);

            if ($esRefFiscal) {
                $sheet->getStyle("A{$row}:J{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E9ECEF');
            } elseif (($v->estado ?? '') === 'anulado') {
                $sheet->getStyle("A{$row}:J{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEE2E2');
            } elseif ($esEfectivo) {
                $sheet->getStyle("A{$row}:J{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF08A');
            } elseif (($v->estado ?? '') === 'pagado') {
                $sheet->getStyle("A{$row}:J{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D1FAE5');
            }
            $row++;
        }

        if ($ventas->isEmpty()) {
            $sheet->mergeCells('A2:J2');
            $sheet->setCellValue('A2', 'Sin datos para los filtros seleccionados.');
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A2')->getFont()->getColor()->setRGB('6B7280');
        }

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        if ($row > 2) {
            $sheet->getStyle('H2:I' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        }

        $filename = 'ventas_' . now()->format('Y-m-d') . '.xlsx';
        $writer   = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'ventas_');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /* ─── Helpers privados ───────────────────────────────────────── */

    /**
     * Encuentra o crea un Producto por nombre.
     * Actualiza precio_venta si el nuevo es mayor.
     */
    private function resolverProducto(string $nombre, float $precioVenta): Producto
    {
        $producto = Producto::firstOrCreate(
            ['nombre' => trim($nombre)],
            ['precio_venta' => $precioVenta]
        );

        if ($precioVenta > (float) $producto->precio_venta) {
            $producto->update(['precio_venta' => $precioVenta]);
        }

        return $producto;
    }

    private function recalcularStockVenta(Venta $venta): void
    {
        $productoIds = $venta->detalles()->pluck('producto_id')->filter()->unique();
        foreach ($productoIds as $pid) {
            Producto::find($pid)?->recalcularStock();
        }
    }
}
