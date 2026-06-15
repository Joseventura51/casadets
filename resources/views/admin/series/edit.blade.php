@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="bi bi-123 me-2"></i>Editar Serie — {{ $serie->codigo }}</h3>
    <a href="/admin/series" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<div class="card" style="max-width:520px;">
    <div class="card-body">
        @if($errors->any())
        <div class="alert alert-danger py-2 mb-3">
            <ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
        @endif

        <form action="/admin/series/{{ $serie->id }}" method="POST">
            @csrf @method('PUT')
            <div class="mb-3">
                <label class="form-label fw-semibold">Código de serie</label>
                <input type="text" name="codigo" value="{{ old('codigo', $serie->codigo) }}"
                       class="form-control" required style="text-transform:uppercase;" maxlength="20">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Tipo de documento</label>
                <select name="tipo_documento" class="form-select" required>
                    <option value="boleta"       {{ $serie->tipo_documento === 'boleta'       ? 'selected' : '' }}>Boleta</option>
                    <option value="factura"      {{ $serie->tipo_documento === 'factura'      ? 'selected' : '' }}>Factura</option>
                    <option value="proforma"     {{ $serie->tipo_documento === 'proforma'     ? 'selected' : '' }}>Proforma</option>
                    <option value="nota_credito" {{ $serie->tipo_documento === 'nota_credito' ? 'selected' : '' }}>Nota de crédito</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Correlativo actual</label>
                <input type="number" name="correlativo_actual" value="{{ old('correlativo_actual', $serie->correlativo_actual) }}"
                       class="form-control" min="0" required>
                <div class="form-text">
                    Número actual: <strong class="font-monospace">{{ str_pad($serie->correlativo_actual, 8, '0', STR_PAD_LEFT) }}</strong>.
                    El próximo será {{ str_pad($serie->correlativo_actual + 1, 8, '0', STR_PAD_LEFT) }}.
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Caja asignada</label>
                <select name="caja_id" class="form-select">
                    <option value="">— Sin asignar —</option>
                    @foreach($cajas as $caja)
                    <option value="{{ $caja->id }}" {{ $serie->caja_id == $caja->id ? 'selected' : '' }}>
                        [{{ strtoupper($caja->empresa) }}] {{ $caja->codigo }} — {{ $caja->nombre }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="activa" id="activa" value="1"
                           {{ $serie->activa ? 'checked' : '' }}>
                    <label class="form-check-label" for="activa">Activa</label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i>Guardar cambios
            </button>
        </form>
    </div>
</div>
@endsection
