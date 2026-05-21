@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">Caja</h3>
        <p class="text-muted mb-0">
            {{ $esRango ? 'Período: '.$desde.' al '.$hasta : 'Día: '.$desde }}
        </p>
    </div>
    <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
        <div class="d-flex align-items-center gap-1">
            <label class="form-label mb-0 small text-muted">Desde</label>
            <input type="date" name="desde" value="{{ $desde }}"
                   class="form-control form-control-sm" style="width:145px;">
        </div>
        <div class="d-flex align-items-center gap-1">
            <label class="form-label mb-0 small text-muted">Hasta</label>
            <input type="date" name="hasta" value="{{ $hasta }}"
                   class="form-control form-control-sm" style="width:145px;">
        </div>
        <button type="submit" class="btn btn-sm btn-outline-primary">Filtrar</button>
        <a href="/casadets/caja" class="btn btn-sm btn-outline-secondary">Hoy</a>
    </form>
</div>

{{-- KPI Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card kpi-card">
            <div class="text-muted small">Ventas cobradas</div>
            <h4 class="text-primary mb-0">S/ {{ number_format($totalVentas, 2) }}</h4>
            @if($totalAjustes != 0)
                <small class="{{ $totalAjustes > 0 ? 'text-success' : 'text-danger' }}">
                    Ajustes: {{ $totalAjustes > 0 ? '+' : '' }}S/ {{ number_format($totalAjustes, 2) }}
                </small>
            @endif
            @if($ventasPendientes->count())
                <small class="text-warning d-block">
                    <i class="bi bi-clock me-1"></i>{{ $ventasPendientes->count() }} pendiente(s)
                    — S/ {{ number_format($ventasPendientes->sum('total'), 2) }}
                </small>
            @endif
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card">
            <div class="text-muted small">Otros ingresos</div>
            <h4 class="text-success mb-0">S/ {{ number_format($totalIngresos, 2) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card">
            <div class="text-muted small">Salidas</div>
            <h4 class="text-danger mb-0">S/ {{ number_format($totalSalidas, 2) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card">
            <div class="text-muted small">Saldo de caja</div>
            <h4 class="mb-0 {{ $balance >= 0 ? 'text-success' : 'text-danger' }}">
                S/ {{ number_format($balance, 2) }}
            </h4>
        </div>
    </div>
</div>

{{-- Resúmenes --}}
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Por método de pago</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <tbody>
                        @forelse($ventasPorMetodo as $metodo => $monto)
                        <tr>
                            <td>{{ ucfirst($metodo) }}</td>
                            <td class="text-end fw-semibold">S/ {{ number_format($monto, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="2" class="text-center text-muted py-3">Sin ventas cobradas</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Por vendedor</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <tbody>
                        @forelse($ventasPorVendedor as $vendedor => $monto)
                        <tr>
                            <td>{{ $vendedor }}</td>
                            <td class="text-end fw-semibold">S/ {{ number_format($monto, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="2" class="text-center text-muted py-3">Sin ventas cobradas</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Tabla de ventas --}}
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>
            Ventas del período
            <span class="badge bg-primary ms-1">{{ $ventas->count() }}</span>
            @if($ventasPendientes->count())
                <span class="badge bg-warning text-dark ms-1">{{ $ventasPendientes->count() }} pendiente(s)</span>
            @endif
        </span>
        <a href="/casadets/ventas/create" class="btn btn-sm btn-primary">+ Nueva venta</a>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Doc.</th>
                    <th>Vendedor</th>
                    <th>Productos</th>
                    <th>Estado</th>
                    <th>Pago</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ventas as $v)
                @php
                    $cobrada = $v->estado === 'pagado' || !empty($v->metodo_pago);
                @endphp
                <tr class="{{ !$cobrada ? 'table-warning' : '' }}">
                    <td class="text-muted small">{{ $v->fecha->format('d/m/Y') }}</td>
                    <td class="small text-muted">{{ $v->documento_numero ?? '—' }}</td>
                    <td>{{ $v->vendedor->nombre ?? '—' }}</td>
                    <td>
                        @if($v->detalles->count() == 1)
                            <span class="small">{{ $v->detalles->first()->producto }}</span>
                        @else
                            <span class="badge bg-info text-dark">{{ $v->detalles->count() }} productos</span>
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
                    <td>
                        @if(!empty($v->metodo_pago))
                            @foreach(array_filter(array_map('trim', explode(',', $v->metodo_pago))) as $m)
                                <span class="badge bg-success" style="font-size:.7rem;">{{ ucfirst($m) }}</span>
                            @endforeach
                        @else
                            <a href="/casadets/ventas/{{ $v->id }}/pago" class="btn btn-xs btn-outline-warning py-0 px-1" style="font-size:.75rem;">
                                <i class="bi bi-cash me-1"></i>Cobrar
                            </a>
                        @endif
                    </td>
                    <td class="text-end fw-semibold">
                        S/ {{ number_format($cobrada ? $v->total_cobrado : $v->total, 2) }}
                        @if($cobrada && $v->ajuste != 0)
                            <br><small class="{{ $v->ajuste > 0 ? 'text-success' : 'text-danger' }}">
                                ({{ $v->ajuste > 0 ? '+' : '' }}{{ number_format($v->ajuste, 2) }})
                            </small>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-3">Sin ventas en este período</td></tr>
                @endforelse
            </tbody>
            @if($ventasCobradas->count())
            <tfoot class="table-light">
                <tr>
                    <th colspan="6" class="text-end small text-muted">Total cobrado</th>
                    <th class="text-end text-primary">S/ {{ number_format($totalVentas, 2) }}</th>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

{{-- Movimientos --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Movimientos <span class="badge bg-secondary ms-1">{{ $movimientos->count() }}</span></span>
        <div>
            <a href="/movimientos/create/ingreso" class="btn btn-sm btn-success">+ Ingreso</a>
            <a href="/movimientos/create/salida" class="btn btn-sm btn-danger">+ Salida</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Categoría</th>
                    <th>Documento</th>
                    <th class="text-end">Monto</th>
                </tr>
            </thead>
            <tbody>
                @forelse($movimientos as $m)
                <tr>
                    <td class="text-muted small">{{ $m->fecha->format('d/m/Y') }}</td>
                    <td>
                        <span class="badge {{ $m->tipo == 'ingreso' ? 'bg-success' : 'bg-danger' }}">
                            {{ ucfirst($m->tipo) }}
                        </span>
                    </td>
                    <td>{{ $m->categoria }}</td>
                    <td class="small text-muted">{{ ucfirst($m->documento_tipo) }} {{ $m->documento_numero }}</td>
                    <td class="text-end fw-semibold">S/ {{ number_format($m->monto, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center text-muted py-3">Sin movimientos en este período</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
