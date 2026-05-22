@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-0">Dashboard</h3>
        <p class="text-muted mb-0">Resumen del mes en curso — fuente: movimientos</p>
    </div>
    <a href="/casadets/caja" class="btn btn-primary">
        <i class="bi bi-cash-coin me-1"></i> Ver Caja del día
    </a>
</div>

{{-- KPI Cards — sin doble conteo, movimientos es la fuente única --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card kpi-card">
            <div class="text-muted small">Ventas cobradas</div>
            <h4 class="text-primary mb-0">S/ {{ number_format($cobradoMes, 2) }}</h4>
            <small class="text-muted">Pagos de ventas del mes</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card">
            <div class="text-muted small">Otros ingresos</div>
            <h4 class="text-success mb-0">S/ {{ number_format($otrosIngresosMes, 2) }}</h4>
            <small class="text-muted">Ingresos no relacionados a ventas</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card">
            <div class="text-muted small">Salidas del mes</div>
            <h4 class="text-danger mb-0">S/ {{ number_format($salidasMes, 2) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card">
            <div class="text-muted small">Balance</div>
            <h4 class="mb-0 {{ $balanceMes >= 0 ? 'text-success' : 'text-danger' }}">
                S/ {{ number_format($balanceMes, 2) }}
            </h4>
            <small class="text-muted">Cobrado + otros − salidas</small>
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
                            <th>Estado</th>
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
                            <td>
                                @if($v->estado === 'pagado')
                                    <span class="badge bg-success">Pagado</span>
                                @elseif($v->estado === 'anulado')
                                    <span class="badge bg-danger">Anulado</span>
                                @else
                                    <span class="badge bg-warning text-dark">Pendiente</span>
                                @endif
                            </td>
                            <td class="text-end">S/ {{ number_format($v->total, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">Sin ventas hoy</td></tr>
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
                            <th>Origen</th>
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
                            <td>
                                {{ $m->categoria }}
                                @if($m->subtipo === 'pago_venta')
                                    <br><span class="badge bg-light text-secondary" style="font-size:.65rem;">pago venta</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ ($m->origen ?? 'manual') === 'auto' ? 'bg-info text-dark' : 'bg-secondary' }}" style="font-size:.65rem;">
                                    {{ ($m->origen ?? 'manual') === 'auto' ? 'auto' : 'manual' }}
                                </span>
                            </td>
                            <td class="text-end">S/ {{ number_format($m->monto, 2) }}</td>
                            <td>{{ \Carbon\Carbon::parse($m->fecha)->format('d/m/Y') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">Sin movimientos</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
