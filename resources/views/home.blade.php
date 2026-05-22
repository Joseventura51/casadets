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

{{-- Alertas operativas --}}
@if($pendientesVencidas > 0 || $stockBajoCount > 0)
<div class="row g-2 mb-4">
    @if($pendientesVencidas > 0)
    <div class="col-md-6">
        <div class="alert alert-warning d-flex align-items-center gap-2 mb-0 py-2">
            <i class="bi bi-clock-history fs-5"></i>
            <span>
                <strong>{{ $pendientesVencidas }}</strong> venta(s) pendiente(s) de días anteriores sin cobrar.
                <a href="/casadets/pendientes" class="alert-link ms-1">Ver →</a>
            </span>
        </div>
    </div>
    @endif
    @if($stockBajoCount > 0)
    <div class="col-md-6">
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-0 py-2">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <span>
                <strong>{{ $stockBajoCount }}</strong> producto(s) con stock en cero o negativo.
                <a href="/casadets/productos?stock=bajo" class="alert-link ms-1">Ver →</a>
            </span>
        </div>
    </div>
    @endif
</div>
@endif

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
            @if($comprasMes > 0)
                <small class="text-muted">Inc. S/ {{ number_format($comprasMes, 2) }} en compras</small>
            @endif
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

{{-- Segunda fila KPIs --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card kpi-card {{ $deudaPendiente > 0 ? 'border-warning' : '' }}">
            <div class="text-muted small">Deuda pendiente total</div>
            <h4 class="mb-0 {{ $deudaPendiente > 0 ? 'text-warning' : 'text-muted' }}">
                S/ {{ number_format($deudaPendiente, 2) }}
            </h4>
            <small class="text-muted">Ventas sin cobrar (todas las fechas)</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card kpi-card {{ $saldosDisponiblesMes > 0 ? 'border-info' : '' }}">
            <div class="text-muted small">Saldos a favor disponibles</div>
            <h4 class="mb-0 {{ $saldosDisponiblesMes > 0 ? 'text-info' : 'text-muted' }}">
                S/ {{ number_format($saldosDisponiblesMes, 2) }}
            </h4>
            <small class="text-muted">Excedentes de clientes por aplicar</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card kpi-card">
            <div class="text-muted small">Compras del mes</div>
            <h4 class="mb-0 text-danger">S/ {{ number_format($comprasMes, 2) }}</h4>
            <small class="text-muted">Egresos registrados por compras</small>
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
                            <th>Estado</th>
                            <th class="text-end">Monto</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ultimosMovimientos as $m)
                        <tr class="{{ $m->estado === 'anulado' ? 'text-decoration-line-through text-muted' : '' }}">
                            <td>
                                @if($m->tipo === 'ingreso')
                                    <span class="badge bg-success">Ingreso</span>
                                @elseif($m->tipo === 'salida')
                                    <span class="badge bg-danger">Salida</span>
                                @else
                                    <span class="badge bg-secondary">{{ ucfirst($m->tipo) }}</span>
                                @endif
                            </td>
                            <td>
                                {{ $m->categoria }}
                                @if($m->subtipo === 'pago_venta')
                                    <br><span class="badge bg-light text-secondary" style="font-size:.65rem;">pago venta</span>
                                @endif
                            </td>
                            <td>
                                @if($m->estado === 'anulado')
                                    <span class="badge bg-secondary" style="font-size:.65rem;">Anulado</span>
                                @endif
                            </td>
                            <td class="text-end">S/ {{ number_format($m->monto, 2) }}</td>
                            <td class="small text-muted">{{ \Carbon\Carbon::parse($m->fecha)->format('d/m/Y') }}</td>
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
