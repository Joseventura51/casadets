@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">Compras</h3>
        <p class="text-muted mb-0">Compras a proveedores. Pueden vincularse a ventas.</p>
    </div>
    <a href="/casadets/compras/create" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Nueva compra
    </a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1">Empresa / Proveedor</label>
                <input type="text" name="empresa" value="{{ request('empresa') }}" class="form-control form-control-sm" placeholder="Buscar...">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Desde</label>
                <input type="date" name="desde" value="{{ request('desde') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Hasta</label>
                <input type="date" name="hasta" value="{{ request('hasta') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary">Filtrar</button>
                <a href="/casadets/compras" class="btn btn-sm btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Empresa</th>
                    <th>Documento</th>
                    <th>Producto</th>
                    <th class="text-end">Cant.</th>
                    <th class="text-end">M. unit.</th>
                    <th class="text-end">Total</th>
                    <th class="text-center">Ventas</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($compras as $c)
                <tr>
                    <td>{{ $c->fecha->format('d/m/Y') }}</td>
                    <td>{{ $c->empresa }}</td>
                    <td>{{ $c->documento_tipo ? ucfirst($c->documento_tipo) : '' }} {{ $c->documento_numero }}</td>
                    <td>{{ $c->producto ?? '—' }}</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format($c->cantidad, 2), '0'), '.') }}</td>
                    <td class="text-end">S/ {{ number_format($c->monto_unitario, 2) }}</td>
                    <td class="text-end fw-semibold">S/ {{ number_format($c->monto_total, 2) }}</td>
                    <td class="text-center">
                        @if($c->ventas->count())
                            <span class="badge bg-info text-dark">{{ $c->ventas->count() }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="/casadets/compras/{{ $c->id }}" class="btn btn-sm btn-outline-secondary">Ver</a>
                        <a href="/casadets/compras/{{ $c->id }}/edit" class="btn btn-sm btn-outline-primary">Editar</a>
                        <form action="/casadets/compras/{{ $c->id }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar compra?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No hay compras registradas.</td></tr>
                @endforelse
            </tbody>
            @if($compras->count())
            <tfoot>
                <tr class="table-light">
                    <th colspan="6" class="text-end">Total comprado</th>
                    <th class="text-end">S/ {{ number_format($compras->sum('monto_total'), 2) }}</th>
                    <th colspan="2"></th>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
