@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="bi bi-cash-register me-2"></i>Nueva Caja</h3>
    <a href="/admin/cajas" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<div class="card" style="max-width:520px;">
    <div class="card-body">
        @if($errors->any())
        <div class="alert alert-danger py-2 mb-3">
            <ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
        @endif

        <form action="/admin/cajas" method="POST">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold">Código <span class="text-danger">*</span></label>
                <input type="text" name="codigo" value="{{ old('codigo') }}"
                       class="form-control" placeholder="Ej: CAJA01, C01" required
                       style="text-transform:uppercase;" maxlength="20">
                <div class="form-text">Identificador único. Ej: CAJA01, CAJA-A, C01</div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                <input type="text" name="nombre" value="{{ old('nombre') }}"
                       class="form-control" placeholder="Ej: Caja Principal, Caja 02" required maxlength="100">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Empresa <span class="text-danger">*</span></label>
                <select name="empresa" class="form-select" required>
                    <option value="casadets" {{ old('empresa') === 'casadets' ? 'selected' : '' }}>CASADETS</option>
                    <option value="zendy"    {{ old('empresa') === 'zendy'    ? 'selected' : '' }}>ZENDY</option>
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
                    <i class="bi bi-check-lg me-1"></i>Crear caja
                </button>
                <a href="/admin/cajas" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
