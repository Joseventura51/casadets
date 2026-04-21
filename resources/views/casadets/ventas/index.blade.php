@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">Ventas</h3>
        <p class="text-muted mb-0">Registro de ventas por vendedor.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/casadets/ventas/import" class="btn btn-outline-success">
            <i class="bi bi-file-earmark-spreadsheet"></i> Importar Excel
        </a>
        <a href="/casadets/ventas/create" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Nueva venta
        </a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Vendedor</label>
                <select name="vendedor_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($vendedores as $v)
                        <option value="{{ $v->id }}" {{ request('vendedor_id') == $v->id ? 'selected' : '' }}>{{ $v->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Desde</label>
                <input type="date" name="desde" value="{{ request('desde') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Hasta</label>
                <input type="date" name="hasta" value="{{ request('hasta') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary">Filtrar</button>
                <a href="/casadets/ventas" class="btn btn-sm btn-outline-secondary">Limpiar</a>
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
                    <th>Vendedor</th>
                    <th>Productos</th>
                    <th>Pago</th>
                    <th>Documento</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ventas as $v)
                <tr>
                    <td>{{ $v->fecha->format('d/m/Y') }}</td>
                    <td>{{ $v->vendedor->nombre ?? '—' }}</td>
                    <td>
                        @if($v->detalles->count() == 1)
                            {{ $v->detalles->first()->producto }}
                        @else
                            <span class="badge bg-info text-dark">{{ $v->detalles->count() }} productos</span>
                            <small class="text-muted d-block">{{ $v->detalles->pluck('producto')->take(2)->implode(', ') }}{{ $v->detalles->count() > 2 ? '…' : '' }}</small>
                        @endif
                    </td>
                    <td><span class="badge bg-light text-dark">{{ ucfirst($v->metodo_pago) }}</span></td>
                    <td>
                        @if($v->documento_tipo)
                            {{ ucfirst($v->documento_tipo) }} {{ $v->documento_numero }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-end fw-semibold">
                        S/ {{ number_format($v->total_cobrado, 2) }}
                        @if($v->ajuste != 0)
                            <br><small class="{{ $v->ajuste > 0 ? 'text-success' : 'text-danger' }}">
                                ({{ $v->ajuste > 0 ? '+' : '' }}{{ number_format($v->ajuste, 2) }})
                            </small>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="/casadets/ventas/{{ $v->id }}" class="btn btn-sm btn-outline-secondary">Ver</a>
                        <form action="/casadets/ventas/{{ $v->id }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar venta?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No hay ventas registradas.</td></tr>
                @endforelse
            </tbody>
            @if($ventas->count())
            <tfoot>
                <tr class="table-light">
                    <th colspan="5" class="text-end">Total cobrado</th>
                    <th class="text-end">S/ {{ number_format($ventas->sum(fn($v) => $v->total_cobrado), 2) }}</th>
                    <th></th>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
