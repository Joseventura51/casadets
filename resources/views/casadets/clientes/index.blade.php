@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">Clientes</h3>
        <p class="text-muted mb-0">Registro de clientes de CASADETS.</p>
    </div>
    <a href="/casadets/clientes/create" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Nuevo cliente
    </a>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small mb-1">Buscar por nombre o documento</label>
                <input type="text" name="buscar" value="{{ request('buscar') }}"
                       class="form-control form-control-sm" placeholder="Nombre, DNI o RUC…">
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary">Buscar</button>
                <a href="/casadets/clientes" class="btn btn-sm btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th>Doc. (DNI/RUC)</th>
                    <th>Teléfono</th>
                    <th>Dirección</th>
                    <th class="text-center">Ventas</th>
                    <th class="text-center">Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($clientes as $c)
                <tr class="{{ !$c->activo ? 'text-muted' : '' }}">
                    <td class="fw-semibold">{{ $c->nombre }}</td>
                    <td>{{ $c->documento ?? '—' }}</td>
                    <td>{{ $c->telefono ?? '—' }}</td>
                    <td>{{ $c->direccion ?? '—' }}</td>
                    <td class="text-center">
                        @if($c->ventas_count)
                            <a href="/casadets/ventas?cliente_id={{ $c->id }}" class="badge bg-primary text-decoration-none">
                                {{ $c->ventas_count }}
                            </a>
                        @else
                            <span class="text-muted">0</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($c->activo)
                            <span class="badge bg-success">Activo</span>
                        @else
                            <span class="badge bg-secondary">Inactivo</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="/casadets/clientes/{{ $c->id }}/edit" class="btn btn-sm btn-outline-primary">Editar</a>
                        <form action="/casadets/clientes/{{ $c->id }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar cliente?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No hay clientes registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($clientes->hasPages())
<div class="d-flex justify-content-center mt-3">
    {{ $clientes->links() }}
</div>
@endif
@endsection
