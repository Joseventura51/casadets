@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width:640px;">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">Editar usuario</h4>
                <p class="text-muted mb-0 small">{{ $usuario->email }}</p>
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

            <form method="POST" action="/admin/usuarios/{{ $usuario->id }}" class="row g-3">
                @csrf
                @method('PUT')

                <div class="col-12">
                    <label class="form-label">Nombre completo <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control"
                        value="{{ old('name', $usuario->name) }}" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Correo electrónico <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control"
                        value="{{ old('email', $usuario->email) }}" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Nueva contraseña</label>
                    <input type="password" name="password" class="form-control">
                    <div class="form-text">Dejar vacío para no cambiar. Mínimo 6 caracteres.</div>
                </div>

                <div class="col-12">
                    <label class="form-label">Rol <span class="text-danger">*</span></label>
                    <select name="rol_id" class="form-select" required>
                        <option value="">— Selecciona un rol —</option>
                        @foreach($roles as $rol)
                        <option value="{{ $rol->id }}"
                            {{ old('rol_id', $usuario->rol_id) == $rol->id ? 'selected' : '' }}>
                            {{ $rol->nombre }} — {{ $rol->descripcion }}
                        </option>
                        @endforeach
                    </select>
                </div>

                @if($vendedores->isNotEmpty())
                <div class="col-12">
                    <label class="form-label">Vendedores asociados <span class="text-muted small">(para rol Vendedor)</span></label>
                    <div class="border rounded p-2" style="max-height:160px;overflow-y:auto;">
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

                @if($cajas->isNotEmpty())
                @php
                    $cajasAsignadas    = old('cajas', $usuario->cajasPermitidas->pluck('id')->toArray());
                    $cajaPrincipalId   = old('caja_principal', $usuario->cajasPermitidas->firstWhere('pivot.principal', true)?->id ?? '');
                @endphp
                <div class="col-12">
                    <label class="form-label fw-semibold">Cajas permitidas <span class="text-muted small">(opcional)</span></label>
                    <div class="border rounded p-2" style="max-height:180px;overflow-y:auto;">
                        @foreach($cajas as $caja)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="cajas[]"
                                value="{{ $caja->id }}" id="caja_{{ $caja->id }}"
                                {{ in_array($caja->id, $cajasAsignadas) ? 'checked' : '' }}>
                            <label class="form-check-label" for="caja_{{ $caja->id }}">
                                <span class="badge bg-secondary me-1">{{ strtoupper($caja->empresa) }}</span>
                                <code>{{ $caja->codigo }}</code> — {{ $caja->nombre }}
                            </label>
                        </div>
                        @endforeach
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Caja principal</label>
                    <select name="caja_principal" class="form-select form-select-sm">
                        <option value="">— Ninguna —</option>
                        @foreach($cajas as $caja)
                        <option value="{{ $caja->id }}" {{ $cajaPrincipalId == $caja->id ? 'selected' : '' }}>
                            {{ $caja->codigo }} — {{ $caja->nombre }}
                        </option>
                        @endforeach
                    </select>
                    <div class="form-text">La caja que se seleccionará automáticamente al iniciar sesión.</div>
                </div>
                @endif

                <div class="col-12">
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

                <div class="col-12 d-flex gap-2 border-top pt-3 mt-1">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Guardar cambios
                    </button>
                    <a href="/admin/usuarios" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
