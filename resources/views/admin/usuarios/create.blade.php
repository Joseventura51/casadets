@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width:640px;">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">Nuevo usuario</h4>
                <p class="text-muted mb-0 small">Completa los datos para crear una cuenta de acceso.</p>
            </div>
            <a href="/admin/usuarios" class="btn btn-sm btn-outline-secondary">← Volver</a>
        </div>
        <div class="card-body">
            @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <form method="POST" action="/admin/usuarios" class="row g-3">
                @csrf

                <div class="col-12">
                    <label class="form-label">Nombre completo <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Correo electrónico <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Contraseña <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control" required>
                    <div class="form-text">Mínimo 6 caracteres.</div>
                </div>

                <div class="col-12">
                    <label class="form-label">Rol <span class="text-danger">*</span></label>
                    <select name="rol_id" class="form-select" required>
                        <option value="">— Selecciona un rol —</option>
                        @foreach($roles as $rol)
                        <option value="{{ $rol->id }}" {{ old('rol_id') == $rol->id ? 'selected' : '' }}>
                            {{ $rol->nombre }} — {{ $rol->descripcion }}
                        </option>
                        @endforeach
                    </select>
                </div>

                @if($vendedores->isNotEmpty())
                <div class="col-12">
                    <label class="form-label">Vendedores asociados <span class="text-muted small">(para rol Vendedor)</span></label>
                    <div class="border rounded p-2" style="max-height:160px;overflow-y:auto;">
                        @foreach($vendedores as $v)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="vendedores[]"
                                value="{{ $v->id }}" id="vend_{{ $v->id }}"
                                {{ in_array($v->id, old('vendedores', [])) ? 'checked' : '' }}>
                            <label class="form-check-label" for="vend_{{ $v->id }}">{{ $v->nombre }}</label>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="activo" id="activo"
                            {{ old('activo', '1') ? 'checked' : '' }}>
                        <label class="form-check-label" for="activo">Usuario activo</label>
                    </div>
                </div>

                <div class="col-12 d-flex gap-2 border-top pt-3 mt-1">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-person-check me-1"></i> Crear usuario
                    </button>
                    <a href="/admin/usuarios" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
