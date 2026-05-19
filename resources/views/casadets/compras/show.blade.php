@extends('layouts.app')

@section('content')
@php
    $porVenta = $compra->detalles->groupBy('venta_id');
@endphp

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

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-bag me-1"></i> Detalle de la compra</div>
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-6"><span class="text-muted small">Producto:</span><br><strong>{{ $compra->producto ?? '—' }}</strong></div>
            <div class="col-md-2 text-center">
                <span class="text-muted small">Cantidad</span><br>
                <strong>{{ rtrim(rtrim(number_format($compra->cantidad, 2), '0'), '.') }}</strong>
            </div>
            <div class="col-md-2 text-end">
                <span class="text-muted small">Unitario</span><br>
                <strong>S/ {{ number_format($compra->monto_unitario, 2) }}</strong>
            </div>
            <div class="col-md-2 text-end">
                <span class="text-muted small">Total</span><br>
                <strong class="text-primary fs-5">S/ {{ number_format($compra->monto_total, 2) }}</strong>
                @php $diff = $compra->monto_total - ($compra->cantidad * $compra->monto_unitario); @endphp
                @if(abs($diff) > 0.005)
                    <br><small class="{{ $diff > 0 ? 'text-success' : 'text-danger' }}">
                        dif. {{ $diff > 0 ? '+' : '' }}S/ {{ number_format($diff, 2) }}
                    </small>
                @endif
            </div>
        </div>
        @if($compra->observaciones)
            <hr class="my-2"><small class="text-muted">Obs:</small> {{ $compra->observaciones }}
        @endif
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-link-45deg me-1 text-warning"></i> Productos vinculados de facturas
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
            <i class="bi bi-info-circle me-1"></i> Esta compra no tiene productos vinculados a ninguna factura.
        </div>
    @endif
</div>
@endsection
