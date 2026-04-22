@extends('layouts.app')

@section('content')
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

<div class="card mb-3">
    <div class="card-header">Detalle</div>
    <div class="card-body">
        <p class="mb-1"><strong>Producto:</strong> {{ $compra->producto ?? '—' }}</p>
        <p class="mb-1"><strong>Cantidad:</strong> {{ rtrim(rtrim(number_format($compra->cantidad, 2), '0'), '.') }}</p>
        <p class="mb-1"><strong>Monto unitario:</strong> S/ {{ number_format($compra->monto_unitario, 2) }}</p>
        <p class="mb-1"><strong>Sugerido (cant × unit.):</strong> S/ {{ number_format($compra->cantidad * $compra->monto_unitario, 2) }}</p>
        <p class="mb-0"><strong>Monto total:</strong> S/ {{ number_format($compra->monto_total, 2) }}
            @php $diff = $compra->monto_total - ($compra->cantidad * $compra->monto_unitario); @endphp
            @if(abs($diff) > 0.005)
                <small class="{{ $diff > 0 ? 'text-success' : 'text-danger' }}">
                    (diferencia con sugerido: {{ $diff > 0 ? '+' : '' }}S/ {{ number_format($diff, 2) }})
                </small>
            @endif
        </p>
        @if($compra->observaciones)
            <hr><small class="text-muted">Observaciones:</small> {{ $compra->observaciones }}
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header">Ventas vinculadas</div>
    @if($compra->ventas->count())
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Vendedor</th>
                    <th>Documento</th>
                    <th class="text-end">Total cobrado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($compra->ventas as $v)
                <tr>
                    <td>{{ $v->fecha->format('d/m/Y') }}</td>
                    <td>{{ $v->vendedor->nombre ?? '—' }}</td>
                    <td>{{ $v->documento_tipo ? ucfirst($v->documento_tipo) : '' }} {{ $v->documento_numero }}</td>
                    <td class="text-end">S/ {{ number_format($v->total_cobrado, 2) }}</td>
                    <td><a href="/casadets/ventas/{{ $v->id }}" class="btn btn-sm btn-outline-secondary">Ver</a></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="card-body text-center text-muted py-4">Esta compra no está vinculada a ninguna venta.</div>
    @endif
</div>
@endsection
