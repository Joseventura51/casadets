@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h3 class="mb-0">Almacenes</h3><p class="text-muted mb-0 small">Ubicaciones disponibles para el inventario.</p></div>
    <a href="/admin/almacenes/create" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nuevo almacén</a>
</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light"><tr><th>Código</th><th>Nombre</th><th>Empresa</th><th class="text-center">Productos con saldo</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            @foreach($almacenes as $almacen)
                <tr class="{{ !$almacen->activo ? 'text-muted' : '' }}">
                    <td class="fw-semibold">{{ $almacen->codigo }}</td><td>{{ $almacen->nombre }}</td><td>{{ $almacen->empresa }}</td>
                    <td class="text-center">{{ $almacen->stocks_count }}</td>
                    <td><span class="badge bg-{{ $almacen->activo ? 'success' : 'secondary' }}">{{ $almacen->activo ? 'Activo' : 'Inactivo' }}</span></td>
                    <td class="text-end">
                        <a href="/admin/almacenes/{{ $almacen->id }}/edit" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <form action="/admin/almacenes/{{ $almacen->id }}/toggle" method="POST" class="d-inline">@csrf @method('PATCH')
                            <button class="btn btn-sm btn-outline-{{ $almacen->activo ? 'secondary' : 'success' }}">{{ $almacen->activo ? 'Desactivar' : 'Activar' }}</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection