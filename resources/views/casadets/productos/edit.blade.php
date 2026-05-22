@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width:640px;">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">Editar producto</h4>
                <p class="text-muted mb-0 small">{{ $producto->nombre }}</p>
            </div>
            <div class="d-flex gap-2">
                <a href="/casadets/productos/{{ $producto->id }}" class="btn btn-sm btn-outline-secondary">← Volver</a>
            </div>
        </div>
        <div class="card-body">
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            <form action="/casadets/productos/{{ $producto->id }}" method="POST" class="row g-3">
                @csrf
                @method('PUT')

                <div class="col-12">
                    <label class="form-label">Nombre <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" value="{{ old('nombre', $producto->nombre) }}"
                           class="form-control" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Código / SKU</label>
                    <input type="text" name="codigo" value="{{ old('codigo', $producto->codigo) }}"
                           class="form-control" placeholder="ej: VFT-001">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Empresa <span class="text-danger">*</span></label>
                    <select name="empresa" class="form-select" required>
                        <option value="casadets" {{ old('empresa', $producto->empresa) === 'casadets' ? 'selected' : '' }}>CASADETS</option>
                        <option value="zendy"    {{ old('empresa', $producto->empresa) === 'zendy'    ? 'selected' : '' }}>ZENDY</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Precio de venta (S/) <span class="text-danger">*</span></label>
                    <input type="number" name="precio_venta" value="{{ old('precio_venta', $producto->precio_venta) }}"
                           class="form-control" step="0.01" min="0" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Precio de costo (S/)</label>
                    <input type="number" name="precio_costo" value="{{ old('precio_costo', $producto->precio_costo) }}"
                           class="form-control" step="0.01" min="0">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Stock actual</label>
                    <input type="text" value="{{ number_format($producto->stock_actual, 2) }}"
                           class="form-control" readonly disabled>
                    <div class="form-text">
                        Solo editable desde
                        <a href="/casadets/productos/{{ $producto->id }}#ajuste">ajuste de stock</a>.
                    </div>
                </div>

                <div class="col-md-6 d-flex align-items-center">
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="activo"
                               id="activoCheck" value="1"
                               {{ old('activo', $producto->activo) ? 'checked' : '' }}>
                        <label class="form-check-label" for="activoCheck">Producto activo</label>
                    </div>
                </div>

                <div class="col-12 d-flex gap-2 border-top pt-3 mt-1">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Guardar cambios
                    </button>
                    <a href="/casadets/productos/{{ $producto->id }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
