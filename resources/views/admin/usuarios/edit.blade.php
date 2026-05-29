@extends('layouts.app')

@section('content')
<div class="mb-4">
    <a href="/admin/usuarios" class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left me-1"></i> Volver
    </a>
    <h3 class="mb-0">Editar usuario</h3>
    <p class="text-muted mb-0">{{ $usuario->email }}</p>
</div>

<div class="card bg-dark border-secondary" style="max-width:600px;">
    <div class="card-body">
        @if($errors->any())
        <div class="alert alert-danger mb-3">
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="/admin/usuarios/{{ $usuario->id }}">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="form-label">Nombre completo</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $usuario->name) }}" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Correo electrónico</label>
                <input type="email" name="email" class="form-control" value="{{ old('email', $usuario->email) }}" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Nueva contraseña <span class="text-muted small">(dejar vacío para no cambiar)</span></label>
                <input type="password" name="password" class="form-control">
                <div class="form-text text-muted">Mínimo 6 caracteres si deseas cambiarla.</div>
            </div>

            <div class="mb-3">
                <label class="form-label">Rol</label>
                <select name="rol_id" class="form-select" required>
                    <option value="">— Selecciona un rol —</option>
                    @foreach($roles as $rol)
                    <option value="{{ $rol->id }}" {{ old('rol_id', $usuario->rol_id) == $rol->id ? 'selected' : '' }}>
                        {{ $rol->nombre }} — {{ $rol->descripcion }}
                    </option>
                    @endforeach
                </select>
            </div>

            @if($vendedores->isNotEmpty())
            <div class="mb-3">
                <label class="form-label">Vendedores asociados <span class="text-muted small">(para rol Vendedor)</span></label>
                <div class="border border-secondary rounded p-2" style="max-height:160px;overflow-y:auto;">
                    @php $asignados = old('vendedores', $usuario->vendedores->pluck('id')->toArray()); @endphp
                    @foreach($vendedores as $v)
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="vendedores[]"
                            value="{{ $v->id }}" id="vend_{{ $v->id }}"
                            {{ in_array($v->id, $asignados) ? 'checked' : '' }}>
                        <label class="form-check-label" for="vend_{{ $v->id }}">{{ $v->nombre }}</label>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <div class="mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="activo" id="activo"
                        {{ old('activo', $usuario->activo) ? 'checked' : '' }}
                        @if($usuario->id === auth()->id()) disabled @endif>
                    <label class="form-check-label" for="activo">
                        Usuario activo
                        @if($usuario->id === auth()->id())
                            <span class="text-muted small">(no puedes desactivar tu propia cuenta)</span>
                        @endif
                    </label>
                    @if($usuario->id === auth()->id())
                        <input type="hidden" name="activo" value="1">
                    @endif
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Guardar cambios
                </button>
                <a href="/admin/usuarios" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
