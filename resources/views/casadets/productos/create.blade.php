@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width:640px;">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">Nuevo producto</h4>
                <p class="text-muted mb-0 small">El stock se gestiona desde el kardex (ajuste manual o compras).</p>
            </div>
            <a href="/casadets/productos" class="btn btn-sm btn-outline-secondary">← Volver</a>
        </div>
        <div class="card-body">
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            <form action="/casadets/productos" method="POST" class="row g-3">
                @csrf

                <div class="col-12">
                    <label class="form-label">Nombre <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" value="{{ old('nombre') }}"
                           class="form-control" placeholder="ej: Vestido floral talla M" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Código / SKU</label>
                    <input type="text" name="codigo" value="{{ old('codigo') }}"
                           class="form-control" placeholder="ej: VFT-001">
                    <div class="form-text">Opcional. Para búsqueda rápida.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Empresa <span class="text-danger">*</span></label>
                    <select name="empresa" class="form-select" required>
                        <option value="casadets" {{ old('empresa', 'casadets') === 'casadets' ? 'selected' : '' }}>CASADETS</option>
                        <option value="zendy"    {{ old('empresa') === 'zendy' ? 'selected' : '' }}>ZENDY</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Precio de venta (S/) <span class="text-danger">*</span></label>
                    <input type="number" name="precio_venta" value="{{ old('precio_venta') }}"
                           class="form-control" step="0.01" min="0" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Precio de costo (S/)</label>
                    <input type="number" name="precio_costo" value="{{ old('precio_costo', '0') }}"
                           class="form-control" step="0.01" min="0">
                    <div class="form-text">Base para cálculo de margen.</div>
                </div>

                <div class="col-12 d-flex gap-2 border-top pt-3 mt-1">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Crear producto
                    </button>
                    <a href="/casadets/productos" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
