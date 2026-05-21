@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h3 mb-1">Movimientos</h2>
            <p class="text-muted mb-0">Ingresos y salidas asociados a factura o proforma.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="/movimientos/create/ingreso" class="btn btn-success">+ Ingreso</a>
            <a href="/movimientos/create/salida" class="btn btn-danger">+ Salida</a>
        </div>
    </div>

    {{-- Filtros server-side --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">Tipo</label>
                    <select name="tipo" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="ingreso" {{ request('tipo') === 'ingreso' ? 'selected' : '' }}>Ingreso</option>
                        <option value="salida"  {{ request('tipo') === 'salida'  ? 'selected' : '' }}>Salida</option>
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
                    <a href="/movimientos" class="btn btn-sm btn-outline-secondary">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Tipo</th>
                        <th>Categoría</th>
                        <th>Documento</th>
                        <th>Número</th>
                        <th class="text-end">Monto</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($movimientos as $m)
                    <tr>
                        <td>
                            @if($m->tipo === 'ingreso')
                                <span class="badge bg-success">Ingreso</span>
                            @else
                                <span class="badge bg-danger">Salida</span>
                            @endif
                        </td>
                        <td>{{ $m->categoria }}</td>
                        <td>{{ ucfirst($m->documento_tipo) }}</td>
                        <td>{{ $m->documento_numero }}</td>
                        <td class="text-end fw-semibold">S/ {{ number_format($m->monto, 2) }}</td>
                        <td>{{ \Carbon\Carbon::parse($m->fecha)->format('d/m/Y') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No hay movimientos registrados.</td>
                    </tr>
                    @endforelse
                </tbody>
                @if($movimientos->count())
                <tfoot class="table-light">
                    <tr>
                        <th colspan="4" class="text-end">Total (esta página)</th>
                        <th class="text-end">S/ {{ number_format($movimientos->getCollection()->sum('monto'), 2) }}</th>
                        <th></th>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    @if($movimientos->hasPages())
    <div class="d-flex justify-content-center mt-3">
        {{ $movimientos->links() }}
    </div>
    @endif
</div>
@endsection
