@extends('layouts.app')

@section('title', 'Roles y permisos')

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-0 fw-bold">Roles y permisos</h4>
            <small class="text-muted">Gestiona los roles del sistema y sus permisos de acceso</small>
        </div>
        <a href="/admin/roles/create" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Nuevo rol
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-3">
        @foreach($roles as $rol)
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between mb-3">
                        <div>
                            <h5 class="fw-bold mb-1">{{ $rol->nombre }}</h5>
                            <p class="text-muted small mb-0">{{ $rol->descripcion ?: 'Sin descripción' }}</p>
                        </div>
                        <span class="badge bg-light text-dark border">
                            <i class="bi bi-people me-1"></i>{{ $rol->users_count }} usuario{{ $rol->users_count !== 1 ? 's' : '' }}
                        </span>
                    </div>

                    @if(!empty($rol->modulos))
                    <div class="mb-2">
                        <p class="text-muted mb-1" style="font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Módulos ({{ count($rol->modulos) }})</p>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach(array_slice($rol->modulos, 0, 6) as $modulo)
                                <span class="badge bg-primary bg-opacity-10 text-primary" style="font-size:.7rem;">{{ $modulo }}</span>
                            @endforeach
                            @if(count($rol->modulos) > 6)
                                <span class="badge bg-secondary bg-opacity-10 text-secondary" style="font-size:.7rem;">+{{ count($rol->modulos) - 6 }} más</span>
                            @endif
                        </div>
                    </div>
                    @endif

                    @if(!empty($rol->permisos))
                    <div class="mb-3">
                        <p class="text-muted mb-1" style="font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Acciones ({{ count($rol->permisos) }})</p>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach(array_slice($rol->permisos, 0, 5) as $permiso)
                                <span class="badge bg-success bg-opacity-10 text-success" style="font-size:.7rem;">{{ $permiso }}</span>
                            @endforeach
                            @if(count($rol->permisos) > 5)
                                <span class="badge bg-secondary bg-opacity-10 text-secondary" style="font-size:.7rem;">+{{ count($rol->permisos) - 5 }} más</span>
                            @endif
                        </div>
                    </div>
                    @endif

                </div>
                <div class="card-footer bg-white border-top d-flex gap-2">
                    <a href="/admin/roles/{{ $rol->id }}/edit" class="btn btn-sm btn-outline-primary flex-fill">
                        <i class="bi bi-pencil me-1"></i>Editar
                    </a>
                    @if($rol->users_count === 0)
                    <form method="POST" action="/admin/roles/{{ $rol->id }}" onsubmit="return confirm('¿Eliminar el rol «{{ $rol->nombre }}»?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                    @else
                    <button class="btn btn-sm btn-outline-secondary" disabled title="Tiene usuarios asignados">
                        <i class="bi bi-trash"></i>
                    </button>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>

</div>
@endsection
