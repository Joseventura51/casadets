@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="bi bi-123 me-2"></i>Nueva Serie</h3>
    <a href="/admin/series" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<div class="card" style="max-width:520px;">
    <div class="card-body">
        @if($errors->any())
        <div class="alert alert-danger py-2 mb-3">
            <ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
        @endif

        <form action="/admin/series" method="POST">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold">Código de serie <span class="text-danger">*</span></label>
                <input type="text" name="codigo" value="{{ old('codigo') }}"
                       class="form-control" placeholder="Ej: B001, F001, P001"
                       style="text-transform:uppercase;" required maxlength="20">
                <div class="form-text">Prefijo que identifica la serie. Ej: B001, F002</div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Tipo de documento <span class="text-danger">*</span></label>
                <select name="tipo_documento" class="form-select" required>
                    <option value="">— Seleccionar —</option>
                    <option value="boleta"       {{ old('tipo_documento') === 'boleta'       ? 'selected' : '' }}>Boleta</option>
                    <option value="factura"      {{ old('tipo_documento') === 'factura'      ? 'selected' : '' }}>Factura</option>
                    <option value="proforma"     {{ old('tipo_documento') === 'proforma'     ? 'selected' : '' }}>Proforma</option>
                    <option value="nota_credito" {{ old('tipo_documento') === 'nota_credito' ? 'selected' : '' }}>Nota de crédito</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Correlativo actual</label>
                <input type="number" name="correlativo_actual" value="{{ old('correlativo_actual', 0) }}"
                       class="form-control" min="0" required>
                <div class="form-text">El próximo número emitido será este + 1.</div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Caja asignada</label>
                <select name="caja_id" class="form-select">
                    <option value="">— Sin asignar —</option>
                    @foreach($cajas as $caja)
                    <option value="{{ $caja->id }}"
                        {{ (old('caja_id', request('caja_id')) == $caja->id) ? 'selected' : '' }}>
                        [{{ strtoupper($caja->empresa) }}] {{ $caja->codigo }} — {{ $caja->nombre }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="activa" id="activa" value="1"
                           {{ old('activa', '1') ? 'checked' : '' }}>
                    <label class="form-check-label" for="activa">Activa</label>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Crear serie
                </button>
                <a href="/admin/series" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
