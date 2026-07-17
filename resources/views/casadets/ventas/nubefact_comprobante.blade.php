@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <i class="bi bi-send-check me-2 text-success"></i>
        Comprobante Electrónico — {{ $comprobante->tipoLabel() }} {{ $comprobante->numeroCompleto() }}
    </h4>
    <a href="/casadets/ventas/{{ $comprobante->venta_id }}" class="btn btn-outline-secondary btn-sm">← Volver al vale</a>
</div>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger">{{ session('error') }}</div>
@endif

{{-- Estado --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body d-flex align-items-center gap-3">
        @if($comprobante->estaAceptado())
            <span class="badge fs-6 bg-success px-3 py-2"><i class="bi bi-check-circle me-1"></i>Aceptado por SUNAT</span>
        @elseif($comprobante->estado === 'rechazado')
            <span class="badge fs-6 bg-danger px-3 py-2"><i class="bi bi-x-circle me-1"></i>Rechazado</span>
        @else
            <span class="badge fs-6 bg-warning text-dark px-3 py-2"><i class="bi bi-hourglass-split me-1"></i>Pendiente</span>
        @endif

        <div>
            <div class="fw-semibold">{{ $comprobante->tipoLabel() }} {{ $comprobante->numeroCompleto() }}</div>
            <small class="text-muted">Emitido: {{ $comprobante->created_at->format('d/m/Y H:i') }}</small>
        </div>

        @if($comprobante->enlace_pdf)
        <a href="{{ $comprobante->enlace_pdf }}" target="_blank" class="btn btn-outline-danger btn-sm ms-auto">
            <i class="bi bi-file-earmark-pdf me-1"></i>Ver PDF
        </a>
        @elseif($comprobante->pdf_url)
        <a href="{{ $comprobante->pdf_url }}" target="_blank" class="btn btn-outline-danger btn-sm ms-auto">
            <i class="bi bi-file-earmark-pdf me-1"></i>Ver PDF
        </a>
        @endif
    </div>
</div>

{{-- Error --}}
@if($comprobante->error_mensaje)
<div class="alert alert-danger d-flex gap-2 align-items-start">
    <i class="bi bi-exclamation-triangle-fill fs-5 mt-1"></i>
    <div>
        <strong>Mensaje de Nubefact / SUNAT:</strong><br>
        {{ $comprobante->error_mensaje }}
    </div>
</div>
@if(!$comprobante->estaAceptado())
<form method="POST" action="/casadets/nubefact/{{ $comprobante->id }}/reintentar">
    @csrf
    <button class="btn btn-warning">
        <i class="bi bi-arrow-clockwise me-1"></i>Reintentar emisión
    </button>
</form>
@endif
@endif

{{-- Hash --}}
@if($comprobante->hash)
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold small">Datos técnicos</div>
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-6">
                <small class="text-muted">HASH</small>
                <div class="font-monospace small text-break">{{ $comprobante->hash }}</div>
            </div>
            @if($comprobante->nubefact_id)
            <div class="col-md-6">
                <small class="text-muted">ID Nubefact</small>
                <div class="font-monospace small">{{ $comprobante->nubefact_id }}</div>
            </div>
            @endif
        </div>
    </div>
</div>
@endif

{{-- Detalle de la venta --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">Venta vinculada</div>
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-3">
                <small class="text-muted d-block">Fecha</small>
                <span>{{ $comprobante->venta->fecha->format('d/m/Y') }}</span>
            </div>
            <div class="col-md-5">
                <small class="text-muted d-block">Cliente</small>
                <span>{{ $comprobante->venta->cliente?->nombre ?? 'Cliente varios' }}</span>
                @if($comprobante->venta->cliente?->documento)
                    <small class="text-muted ms-1">{{ $comprobante->venta->cliente->documento }}</small>
                @endif
            </div>
            <div class="col-md-4 text-end">
                <small class="text-muted d-block">Total</small>
                <span class="fw-bold fs-5">S/ {{ number_format($comprobante->venta->total_a_cobrar, 2) }}</span>
            </div>
        </div>

        <table class="table table-sm mt-3 mb-0">
            <thead class="table-light">
                <tr>
                    <th>Producto</th>
                    <th class="text-end">Cant.</th>
                    <th class="text-end">P. Unit.</th>
                    <th class="text-end">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($comprobante->venta->detalles as $d)
                <tr>
                    <td>{{ $d->producto }}</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format($d->cantidad,2),'0'),'.') }}</td>
                    <td class="text-end">S/ {{ number_format($d->precio_unitario,2) }}</td>
                    <td class="text-end">S/ {{ number_format($d->subtotal,2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
