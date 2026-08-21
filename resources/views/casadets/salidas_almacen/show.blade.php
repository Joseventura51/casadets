@extends('layouts.app')

@section('content')
@php
    $estadoLabels = [
        'realizada' => ['Realizada', 'success'],
        'parcialmente_devuelta' => ['Parcialmente devuelta', 'warning'],
        'devuelta_completamente' => ['Devuelta completamente', 'info'],
        'anulada' => ['Anulada', 'secondary'],
    ];
    [$estadoLabel, $estadoColor] = $estadoLabels[$salida->estado] ?? [ucfirst($salida->estado), 'secondary'];
@endphp
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <a href="/casadets/salidas-almacen" class="small text-decoration-none">← Salidas de almacén</a>
        <h3 class="mb-0 mt-1">{{ $salida->documento }}</h3>
        <span class="badge bg-{{ $estadoColor }}">{{ $estadoLabel }}</span>
    </div>
    <div class="d-flex gap-2">
        @if(!in_array($salida->estado, ['anulada', 'devuelta_completamente']) && auth()->user()->puedeHacer('salidas-almacen.devolver'))
            <a href="/casadets/salidas-almacen/{{ $salida->id }}/devolucion" class="btn btn-outline-warning btn-sm">
                <i class="bi bi-arrow-return-left me-1"></i>Registrar devolución
            </a>
        @endif
        @if($salida->estado !== 'anulada' && auth()->user()->puedeHacer('salidas-almacen.anular'))
            <form method="POST" action="/casadets/salidas-almacen/{{ $salida->id }}/anular" onsubmit="return confirm('¿Anular esta salida? Se restaurará el stock del origen.')">
                @csrf
                <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle me-1"></i>Anular salida</button>
            </form>
        @endif
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-6 col-md-2"><small class="text-muted d-block">Fecha</small><strong>{{ $salida->fecha?->format('d/m/Y') }}</strong></div>
            <div class="col-12 col-md-4"><small class="text-muted d-block">Origen</small><strong>{{ $salida->origen->nombre }}</strong></div>
            <div class="col-12 col-md-4"><small class="text-muted d-block">Destino</small><strong>{{ $salida->destino->nombre }}</strong></div>
            <div class="col-6 col-md-2"><small class="text-muted d-block">Usuario</small><strong>{{ $salida->usuario?->name ?? '—' }}</strong></div>
        </div>
        @if($salida->observaciones)<hr><div class="small"><strong>Observaciones:</strong> {{ $salida->observaciones }}</div>@endif
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Productos retirados</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>Código</th><th>Producto</th><th>Unidad</th><th>Lote</th><th class="text-end">Cantidad</th><th class="text-end">Precio</th><th class="text-end">Monto</th></tr></thead>
            <tbody>
            @foreach($salida->detalles as $detalle)
                <tr>
                    <td>{{ $detalle->codigo_producto ?? '—' }}</td>
                    <td>{{ $detalle->descripcion_original ?? $detalle->producto->nombre }}</td>
                    <td>{{ $detalle->unidad_medida ?? '—' }}</td>
                    <td>{{ $detalle->lote ?? '—' }}</td>
                    <td class="text-end">{{ number_format($detalle->cantidad, 2) }}</td>
                    <td class="text-end">S/ {{ number_format($detalle->precio_unitario, 2) }}</td>
                    <td class="text-end">S/ {{ number_format($detalle->monto, 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">Historial de Kardex</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Fecha</th><th>Producto</th><th>Almacén</th><th>Tipo</th><th class="text-end">Cantidad</th><th class="text-end">Saldo anterior</th><th class="text-end">Saldo posterior</th></tr></thead>
            <tbody>
            @foreach($movimientos as $movimiento)
                <tr><td>{{ $movimiento->created_at?->format('d/m/Y H:i') }}</td><td>{{ $movimiento->producto->nombre }}</td><td>{{ $movimiento->almacen->nombre }}</td><td>{{ ucfirst($movimiento->tipo) }}</td><td class="text-end">{{ number_format($movimiento->cantidad, 2) }}</td><td class="text-end">{{ number_format($movimiento->saldo_anterior, 2) }}</td><td class="text-end">{{ number_format($movimiento->saldo_posterior, 2) }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection