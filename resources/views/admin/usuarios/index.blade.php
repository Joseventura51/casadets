@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">Usuarios</h3>
        <p class="text-muted mb-0">Gestión de acceso y roles del sistema</p>
    </div>
    <a href="/admin/usuarios/create" class="btn btn-primary">
        <i class="bi bi-person-plus me-1"></i> Nuevo usuario
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Nombre</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Vendedores asociados</th>
                    <th class="text-center">Estado</th>
                    <th class="text-end pe-3">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($usuarios as $u)
                <tr>
                    <td class="ps-3 fw-semibold">{{ $u->name }}</td>
                    <td class="text-muted small">{{ $u->email }}</td>
                    <td>
                        @if($u->rol)
                        <span class="badge rounded-pill
                            @if($u->rol->nombre === 'Administrador') bg-danger
                            @elseif($u->rol->nombre === 'Supervisor') bg-warning text-dark
                            @elseif($u->rol->nombre === 'Cajero') bg-info text-dark
                            @else bg-secondary
                            @endif">
                            {{ $u->rol->nombre }}
                        </span>
                        @else
                        <span class="text-muted small">Sin rol</span>
                        @endif
                    </td>
                    <td class="small text-muted">
                        @if($u->vendedores->isNotEmpty())
                            {{ $u->vendedores->pluck('nombre')->implode(', ') }}
                        @else
                            <span class="text-secondary">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($u->activo)
                            <span class="badge bg-success">Activo</span>
                        @else
                            <span class="badge bg-secondary">Inactivo</span>
                        @endif
                    </td>
                    <td class="text-end pe-3">
                        <a href="/admin/usuarios/{{ $u->id }}/edit" class="btn btn-sm btn-outline-primary me-1">
                            <i class="bi bi-pencil"></i>
                        </a>
                        @if($u->id !== auth()->id())
                        <form method="POST" action="/admin/usuarios/{{ $u->id }}/toggle" class="d-inline">
                            @csrf
                            @method('PATCH')
                            <button type="submit"
                                class="btn btn-sm {{ $u->activo ? 'btn-outline-warning' : 'btn-outline-success' }}"
                                title="{{ $u->activo ? 'Desactivar' : 'Activar' }}">
                                <i class="bi bi-{{ $u->activo ? 'slash-circle' : 'check-circle' }}"></i>
                            </button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">No hay usuarios registrados.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
