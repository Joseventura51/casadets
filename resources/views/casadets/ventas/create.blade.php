@extends('layouts.app')

@section('content')
<div class="card">
    <div class="card-header bg-white">
        <h4 class="mb-0">Registrar venta</h4>
        <p class="text-muted mb-0 small">Asigna la venta a un vendedor.</p>
    </div>
    <div class="card-body">
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form action="/casadets/ventas" method="POST" class="row g-3">
            @csrf

            <div class="col-md-4">
                <label class="form-label">Vendedor</label>
                <select name="vendedor_id" class="form-select" required>
                    <option value="">Seleccionar</option>
                    @foreach($vendedores as $v)
                        <option value="{{ $v->id }}" {{ old('vendedor_id') == $v->id ? 'selected' : '' }}>{{ $v->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-5">
                <label class="form-label">Producto / servicio</label>
                <input type="text" name="producto" value="{{ old('producto') }}" class="form-control" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">Monto (S/)</label>
                <input type="number" step="0.01" min="0.01" name="monto" value="{{ old('monto') }}" class="form-control" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">Método de pago</label>
                <select name="metodo_pago" class="form-select" required>
                    @foreach(['efectivo','tarjeta','yape','plin','transferencia'] as $m)
                        <option value="{{ $m }}" {{ old('metodo_pago', 'efectivo') == $m ? 'selected' : '' }}>{{ ucfirst($m) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Documento (opcional)</label>
                <select name="documento_tipo" class="form-select">
                    <option value="">Sin documento</option>
                    @foreach(['boleta','factura','proforma'] as $d)
                        <option value="{{ $d }}" {{ old('documento_tipo') == $d ? 'selected' : '' }}>{{ ucfirst($d) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Número de documento</label>
                <input type="text" name="documento_numero" value="{{ old('documento_numero') }}" class="form-control">
            </div>

            <div class="col-md-3">
                <label class="form-label">Fecha</label>
                <input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}" class="form-control" required>
            </div>

            <div class="col-12">
                <label class="form-label">Observaciones</label>
                <textarea name="observaciones" rows="2" class="form-control">{{ old('observaciones') }}</textarea>
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary">Guardar venta</button>
                <a href="/casadets/ventas" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
