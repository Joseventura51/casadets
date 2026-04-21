@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">Caja</h3>
        <p class="text-muted mb-0">Resumen del día seleccionado.</p>
    </div>
    <form method="GET" class="d-flex gap-2 align-items-center">
        <label class="form-label mb-0">Día:</label>
        <input type="date" name="fecha" value="{{ $fecha }}" class="form-control form-control-sm" onchange="this.form.submit()">
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card kpi-card">
            <div class="text-muted small">Ventas del día</div>
            <h4 class="text-primary mb-0">S/ {{ number_format($totalVentas, 2) }}</h4>
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

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Ventas por método de pago</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <tbody>
                        @forelse($ventasPorMetodo as $metodo => $monto)
                        <tr>
                            <td>{{ ucfirst($metodo) }}</td>
                            <td class="text-end">S/ {{ number_format($monto, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="2" class="text-center text-muted py-3">Sin ventas</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Ventas por vendedor</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <tbody>
                        @forelse($ventasPorVendedor as $vendedor => $monto)
                        <tr>
                            <td>{{ $vendedor }}</td>
                            <td class="text-end">S/ {{ number_format($monto, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="2" class="text-center text-muted py-3">Sin ventas</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Ventas del día</span>
        <a href="/casadets/ventas/create" class="btn btn-sm btn-primary">+ Nueva venta</a>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Vendedor</th>
                    <th>Productos</th>
                    <th>Pago</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ventas as $v)
                <tr>
                    <td>{{ $v->vendedor->nombre ?? '—' }}</td>
                    <td>
                        @if($v->detalles->count() == 1)
                            {{ $v->detalles->first()->producto }}
                        @else
                            <span class="badge bg-info text-dark">{{ $v->detalles->count() }} productos</span>
                        @endif
                    </td>
                    <td>{{ ucfirst($v->metodo_pago) }}</td>
                    <td class="text-end">S/ {{ number_format($v->total, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-center text-muted py-3">Sin ventas en este día</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Movimientos del día</span>
        <div>
            <a href="/movimientos/create/ingreso" class="btn btn-sm btn-success">+ Ingreso</a>
            <a href="/movimientos/create/salida" class="btn btn-sm btn-danger">+ Salida</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Tipo</th>
                    <th>Categoría</th>
                    <th>Documento</th>
                    <th class="text-end">Monto</th>
                </tr>
            </thead>
            <tbody>
                @forelse($movimientos as $m)
                <tr>
                    <td>
                        <span class="badge {{ $m->tipo == 'ingreso' ? 'bg-success' : 'bg-danger' }}">{{ ucfirst($m->tipo) }}</span>
                    </td>
                    <td>{{ $m->categoria }}</td>
                    <td>{{ ucfirst($m->documento_tipo) }} {{ $m->documento_numero }}</td>
                    <td class="text-end">S/ {{ number_format($m->monto, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-center text-muted py-3">Sin movimientos</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
