@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">Productos</h3>
        <p class="text-muted mb-0">
            {{ $totalActivos }} activo(s)
            @if($stockBajoCount > 0)
                &nbsp;·&nbsp;
                <span class="text-danger fw-semibold">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>{{ $stockBajoCount }} con stock bajo
                </span>
            @endif
        </p>
    </div>
    <a href="/casadets/productos/create" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nuevo producto
    </a>
</div>

{{-- Filtros --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end" data-dynamic-filter>
            <div class="col-md-3">
                <label class="form-label small mb-1">Buscar</label>
                <input type="text" name="q" value="{{ request('q') }}"
                       class="form-control form-control-sm" placeholder="Nombre o código…">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Empresa</label>
                <select name="empresa" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <option value="casadets" {{ request('empresa') === 'casadets' ? 'selected' : '' }}>CASADETS</option>
                    <option value="zendy"    {{ request('empresa') === 'zendy'    ? 'selected' : '' }}>ZENDY</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Estado</label>
                <select name="activo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="1" {{ request('activo') === '1' ? 'selected' : '' }}>Activos</option>
                    <option value="0" {{ request('activo') === '0' ? 'selected' : '' }}>Inactivos</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Stock</label>
                <select name="stock" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="bajo" {{ request('stock') === 'bajo' ? 'selected' : '' }}>Stock bajo (≤ 0)</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary w-100">Filtrar</button>
                <a href="/casadets/productos" class="btn btn-sm btn-outline-secondary">✕</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Empresa</th>
                    <th class="text-end">Precio venta</th>
                    <th class="text-end">Precio costo</th>
                    <th class="text-end">Margen</th>
                    <th class="text-end">Stock</th>
                    <th class="text-center">Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($productos as $p)
                <tr class="{{ !$p->activo ? 'text-muted' : '' }}">
                    <td class="small text-muted">{{ $p->codigo ?? '—' }}</td>
                    <td>
                        <a href="/casadets/productos/{{ $p->id }}" class="fw-semibold text-decoration-none">
                            {{ $p->nombre }}
                        </a>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border" style="font-size:.72rem;">
                            {{ strtoupper($p->empresa ?? 'casadets') }}
                        </span>
                    </td>
                    <td class="text-end">S/ {{ number_format($p->precio_venta, 2) }}</td>
                    <td class="text-end text-muted">S/ {{ number_format($p->precio_costo, 2) }}</td>
                    <td class="text-end">
                        @if($p->margen !== null)
                            <span class="badge {{ $p->margen >= 20 ? 'bg-success' : ($p->margen >= 0 ? 'bg-warning text-dark' : 'bg-danger') }}">
                                {{ number_format($p->margen, 1) }}%
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-end fw-semibold">
                        @if($p->stock_bajo)
                            <span class="text-danger">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>{{ number_format($p->stock_actual, 2) }}
                            </span>
                        @else
                            {{ number_format($p->stock_actual, 2) }}
                        @endif
                    </td>
                    <td class="text-center">
                        @if($p->activo)
                            <span class="badge bg-success">Activo</span>
                        @else
                            <span class="badge bg-secondary">Inactivo</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="/casadets/productos/{{ $p->id }}/edit" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size:.75rem;">
                            <i class="bi bi-pencil"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">
                        No se encontraron productos.
                        <a href="/casadets/productos/create" class="ms-2">Crear el primero →</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($productos->hasPages())
<div class="d-flex justify-content-center mt-3">
    {{ $productos->links() }}
</div>
@endif
@endsection
