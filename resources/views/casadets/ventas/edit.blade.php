@extends('layouts.app')

@section('content')
@php
    $metodos = ['efectivo','tarjeta','yape','plin','transferencia'];
    $tipos = ['boleta','factura','proforma'];
@endphp
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Editar venta #{{ $venta->id }}</h3>
    <a href="/casadets/ventas/{{ $venta->id }}" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

@if($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<form action="/casadets/ventas/{{ $venta->id }}" method="POST" class="card">
    @csrf @method('PUT')
    <div class="card-body">
        <div class="alert alert-info small mb-3">
            <strong>Total productos (real):</strong> S/ {{ number_format($venta->total, 2) }} —
            si quieres cambiar productos, debes eliminar la venta y crearla de nuevo.
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Vendedor</label>
                <select name="vendedor_id" class="form-select" required>
                    @foreach($vendedores as $v)
                        <option value="{{ $v->id }}" {{ $v->id == old('vendedor_id', $venta->vendedor_id) ? 'selected' : '' }}>{{ $v->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Método de pago</label>
                <select name="metodo_pago" class="form-select" required>
                    @foreach($metodos as $m)
                        <option value="{{ $m }}" {{ $m == old('metodo_pago', $venta->metodo_pago) ? 'selected' : '' }}>{{ ucfirst($m) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Fecha</label>
                <input type="date" name="fecha" value="{{ old('fecha', $venta->fecha->format('Y-m-d')) }}" class="form-control" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">Tipo documento</label>
                <select name="documento_tipo" class="form-select">
                    <option value="">—</option>
                    @foreach($tipos as $t)
                        <option value="{{ $t }}" {{ $t == old('documento_tipo', $venta->documento_tipo) ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Número documento</label>
                <input type="text" name="documento_numero" value="{{ old('documento_numero', $venta->documento_numero) }}" class="form-control" placeholder="Ej. F002-953">
            </div>
            <div class="col-md-5">
                <label class="form-label">Total cobrado <small class="text-muted">(real: S/ {{ number_format($venta->total, 2) }})</small></label>
                <input type="number" step="0.01" min="0" name="total_cobrado" value="{{ old('total_cobrado', number_format($venta->total_cobrado, 2, '.', '')) }}" class="form-control text-end fw-semibold" required>
            </div>

            <div class="col-12">
                <label class="form-label">Observaciones</label>
                <textarea name="observaciones" class="form-control" rows="2">{{ old('observaciones', $venta->observaciones) }}</textarea>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between">
        <a href="/casadets/ventas/{{ $venta->id }}" class="btn btn-outline-secondary">Cancelar</a>
        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar cambios</button>
    </div>
</form>
@endsection
