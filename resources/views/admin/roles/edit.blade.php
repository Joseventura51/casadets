@extends('layouts.app')

@section('title', $rol->exists ? 'Editar rol' : 'Nuevo rol')

@php
    use App\Support\PermisoCatalog;
    $modulosCatalog  = PermisoCatalog::MODULOS;
    $permisosCatalog = PermisoCatalog::PERMISOS;
    $modulosActivos  = $rol->modulos  ?? [];
    $permisosActivos = $rol->permisos ?? [];
    $grupos = collect($modulosCatalog)->groupBy(fn($m) => $m['grupo']);
@endphp

@section('content')
<div class="container-fluid py-4" style="max-width:960px;">

    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="/admin/roles" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h4 class="mb-0 fw-bold">{{ $rol->exists ? 'Editar rol: '.$rol->nombre : 'Nuevo rol' }}</h4>
            <small class="text-muted">Configura los módulos accesibles y las acciones permitidas</small>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger border-0 shadow-sm">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ $rol->exists ? '/admin/roles/'.$rol->id : '/admin/roles' }}">
        @csrf
        @if($rol->exists) @method('PUT') @endif

        {{-- ── Datos generales ──────────────────────────────────────────── --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom fw-semibold py-3">
                <i class="bi bi-tag me-2 text-primary"></i>Datos del rol
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror"
                               value="{{ old('nombre', $rol->nombre) }}" placeholder="Ej: Supervisor de ventas" required>
                        @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-7">
                        <label class="form-label fw-semibold">Descripción</label>
                        <input type="text" name="descripcion" class="form-control"
                               value="{{ old('descripcion', $rol->descripcion) }}" placeholder="Breve descripción del rol">
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Módulos accesibles ───────────────────────────────────────── --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between py-3">
                <span class="fw-semibold"><i class="bi bi-layout-sidebar me-2 text-primary"></i>Módulos accesibles (menú y rutas)</span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="toggleAll('modulos[]', true)">Todos</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="toggleAll('modulos[]', false)">Ninguno</button>
                </div>
            </div>
            <div class="card-body">
                @foreach($grupos as $grupo => $items)
                <div class="mb-3">
                    <p class="text-muted mb-2" style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;">{{ $grupo }}</p>
                    <div class="row g-2">
                        @foreach($items as $clave => $info)
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"
                                       name="modulos[]" value="{{ $clave }}"
                                       id="mod_{{ Str::slug($clave) }}"
                                       {{ in_array($clave, $modulosActivos) ? 'checked' : '' }}>
                                <label class="form-check-label small" for="mod_{{ Str::slug($clave) }}">
                                    {{ $info['label'] }}
                                </label>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @if(!$loop->last)<hr class="my-2">@endif
                @endforeach
            </div>
        </div>

        {{-- ── Permisos de acciones ─────────────────────────────────────── --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between py-3">
                <span class="fw-semibold"><i class="bi bi-shield-check me-2 text-primary"></i>Permisos de acciones</span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="toggleAll('permisos[]', true)">Todos</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="toggleAll('permisos[]', false)">Ninguno</button>
                </div>
            </div>
            <div class="card-body">
                @foreach($permisosCatalog as $grupo => $acciones)
                <div class="mb-3">
                    <p class="text-muted mb-2" style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;">{{ $grupo }}</p>
                    <div class="row g-2">
                        @foreach($acciones as $clave => $label)
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"
                                       name="permisos[]" value="{{ $clave }}"
                                       id="perm_{{ Str::slug($clave) }}"
                                       {{ in_array($clave, $permisosActivos) ? 'checked' : '' }}>
                                <label class="form-check-label small" for="perm_{{ Str::slug($clave) }}">
                                    {{ $label }}
                                </label>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @if(!$loop->last)<hr class="my-2">@endif
                @endforeach
            </div>
        </div>

        {{-- ── Acciones ─────────────────────────────────────────────────── --}}
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-floppy me-1"></i>{{ $rol->exists ? 'Guardar cambios' : 'Crear rol' }}
            </button>
            <a href="/admin/roles" class="btn btn-outline-secondary">Cancelar</a>
        </div>

    </form>
</div>

<script>
function toggleAll(name, check) {
    document.querySelectorAll(`input[name="${name}"]`).forEach(el => el.checked = check);
}
</script>
@endsection
