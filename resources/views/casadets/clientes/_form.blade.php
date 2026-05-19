@if($errors->any())
<div class="alert alert-danger mb-3">
    <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-person me-1"></i> Datos del cliente</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Nombre / Razón social *</label>
                <input type="text" name="nombre" value="{{ old('nombre', $cliente->nombre ?? '') }}"
                    class="form-control" required placeholder="Ej. Juan Pérez">
            </div>
            <div class="col-md-3">
                <label class="form-label">DNI / RUC</label>
                <input type="text" name="documento" value="{{ old('documento', $cliente->documento ?? '') }}"
                    class="form-control" placeholder="Ej. 12345678">
            </div>
            <div class="col-md-3">
                <label class="form-label">Teléfono</label>
                <input type="text" name="telefono" value="{{ old('telefono', $cliente->telefono ?? '') }}"
                    class="form-control" placeholder="Ej. 987654321">
            </div>
            <div class="col-md-9">
                <label class="form-label">Dirección</label>
                <input type="text" name="direccion" value="{{ old('direccion', $cliente->direccion ?? '') }}"
                    class="form-control" placeholder="Ej. Av. Principal 123">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="activo" value="1" id="activoCheck"
                        {{ old('activo', $cliente->activo ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="activoCheck">Cliente activo</label>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between">
    <a href="/casadets/clientes" class="btn btn-outline-secondary">Cancelar</a>
    <button class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Guardar</button>
</div>
