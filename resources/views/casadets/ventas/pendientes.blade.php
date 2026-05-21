@extends('layouts.app')

@section('content')
<style>
.dias-badge { font-size:.72rem; padding:.2rem .55rem; border-radius:20px; font-weight:600; }
.select-estado { font-size:.78rem; padding:.2rem .5rem; border-radius:20px; font-weight:600; cursor:pointer; border:1.5px solid; appearance:none; -webkit-appearance:none; text-align:center; min-width:110px; }
.select-estado.est-pendiente { border-color:#adb5bd; background:#f8f9fa; color:#495057; }
.select-estado.est-pagado    { border-color:#198754; background:#d1e7dd; color:#155724; }
.select-estado.est-anulado   { border-color:#dc3545; background:#f8d7da; color:#842029; }
.fila-amarillo { background: #fff9e6 !important; }
.fila-rojo     { background: #fff0f0 !important; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h3 class="mb-0"><i class="bi bi-clock-history me-2 text-danger"></i>Ventas Pendientes</h3>
        <p class="text-muted mb-0 small">Ventas de días anteriores que aún no han sido cobradas.</p>
    </div>
    <a href="/casadets/ventas" class="btn btn-outline-secondary btn-sm">← Ver todas las ventas</a>
</div>

{{-- Filtros --}}
<form method="GET" action="/casadets/pendientes" class="card p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-3">
            <label class="form-label small mb-1">Vendedor</label>
            <select name="vendedor_id" class="form-select form-select-sm">
                <option value="">Todos</option>
                @foreach($vendedores as $v)
                    <option value="{{ $v->id }}" {{ request('vendedor_id') == $v->id ? 'selected' : '' }}>{{ $v->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Desde</label>
            <input type="date" name="desde" value="{{ request('desde') }}" class="form-control form-select-sm">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Hasta</label>
            <input type="date" name="hasta" value="{{ request('hasta') }}" class="form-control form-select-sm">
        </div>
        <div class="col-6 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm w-100">Filtrar</button>
            <a href="/casadets/pendientes" class="btn btn-outline-secondary btn-sm w-100">Limpiar</a>
        </div>
    </div>
</form>

{{-- KPIs --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card kpi-card">
            <small class="text-muted">Total pendientes</small>
            <h4 class="mb-0 text-danger fw-bold">{{ $ventas->count() }}</h4>
            <small class="text-muted">ventas sin cobrar</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card">
            <small class="text-muted">Monto total</small>
            <h4 class="mb-0 text-danger fw-bold">S/ {{ number_format($ventas->sum('total'), 2) }}</h4>
            <small class="text-muted">por cobrar</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card">
            <small class="text-muted">Más antigua</small>
            <h4 class="mb-0">{{ $ventas->isNotEmpty() ? $ventas->first()->fecha->format('d/m/Y') : '—' }}</h4>
            @if($ventas->isNotEmpty())
                <small class="text-danger">hace {{ $ventas->first()->fecha->diffInDays(today()) }} días</small>
            @endif
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card">
            <small class="text-muted">Vendedor con más pendientes</small>
            @php $topVendedor = $ventas->groupBy('vendedor_id')->sortByDesc(fn($g) => $g->count())->first(); @endphp
            <h4 class="mb-0">{{ $topVendedor ? $topVendedor->first()->vendedor->nombre ?? '—' : '—' }}</h4>
            @if($topVendedor)<small class="text-muted">{{ $topVendedor->count() }} venta(s)</small>@endif
        </div>
    </div>
</div>

{{-- Tabla --}}
<div class="card">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Estado</th>
                    <th>Días</th>
                    <th>Fecha venta</th>
                    <th>Vencimiento</th>
                    <th>Vendedor</th>
                    <th>Cliente</th>
                    <th>Productos</th>
                    <th>Documento</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ventas as $v)
                @php
                    $dias       = $v->fecha->diffInDays(today());
                    $vencimiento = $v->fecha->copy()->addDays(30);
                    $filaClass  = $dias >= 20 ? 'fila-rojo' : ($dias >= 10 ? 'fila-amarillo' : '');
                    $diasColor  = $dias >= 20 ? 'danger'    : ($dias >= 10 ? 'warning'        : 'secondary');
                    $diasText   = $diasColor  === 'warning'  ? 'text-dark' : 'text-white';
                @endphp
                <tr class="{{ $filaClass }}">
                    <td>
                        <form action="/casadets/ventas/{{ $v->id }}/estado" method="POST">
                            @csrf
                            <select name="estado" class="select-estado est-{{ $v->estado ?? 'pendiente' }}"
                                onchange="this.form.submit()">
                                <option value="pendiente" selected>⏳ Pendiente</option>
                                <option value="pagado">✔ Pagado</option>
                                <option value="anulado">✕ Anulado</option>
                            </select>
                        </form>
                    </td>
                    <td>
                        @if($dias >= 10)
                            <span class="dias-badge bg-{{ $diasColor }} {{ $diasText }}">{{ $dias }}d</span>
                        @else
                            <span class="text-muted small">{{ $dias }}d</span>
                        @endif
                    </td>
                    <td>{{ $v->fecha->format('d/m/Y') }}</td>
                    <td>
                        <span class="{{ $vencimiento->isPast() ? 'text-danger fw-semibold' : 'text-muted' }}">
                            {{ $vencimiento->format('d/m/Y') }}
                            @if($vencimiento->isPast())
                                <i class="bi bi-exclamation-circle ms-1" title="Vencida"></i>
                            @endif
                        </span>
                    </td>
                    <td>{{ $v->vendedor->nombre ?? '—' }}</td>
                    <td>
                        @if($v->cliente)
                            <span class="fw-semibold">{{ $v->cliente->nombre }}</span>
                            @if($v->cliente->documento)<br><small class="text-muted">{{ $v->cliente->documento }}</small>@endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($v->detalles->count() == 1)
                            {{ $v->detalles->first()->producto }}
                        @else
                            <span class="badge bg-info text-dark">{{ $v->detalles->count() }} productos</span>
                        @endif
                    </td>
                    <td>{{ $v->documento_tipo ? ucfirst($v->documento_tipo).' '.$v->documento_numero : '—' }}</td>
                    <td class="text-end fw-semibold">S/ {{ number_format($v->total, 2) }}</td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="/casadets/ventas/{{ $v->id }}" class="btn btn-outline-secondary btn-sm">Ver</a>
                            <a href="/casadets/ventas/{{ $v->id }}/pago" class="btn btn-success btn-sm">
                                <i class="bi bi-cash-stack"></i> Cobrar
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="text-center text-muted py-4">
                        <i class="bi bi-check-circle text-success fs-3 d-block mb-2"></i>
                        No hay ventas pendientes de días anteriores.
                    </td>
                </tr>
                @endforelse
            </tbody>
            @if($ventas->count())
            <tfoot class="table-light">
                <tr>
                    <th colspan="8" class="text-end">Total por cobrar</th>
                    <th class="text-end text-danger">S/ {{ number_format($ventas->sum('total'), 2) }}</th>
                    <th></th>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

<script>
document.querySelectorAll('.select-estado').forEach(sel => {
    sel.addEventListener('change', function() {
        this.className = 'select-estado est-' + this.value;
    });
});
</script>
@endsection
