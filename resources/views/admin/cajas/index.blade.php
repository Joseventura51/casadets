@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0"><i class="bi bi-cash-register me-2"></i>Cajas</h3>
        <p class="text-muted small mb-0">Gestión de cajas físicas por empresa</p>
    </div>
    <a href="/admin/cajas/create" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Nueva caja
    </a>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2">
    {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Empresa</th>
                    <th class="text-center">Series</th>
                    <th class="text-center">Estado hoy</th>
                    <th class="text-center">Activa</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($cajas as $caja)
                <tr>
                    <td><code class="fw-semibold">{{ $caja->codigo }}</code></td>
                    <td>{{ $caja->nombre }}</td>
                    <td><span class="badge bg-secondary">{{ strtoupper($caja->empresa) }}</span></td>
                    <td class="text-center">
                        <span class="badge bg-info text-dark">{{ $caja->series_count }}</span>
                    </td>
                    <td class="text-center">
                        @if($caja->estaAbiertaHoy())
                            <span class="badge bg-success"><i class="bi bi-door-open me-1"></i>Abierta</span>
                        @else
                            <span class="badge bg-secondary"><i class="bi bi-lock me-1"></i>Cerrada</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <form action="/admin/cajas/{{ $caja->id }}/toggle" method="POST">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-xs btn-outline-{{ $caja->activa ? 'success' : 'secondary' }} py-0 px-2" style="font-size:.75rem;">
                                {{ $caja->activa ? 'Activa' : 'Inactiva' }}
                            </button>
                        </form>
                    </td>
                    <td class="text-end">
                        <a href="/admin/cajas/{{ $caja->id }}/edit" class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size:.75rem;">
                            <i class="bi bi-pencil"></i> Editar
                        </a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Sin cajas registradas. <a href="/admin/cajas/create">Crear la primera</a></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
