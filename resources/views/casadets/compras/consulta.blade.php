@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="bi bi-search me-1"></i> Consulta de documento</h3>
    <a href="/casadets/compras" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col">
                <label class="form-label small mb-1">Número de documento (factura, boleta, proforma…)</label>
                <input type="text" name="q" value="{{ $query }}" class="form-control"
                    placeholder="Ej: F001-123, B002-45, PR0001-8…" autofocus>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary"><i class="bi bi-search me-1"></i> Buscar</button>
            </div>
        </form>
    </div>
</div>

@if($query && !$resultados->count())
    <div class="alert alert-warning">
        No se encontró ningún documento con "<strong>{{ $query }}</strong>".
    </div>
@endif

@foreach($resultados as $venta)
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
            <span class="badge bg-secondary me-1">{{ ucfirst($venta->documento_tipo ?? '') }}</span>
            <strong class="fs-5">{{ $venta->documento_numero }}</strong>
            <span class="text-muted ms-2">{{ $venta->fecha->format('d/m/Y') }}</span>
            @if($venta->vendedor)
                <span class="text-muted ms-2">· {{ $venta->vendedor->nombre }}</span>
            @endif
        </div>
        <div class="d-flex gap-2 align-items-center">
            <span class="fw-semibold text-primary">S/ {{ number_format($venta->total_cobrado, 2) }}</span>
            <a href="/casadets/ventas/{{ $venta->id }}" class="btn btn-sm btn-outline-secondary" target="_blank">
                <i class="bi bi-box-arrow-up-right me-1"></i>Ver venta
            </a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Producto</th>
                    <th class="text-end" style="width:100px;">Cantidad</th>
                    <th class="text-end" style="width:110px;">P. Unitario</th>
                    <th class="text-end" style="width:110px;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($venta->detalles as $d)
                <tr>
                    <td>{{ $d->producto }}</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format($d->cantidad, 2), '0'), '.') }}</td>
                    <td class="text-end">S/ {{ number_format($d->precio_unitario, 2) }}</td>
                    <td class="text-end">S/ {{ number_format($d->subtotal, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="3" class="text-end">Total</th>
                    <th class="text-end text-primary">S/ {{ number_format($venta->total_cobrado, 2) }}</th>
                </tr>
            </tfoot>
        </table>
    </div>
    @if($venta->cliente)
    <div class="card-footer bg-transparent border-top py-2 text-muted small">
        <i class="bi bi-person me-1"></i> Cliente: {{ $venta->cliente->nombre ?? '—' }}
        @if($venta->cliente->ruc) · RUC {{ $venta->cliente->ruc }} @endif
    </div>
    @endif
</div>
@endforeach
@endsection
