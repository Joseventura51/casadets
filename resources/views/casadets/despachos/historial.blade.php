@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-1">Despacho #{{ $despacho->id }}</h3>
        <div class="text-muted">{{ $despacho->venta->documento_numero ?: 'Venta #' . $despacho->venta_id }} · {{ $despacho->fecha_hora->format('d/m/Y H:i') }}</div>
    </div>
    <a href="/casadets/despachos/{{ $despacho->venta_id }}/registrar" class="btn btn-outline-secondary">Volver a la venta</a>
</div>
<div class="card">
    <div class="card-body">
        <div class="mb-3"><strong>Responsable:</strong> {{ $despacho->user?->name ?? 'Usuario eliminado' }}</div>
        @if($despacho->observaciones)<div class="mb-3"><strong>Observaciones:</strong> {{ $despacho->observaciones }}</div>@endif
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Producto</th><th class="text-end">Cantidad</th><th>Costos vinculados</th></tr></thead>
                <tbody>
                    @foreach($despacho->detalles as $detalle)
                        <tr>
                            <td>{{ $detalle->ventaDetalle?->producto ?? 'Producto' }}</td>
                            <td class="text-end">{{ number_format((float) $detalle->cantidad, 2) }}</td>
                            <td>
                                @forelse($detalle->costos as $costo)
                                    <span class="badge bg-light text-dark border">{{ number_format((float) $costo->cantidad, 2) }} unidades · asignación #{{ $costo->compra_venta_detalle_id }}</span>
                                @empty
                                    <span class="text-muted">Sin costo vinculado</span>
                                @endforelse
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="alert alert-info mb-0 small">Este registro documenta la entrega física; no crea un movimiento de inventario.</div>
    </div>
</div>
@endsection
