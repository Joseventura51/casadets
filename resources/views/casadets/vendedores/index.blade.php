@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">Vendedores</h3>
        <p class="text-muted mb-0">Equipo de ventas registrado.</p>
    </div>
    <a href="/casadets/vendedores/create" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Nuevo vendedor
    </a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th>Teléfono</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($vendedores as $v)
                <tr>
                    <td>{{ $v->nombre }}</td>
                    <td>{{ $v->telefono ?? '—' }}</td>
                    <td>
                        @if($v->activo)
                            <span class="badge bg-success">Activo</span>
                        @else
                            <span class="badge bg-secondary">Inactivo</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="/casadets/vendedores/{{ $v->id }}/edit" class="btn btn-sm btn-outline-primary">Editar</a>
                        <form action="/casadets/vendedores/{{ $v->id }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar vendedor?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-center text-muted py-4">No hay vendedores registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
