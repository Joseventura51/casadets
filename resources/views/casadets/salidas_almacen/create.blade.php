@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">Nueva salida de almacén</h3>
        <p class="text-muted mb-0 small">Registra una salida que ya ocurrió. Solo se descuenta el almacén origen.</p>
    </div>
    <a href="/casadets/salidas-almacen" class="btn btn-outline-secondary btn-sm">Volver</a>
</div>

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<form action="/casadets/salidas-almacen" method="POST" id="salidaForm">
    @csrf
    <div class="card mb-3">
        <div class="card-header">Información de la salida</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="form-label">Documento completo</label>
                    <input name="documento" value="{{ old('documento') }}" class="form-control" placeholder="STR-0007-306" required>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label">Fecha</label>
                    <input type="date" name="fecha" value="{{ old('fecha', today()->toDateString()) }}" class="form-control" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Almacén origen</label>
                    <select name="almacen_origen_id" class="form-select" required>
                        <option value="">Seleccionar</option>
                        @foreach($almacenes as $almacen)
                            <option value="{{ $almacen->id }}" @selected(old('almacen_origen_id') == $almacen->id)>{{ $almacen->codigo }} — {{ $almacen->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Almacén destino</label>
                    <select name="almacen_destino_id" class="form-select" required>
                        <option value="">Seleccionar</option>
                        @foreach($almacenes as $almacen)
                            <option value="{{ $almacen->id }}" @selected(old('almacen_destino_id') == $almacen->id)>{{ $almacen->codigo }} — {{ $almacen->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Observaciones</label>
                    <textarea name="observaciones" rows="2" class="form-control" placeholder="Opcional">{{ old('observaciones') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Productos</span>
            <button type="button" class="btn btn-sm btn-outline-primary" id="agregarLinea"><i class="bi bi-plus-lg me-1"></i>Agregar producto</button>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th style="min-width:260px;">Producto</th><th style="width:150px;">Cantidad</th><th style="width:170px;">Precio unitario</th><th style="width:100px;">Lote</th><th style="width:50px;"></th></tr>
                </thead>
                <tbody id="lineasBody"></tbody>
            </table>
        </div>
    </div>

    <div class="d-flex flex-column flex-sm-row gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Registrar salida</button>
        <a href="/casadets/salidas-almacen" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<template id="lineaTemplate">
    <tr>
        <td>
            <select name="productos[__INDEX__][producto_id]" class="form-select producto-select" required>
                <option value="">Seleccionar producto</option>
                @foreach($productos as $producto)
                    <option value="{{ $producto->id }}" data-precio="{{ $producto->precio_costo }}">{{ $producto->codigo ? $producto->codigo . ' — ' : '' }}{{ $producto->nombre }}</option>
                @endforeach
            </select>
        </td>
        <td><input type="number" name="productos[__INDEX__][cantidad]" class="form-control" step="0.01" min="0.01" required></td>
        <td><input type="number" name="productos[__INDEX__][precio_unitario]" class="form-control precio" step="0.01" min="0" value="0"></td>
        <td><input type="text" name="productos[__INDEX__][lote]" class="form-control" maxlength="100"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger quitar-linea"><i class="bi bi-trash"></i></button></td>
    </tr>
</template>
@push('scripts')
<script>
(() => {
    const body = document.getElementById('lineasBody');
    const template = document.getElementById('lineaTemplate').innerHTML;
    let index = 0;
    const add = () => {
        const wrapper = document.createElement('tbody');
        wrapper.innerHTML = template.replaceAll('__INDEX__', index++);
        const row = wrapper.firstElementChild;
        row.querySelector('.producto-select').addEventListener('change', e => {
            row.querySelector('.precio').value = e.target.selectedOptions[0]?.dataset.precio || 0;
        });
        row.querySelector('.quitar-linea').addEventListener('click', () => {
            if (body.children.length > 1) row.remove();
        });
        body.appendChild(row);
    };
    document.getElementById('agregarLinea').addEventListener('click', add);
    add();
})();
</script>
@endpush
@endsection