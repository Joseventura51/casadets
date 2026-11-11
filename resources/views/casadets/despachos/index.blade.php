@extends('layouts.app')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h3 class="mb-1">Despachos</h3>
        <div class="text-muted small">Entregas físicas registradas por venta. No modifican el stock.</div>
    </div>
</div>

<form method="GET" action="/casadets/despachos" class="card card-body mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-lg-4">
            <label class="form-label">Comprobante, cliente o vendedor</label>
            <input class="form-control" name="q" value="{{ request('q') }}" placeholder="Buscar">
        </div>
        <div class="col-sm-4 col-lg-2">
            <label class="form-label">Desde</label>
            <input class="form-control" type="date" name="desde" value="{{ request('desde') }}">
        </div>
        <div class="col-sm-4 col-lg-2">
            <label class="form-label">Hasta</label>
            <input class="form-control" type="date" name="hasta" value="{{ request('hasta') }}">
        </div>
        <div class="col-sm-4 col-lg-2">
            <label class="form-label">Estado de despacho</label>
            <select class="form-select" name="estado">
                <option value="">Pendientes</option>
                <option value="pendiente" @selected($estado === 'pendiente')>Pendiente</option>
                <option value="parcial" @selected($estado === 'parcial')>Parcial</option>
                <option value="completo" @selected($estado === 'completo')>Completo</option>
            </select>
        </div>
        <div class="col-lg-2 d-flex gap-2">
            <button class="btn btn-primary flex-grow-1" type="submit"><i class="bi bi-search me-1"></i>Buscar</button>
            <a class="btn btn-outline-secondary" href="/casadets/despachos" title="Limpiar filtros"><i class="bi bi-x-lg"></i></a>
        </div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Venta</th>
                    <th>Cliente</th>
                    <th>Vendedor</th>
                    <th>Fecha</th>
                    <th>Pago</th>
                    <th>Despacho</th>
                    <th class="text-end">Importe</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($ventas as $venta)
                    @php
                        $pendiente = collect($venta->cantidad_pendiente_despacho)->sum(
                            fn ($linea) => (float) $linea['cantidad_pendiente']
                        );
                        $badge = match($venta->estado_despacho_calculado) {
                            'completo' => 'success',
                            'parcial' => 'warning text-dark',
                            default => 'secondary',
                        };
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $venta->documento_numero ?: 'Venta #' . $venta->id }}</strong>
                            <div class="text-muted small">{{ ucfirst(str_replace('_', ' ', $venta->documento_tipo ?? 'vale')) }}</div>
                        </td>
                        <td>{{ $venta->cliente?->nombre ?? 'Cliente varios' }}</td>
                        <td>{{ $venta->vendedor?->nombre ?? '—' }}</td>
                        <td>{{ $venta->fecha?->format('d/m/Y') ?? '—' }}</td>
                        <td>
                            @php($pagoBadge = ['pagado' => 'success', 'parcial' => 'warning text-dark', 'pendiente' => 'secondary'][$venta->estado] ?? 'secondary')
                            <span class="badge bg-{{ $pagoBadge }}">{{ ucfirst($venta->estado ?? 'pendiente') }}</span>
                        </td>
                        <td>
                            <span class="badge bg-{{ $badge }}">{{ ucfirst($venta->estado_despacho_calculado) }}</span>
                            <div class="small text-muted">{{ number_format($pendiente, 2) }} unidades pendientes</div>
                        </td>
                        <td class="text-end">S/ {{ number_format((float) $venta->total_a_cobrar, 2) }}</td>
                        <td class="text-end">
                            <a href="/casadets/despachos/{{ $venta->id }}/registrar" class="btn btn-sm btn-outline-primary">
                                Ver / despachar
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-5">No hay ventas que coincidan con la búsqueda.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($ventas->hasPages())
        <div class="card-footer">{{ $ventas->links() }}</div>
    @endif
</div>
@endsection
