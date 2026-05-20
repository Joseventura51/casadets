@extends('layouts.app')

@section('content')
@php $porVenta = $compra->detalles->groupBy('venta_id'); @endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Compra #{{ $compra->id }}</h3>
    <div class="d-flex gap-2">
        <a href="/casadets/compras/{{ $compra->id }}/edit" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> Editar</a>
        <a href="/casadets/compras" class="btn btn-outline-secondary btn-sm">← Volver</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card kpi-card"><small class="text-muted">Fecha</small><h6 class="mb-0">{{ $compra->fecha->format('d/m/Y') }}</h6></div></div>
    <div class="col-md-3"><div class="card kpi-card"><small class="text-muted">Empresa</small><h6 class="mb-0">{{ $compra->empresa }}</h6></div></div>
    <div class="col-md-3"><div class="card kpi-card"><small class="text-muted">Documento</small><h6 class="mb-0">{{ $compra->documento_tipo ? ucfirst($compra->documento_tipo) : '—' }} {{ $compra->documento_numero }}</h6></div></div>
    <div class="col-md-3"><div class="card kpi-card"><small class="text-muted">Total</small><h6 class="mb-0 text-primary">S/ {{ number_format($compra->monto_total, 2) }}</h6></div></div>
</div>

@if($compra->observaciones)
<div class="alert alert-light border mb-3"><small class="text-muted">Observaciones:</small> {{ $compra->observaciones }}</div>
@endif

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-list-ul me-1"></i> Productos comprados</div>
    @if($compra->lineas->count())
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Producto / Descripción</th>
                    <th class="text-end" style="width:100px;">Cantidad</th>
                    <th class="text-end" style="width:110px;">P. Unitario</th>
                    <th class="text-end" style="width:120px;">Total línea</th>
                </tr>
            </thead>
            <tbody>
                @foreach($compra->lineas as $l)
                <tr>
                    <td>{{ $l->producto ?? '—' }}</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format($l->cantidad, 2), '0'), '.') }}</td>
                    <td class="text-end">S/ {{ number_format($l->monto_unitario, 2) }}</td>
                    <td class="text-end fw-semibold">S/ {{ number_format($l->monto_total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="3" class="text-end">Total general</th>
                    <th class="text-end text-primary fs-6">S/ {{ number_format($compra->monto_total, 2) }}</th>
                </tr>
            </tfoot>
        </table>
    </div>
    @else
    <div class="card-body text-center text-muted py-3">Sin líneas de producto registradas.</div>
    @endif
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-link-45deg me-1 text-warning"></i> Productos vinculados de ventas
    </div>
    @if($porVenta->count())
        @foreach($porVenta as $ventaId => $detalles)
            @php $venta = $detalles->first()->venta; @endphp
            <div class="border-bottom">
                <div class="px-3 py-2 bg-light d-flex justify-content-between align-items-center">
                    <div>
                        <strong>{{ $venta->documento_tipo ? ucfirst($venta->documento_tipo) : '' }} {{ $venta->documento_numero }}</strong>
                        <span class="text-muted small ms-2">{{ $venta->fecha->format('d/m/Y') }} · {{ $venta->vendedor->nombre ?? '—' }}</span>
                    </div>
                    <a href="/casadets/ventas/{{ $venta->id }}" class="btn btn-sm btn-outline-secondary">Ver venta</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Producto</th>
                                <th class="text-end">Cant. venta</th>
                                <th class="text-end text-warning-emphasis">Cant. comprada</th>
                                <th class="text-end">Precio venta</th>
                                <th class="text-end">Subtotal venta</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($detalles as $d)
                            <tr>
                                <td>{{ $d->producto }}</td>
                                <td class="text-end text-muted">{{ rtrim(rtrim(number_format($d->cantidad, 2), '0'), '.') }}</td>
                                <td class="text-end">
                                    <span class="badge bg-warning text-dark">
                                        {{ rtrim(rtrim(number_format($d->pivot->cantidad ?? 1, 2), '0'), '.') }}
                                    </span>
                                </td>
                                <td class="text-end">S/ {{ number_format($d->precio_unitario, 2) }}</td>
                                <td class="text-end">S/ {{ number_format($d->subtotal, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    @else
        <div class="card-body text-center text-muted py-4">
            <i class="bi bi-info-circle me-1"></i> Esta compra no tiene productos vinculados a ninguna venta.
        </div>
    @endif
</div>
@endsection
