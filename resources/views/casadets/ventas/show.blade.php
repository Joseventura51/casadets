@extends('layouts.app')

@section('content')
@php
    $comprasVinculadas = $venta->compras;
    $metodosArr = array_filter(explode(',', $venta->metodo_pago ?? ''));
    $badgeMetodo = ['efectivo'=>'success','tarjeta'=>'primary','yape'=>'purple','plin'=>'info','transferencia'=>'warning'];
@endphp

@php
    $esCanjeadaFiscal = $venta->estado === 'canjeada';
    // Extraer proformas vinculadas desde observaciones
    $proformasVinculadas = '';
    if ($esCanjeadaFiscal && $venta->observaciones && str_contains($venta->observaciones, 'Cubre proformas:')) {
        preg_match('/Cubre proformas:\s*(.+?)(?:\s*—|$)/i', $venta->observaciones, $m);
        $proformasVinculadas = trim($m[1] ?? '');
    }
    // Extraer factura emitida desde observaciones (para proformas)
    $facturaEmitida = '';
    if (!$esCanjeadaFiscal && $venta->observaciones && str_contains($venta->observaciones, 'Factura emitida:')) {
        preg_match('/Factura emitida:\s*(.+?)(?:\s*—|$)/i', $venta->observaciones, $m2);
        $facturaEmitida = trim($m2[1] ?? '');
    }
@endphp

@if($esCanjeadaFiscal)
<div class="alert alert-secondary d-flex gap-2 align-items-start mb-3">
    <i class="bi bi-file-earmark-check fs-5 mt-1"></i>
    <div>
        <strong>Referencia fiscal de canje</strong> — Este documento fue emitido como comprobante fiscal pero
        <strong>no genera deuda de cobranza</strong>. La cobranza se gestiona sobre las proformas vinculadas.
        @if($proformasVinculadas)
            <div class="mt-1">
                <span class="text-muted small">Proformas cubiertas:</span>
                @foreach(explode(',', $proformasVinculadas) as $pref)
                    <span class="badge bg-warning text-dark ms-1">{{ trim($pref) }}</span>
                @endforeach
            </div>
        @endif
    </div>
</div>
@elseif($facturaEmitida)
<div class="alert alert-warning d-flex gap-2 align-items-start mb-3" style="border-left: 4px solid #ffc107;">
    <i class="bi bi-receipt fs-5 mt-1"></i>
    <div>
        <strong>Proforma con factura emitida</strong> — Este es el documento de cobranza principal.
        Se emitió la siguiente factura/boleta fiscal:
        @foreach(explode(',', $facturaEmitida) as $fref)
            <span class="badge bg-secondary ms-1">{{ trim($fref) }}</span>
        @endforeach
    </div>
</div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Venta #{{ $venta->id }}</h3>
    <div class="d-flex gap-2">
        @if(!$esCanjeadaFiscal)
        <a href="/casadets/ventas/{{ $venta->id }}/pago" class="btn btn-outline-success btn-sm">
            <i class="bi bi-cash-stack me-1"></i>Verificar pago
        </a>
        @endif
        <a href="/casadets/ventas/{{ $venta->id }}/edit" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Editar
        </a>
        <a href="/casadets/ventas" class="btn btn-outline-secondary btn-sm">← Volver</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-2">
        <div class="card kpi-card">
            <small class="text-muted">Fecha</small>
            <h6 class="mb-0">{{ $venta->fecha->format('d/m/Y') }}</h6>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card kpi-card">
            <small class="text-muted">Vendedor</small>
            <h6 class="mb-0">{{ $venta->vendedor->nombre ?? '—' }}</h6>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card">
            <small class="text-muted">Cliente</small>
            @if($venta->cliente)
                <h6 class="mb-0">{{ $venta->cliente->nombre }}</h6>
                @if($venta->cliente->documento)<small class="text-muted">{{ $venta->cliente->documento }}</small>@endif
                @if($venta->cliente->telefono)<small class="text-muted d-block"><i class="bi bi-telephone me-1"></i>{{ $venta->cliente->telefono }}</small>@endif
            @else
                <h6 class="mb-0 text-muted">—</h6>
            @endif
        </div>
    </div>
    <div class="col-md-2">
        <div class="card kpi-card">
            <small class="text-muted">Pago</small>
            <div class="d-flex flex-wrap gap-1 mt-1">
                @forelse($metodosArr as $m)
                    <span class="badge bg-success">{{ ucfirst(trim($m)) }}</span>
                @empty
                    <span class="text-muted">—</span>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card">
            <small class="text-muted">Documento</small>
            <h6 class="mb-0">
                {{ $venta->documento_tipo ? ucfirst($venta->documento_tipo).' '.$venta->documento_numero : '—' }}
            </h6>
        </div>
    </div>
