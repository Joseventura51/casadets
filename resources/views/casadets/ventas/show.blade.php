@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Venta #{{ $venta->id }}</h3>
    <a href="/casadets/ventas" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card kpi-card"><small class="text-muted">Fecha</small><h6 class="mb-0">{{ $venta->fecha->format('d/m/Y') }}</h6></div></div>
    <div class="col-md-3"><div class="card kpi-card"><small class="text-muted">Vendedor</small><h6 class="mb-0">{{ $venta->vendedor->nombre ?? '—' }}</h6></div></div>
    <div class="col-md-3"><div class="card kpi-card"><small class="text-muted">Pago</small><h6 class="mb-0">{{ ucfirst($venta->metodo_pago) }}</h6></div></div>
    <div class="col-md-3"><div class="card kpi-card"><small class="text-muted">Documento</small><h6 class="mb-0">{{ $venta->documento_tipo ? ucfirst($venta->documento_tipo).' '.$venta->documento_numero : '—' }}</h6></div></div>
</div>

<div class="card">
    <div class="card-header">Detalle de productos</div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Producto</th>
                    <th class="text-end">Cantidad</th>
                    <th class="text-end">Precio unit.</th>
                    <th class="text-end">Subtotal</th>
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
            <tfoot>
                <tr class="table-light">
                    <th colspan="3" class="text-end">TOTAL</th>
                    <th class="text-end fs-5">S/ {{ number_format($venta->total, 2) }}</th>
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
@endsection
