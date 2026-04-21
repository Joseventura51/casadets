@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-0">Dashboard</h3>
        <p class="text-muted mb-0">Resumen del mes en curso</p>
    </div>
    <a href="/casadets/caja" class="btn btn-primary">
        <i class="bi bi-cash-coin me-1"></i> Ver Caja del día
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card kpi-card">
            <div class="text-muted small">Ventas del mes</div>
            <h4 class="text-primary mb-0">S/ {{ number_format($totalVentasMes, 2) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card">
            <div class="text-muted small">Ingresos del mes</div>
            <h4 class="text-success mb-0">S/ {{ number_format($totalIngresosMes, 2) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card">
            <div class="text-muted small">Salidas del mes</div>
            <h4 class="text-danger mb-0">S/ {{ number_format($totalSalidasMes, 2) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card">
            <div class="text-muted small">Balance</div>
            <h4 class="mb-0 {{ $balanceMes >= 0 ? 'text-success' : 'text-danger' }}">
                S/ {{ number_format($balanceMes, 2) }}
            </h4>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-cart3 me-1"></i> Ventas de hoy</span>
                <a href="/casadets/ventas" class="small">Ver todas</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Vendedor</th>
                            <th>Productos</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ventasHoy as $v)
                        <tr>
                            <td>{{ $v->vendedor->nombre ?? '—' }}</td>
                            <td>
                                @if($v->detalles->count() == 1)
                                    {{ $v->detalles->first()->producto }}
                                @else
                                    {{ $v->detalles->count() }} productos
                                @endif
                            </td>
                            <td class="text-end">S/ {{ number_format($v->total, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center text-muted py-3">Sin ventas hoy</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-arrow-left-right me-1"></i> Últimos movimientos</span>
                <a href="/movimientos" class="small">Ver todos</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tipo</th>
                            <th>Categoría</th>
                            <th class="text-end">Monto</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ultimosMovimientos as $m)
                        <tr>
                            <td>
                                <span class="badge {{ $m->tipo == 'ingreso' ? 'bg-success' : 'bg-danger' }}">
                                    {{ ucfirst($m->tipo) }}
                                </span>
                            </td>
                            <td>{{ $m->categoria }}</td>
                            <td class="text-end">S/ {{ number_format($m->monto, 2) }}</td>
                            <td>{{ \Carbon\Carbon::parse($m->fecha)->format('d/m/Y') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">Sin movimientos</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