</div>

{{-- Productos --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-box me-1"></i> Detalle de productos</div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Producto</th>
                    <th class="text-end">Cantidad</th>
                    <th class="text-end">Precio unit.</th>
                    <th class="text-end">Subtotal</th>
                    <th class="text-center" style="width:120px;">Compra vinculada</th>
                </tr>
            </thead>
            <tbody>
                @foreach($venta->detalles as $d)
                @php
                    $comprasDelDetalle = $d->compras ?? collect();
                @endphp
                <tr>
                    <td>{{ $d->producto }}</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format($d->cantidad,2),'0'),'.') }}</td>
                    <td class="text-end">S/ {{ number_format($d->precio_unitario,2) }}</td>
                    <td class="text-end">S/ {{ number_format($d->subtotal,2) }}</td>
                    <td class="text-center">
                        @if($comprasDelDetalle->count())
                            @foreach($comprasDelDetalle as $c)
                                <a href="/casadets/compras/{{ $c->id }}" class="badge bg-warning text-dark text-decoration-none" title="{{ $c->empresa }}">
                                    <i class="bi bi-bag me-1"></i>{{ $c->empresa }}
                                    @if($c->pivot->cantidad != 1)
                                        (×{{ rtrim(rtrim(number_format($c->pivot->cantidad,2),'0'),'.') }})
                                    @endif
                                </a>
                            @endforeach
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="4" class="text-end text-muted">Total productos</th>
                    <th class="text-end"></th>
                </tr>
                <tr>
                    <td colspan="3" class="text-end text-muted small">Subtotal</td>
                    <td class="text-end">S/ {{ number_format($venta->total, 2) }}</td>
                    <td></td>
                </tr>
                <tr class="table-light">
                    <td colspan="3" class="text-end fw-bold">TOTAL COBRADO</td>
                    <td class="text-end fw-bold fs-5">S/ {{ number_format($venta->total_cobrado,2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    @if($venta->observaciones)
    <div class="card-footer bg-white">
        <small class="text-muted">Observaciones:</small> {{ $venta->observaciones }}
    </div>
    @endif
</div>

{{-- Análisis de utilidad --}}
@php
    $filaUtilidad    = [];
    $totalCostoGlobal = 0;

    foreach ($venta->detalles as $d) {
        $subtotalVenta = $d->subtotal ?? ($d->precio_unitario * $d->cantidad);
        $costoTotal    = 0;
        $empresas      = collect();

        foreach ($d->compras as $c) {
            $lineaId       = $c->pivot->compra_linea_id ?? null;
            if ($lineaId) {
                $linea = $c->lineas->firstWhere('id', $lineaId);
            } else {
                // Fallback para registros anteriores: coincidencia por nombre
                $linea = $c->lineas->first(fn($l) => strtolower(trim($l->producto ?? '')) === strtolower(trim($d->producto)));
            }
            $cantidadPivot = $c->pivot->cantidad ?? $d->cantidad;
            $costoUnit     = $linea ? $linea->monto_unitario : null;
            $costoFila     = $costoUnit !== null ? ($costoUnit * $cantidadPivot) : null;
            if ($costoFila !== null) $costoTotal += $costoFila;
            $empresas->push(['empresa' => $c->empresa, 'costo_unit' => $costoUnit, 'cantidad' => $cantidadPivot]);
        }

        $utilidad  = $d->compras->count() ? ($subtotalVenta - $costoTotal) : null;
        $margen    = ($utilidad !== null && $subtotalVenta > 0) ? ($utilidad / $subtotalVenta * 100) : null;

        $totalCostoGlobal += $costoTotal;
        $filaUtilidad[] = [
            'producto'       => $d->producto,
            'cantidad'       => $d->cantidad,
            'precio_unit'    => $d->precio_unitario,
            'subtotal_venta' => $subtotalVenta,
            'costo_total'    => $d->compras->count() ? $costoTotal : null,
            'utilidad'       => $utilidad,
            'margen'         => $margen,
            'empresas'       => $empresas,
        ];
    }

    $totalVenta     = $venta->total_cobrado;
    $utilidadTotal  = $totalVenta - $totalCostoGlobal;
    $margenTotal    = $totalVenta > 0 ? ($utilidadTotal / $totalVenta * 100) : null;
@endphp

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-graph-up-arrow me-1 text-success"></i> Análisis de utilidad
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Producto</th>
                    <th class="text-center">Empresa</th>
                    <th class="text-end">Costo compra</th>
                    <th class="text-end">Venta</th>
                    <th class="text-end">Utilidad</th>
                    <th class="text-end">Margen</th>
                </tr>
            </thead>
            <tbody>
                @foreach($filaUtilidad as $f)
                <tr>
                    <td>
                        <span class="small">{{ $f['producto'] }}</span>
                        <span class="text-muted small"> × {{ rtrim(rtrim(number_format($f['cantidad'],2),'0'),'.') }}</span>
                    </td>
                    <td class="text-center">
                        @if($f['empresas']->count())
                            @foreach($f['empresas']->unique('empresa') as $e)
                                <span class="badge bg-warning text-dark">{{ $e['empresa'] }}</span>
                            @endforeach
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @if($f['costo_total'] !== null)
                            <span class="text-danger">S/ {{ number_format($f['costo_total'], 2) }}</span>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td class="text-end">S/ {{ number_format($f['subtotal_venta'], 2) }}</td>
                    <td class="text-end fw-semibold">
                        @if($f['utilidad'] !== null)
                            <span class="{{ $f['utilidad'] >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $f['utilidad'] >= 0 ? '+' : '' }}S/ {{ number_format($f['utilidad'], 2) }}
                            </span>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @if($f['margen'] !== null)
                            <span class="badge {{ $f['margen'] >= 20 ? 'bg-success' : ($f['margen'] >= 0 ? 'bg-warning text-dark' : 'bg-danger') }}">
                                {{ number_format($f['margen'], 1) }}%
                            </span>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="2" class="text-end">Totales</th>
                    <th class="text-end text-danger">S/ {{ number_format($totalCostoGlobal, 2) }}</th>
                    <th class="text-end">S/ {{ number_format($totalVenta, 2) }}</th>
                    <th class="text-end fw-bold {{ $utilidadTotal >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $utilidadTotal >= 0 ? '+' : '' }}S/ {{ number_format($utilidadTotal, 2) }}
                    </th>
                    <th class="text-end">
                        @if($margenTotal !== null)
                            <span class="badge {{ $margenTotal >= 20 ? 'bg-success' : ($margenTotal >= 0 ? 'bg-warning text-dark' : 'bg-danger') }}">
                                {{ number_format($margenTotal, 1) }}%
                            </span>
                        @endif
                    </th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- Compras vinculadas agrupadas por empresa --}}
@if($comprasVinculadas->count())
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-bag me-1 text-warning"></i> Compras vinculadas
        <small class="text-muted">(productos no propios de esta venta)</small>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Empresa</th>
                    <th>Documento</th>
                    <th>Productos cubiertos</th>
                    <th class="text-end">Total compra</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($comprasVinculadas as $c)
                @php
                    // Productos de esta venta que tienen esta compra vinculada
                    $detallesConEstaCompra = $venta->detalles->filter(
                        fn($d) => $d->compras->contains('id', $c->id)
                    );
                @endphp
                <tr>
                    <td class="fw-semibold">{{ $c->empresa }}</td>
                    <td>
                        {{ $c->documento_tipo ? ucfirst($c->documento_tipo) : '' }}
                        {{ $c->documento_numero ?? '—' }}
                        <br><small class="text-muted">{{ $c->fecha->format('d/m/Y') }}</small>
                    </td>
                    <td>
                        @foreach($detallesConEstaCompra as $d)
                            @php $compraDelDetalle = $d->compras->firstWhere('id', $c->id); @endphp
                            <span class="badge bg-light text-dark border me-1">
                                {{ $d->producto }}
                                @if(($compraDelDetalle->pivot->cantidad ?? 1) != 1)
                                    <span class="text-muted">× {{ rtrim(rtrim(number_format($compraDelDetalle->pivot->cantidad,2),'0'),'.') }}</span>
                                @endif
                            </span>
                        @endforeach
                        @if($detallesConEstaCompra->isEmpty())
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td class="text-end fw-semibold">S/ {{ number_format($c->monto_total,2) }}</td>
                    <td>
                        <a href="/casadets/compras/{{ $c->id }}" class="btn btn-sm btn-outline-secondary">Ver</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
