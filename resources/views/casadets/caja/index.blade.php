@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">Caja</h3>
        <p class="text-muted mb-0">
            {{ $esRango ? 'Período: '.$desde.' al '.$hasta : 'Día: '.$desde }}
            &nbsp;·&nbsp;
            <span class="badge bg-light text-dark border" style="font-size:.75rem;">{{ strtoupper($empresa) }}</span>
        </p>
    </div>
    <form method="GET" class="d-flex gap-2 align-items-center flex-wrap" data-dynamic-filter data-default-today>
        <select name="empresa" class="form-select form-select-sm" style="width:130px;">
            <option value="casadets" {{ $empresa === 'casadets' ? 'selected' : '' }}>CASADETS</option>
            <option value="zendy"    {{ $empresa === 'zendy'    ? 'selected' : '' }}>ZENDY</option>
        </select>
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

{{-- ── Apertura / Cierre de caja (solo visible cuando se ve el día de hoy) ── --}}
@if(!$esRango && $desde === $hoy)
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body py-3">
        @if(!$sesionHoy)
            {{-- Sin apertura registrada --}}
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-secondary" style="font-size:.8rem;">
                        <i class="bi bi-lock me-1"></i>Caja sin apertura
                    </span>
                    <span class="text-muted small">Registra el monto inicial de efectivo en caja para hoy.</span>
                </div>
                <button class="btn btn-success btn-sm ms-auto" data-bs-toggle="collapse" data-bs-target="#formApertura">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Abrir caja
                </button>
            </div>
            <div class="collapse mt-3" id="formApertura">
                <form action="/casadets/caja/apertura" method="POST" class="d-flex gap-2 align-items-end flex-wrap">
                    @csrf
                    <input type="hidden" name="empresa" value="{{ $empresa }}">
                    <div>
                        <label class="form-label small mb-1">Monto de apertura (S/)</label>
                        <input type="number" name="monto_apertura" step="0.01" min="0" value="0"
                               class="form-control form-control-sm" style="width:160px;" required>
                    </div>
                    <div style="flex:1;min-width:200px;">
                        <label class="form-label small mb-1">Observaciones</label>
                        <input type="text" name="observaciones" class="form-control form-control-sm"
                               placeholder="Opcional">
                    </div>
                    <button class="btn btn-success btn-sm">
                        <i class="bi bi-check-lg me-1"></i>Confirmar apertura
                    </button>
                </form>
            </div>

        @elseif($sesionHoy->estaAbierta())
            {{-- Caja abierta --}}
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <span class="badge bg-success" style="font-size:.8rem;">
                    <i class="bi bi-door-open me-1"></i>Caja abierta
                </span>
                <span class="small text-muted">
                    Apertura: <strong class="text-dark">S/ {{ number_format($sesionHoy->monto_apertura, 2) }}</strong>
                    @if($sesionHoy->observaciones)
                        &nbsp;·&nbsp;{{ $sesionHoy->observaciones }}
                    @endif
                </span>
                <button class="btn btn-danger btn-sm ms-auto" data-bs-toggle="collapse" data-bs-target="#formCierre">
                    <i class="bi bi-box-arrow-right me-1"></i>Cerrar caja
                </button>
            </div>
            <div class="collapse mt-3" id="formCierre">
                <form action="/casadets/caja/cierre" method="POST" class="d-flex gap-2 align-items-end flex-wrap">
                    @csrf
                    <input type="hidden" name="empresa" value="{{ $empresa }}">
                    <div>
                        <label class="form-label small mb-1">Monto de cierre contado (S/)</label>
                        <input type="number" name="monto_cierre" step="0.01" min="0"
                               value="{{ number_format($efectivoEnCaja, 2, '.', '') }}"
                               class="form-control form-control-sm" style="width:160px;" required>
                    </div>
                    <div class="small text-muted pt-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Esperado en caja: <strong>S/ {{ number_format($efectivoEnCaja, 2) }}</strong>
                    </div>
                    <button class="btn btn-danger btn-sm">
                        <i class="bi bi-check-lg me-1"></i>Confirmar cierre
                    </button>
                </form>
            </div>

        @else
            {{-- Caja cerrada --}}
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <span class="badge bg-dark" style="font-size:.8rem;">
                    <i class="bi bi-lock-fill me-1"></i>Caja cerrada
                </span>
                <span class="small text-muted">
                    Apertura: <strong>S/ {{ number_format($sesionHoy->monto_apertura, 2) }}</strong>
                    &nbsp;·&nbsp;
                    Cierre contado: <strong>S/ {{ number_format($sesionHoy->monto_cierre, 2) }}</strong>
                </span>
                @php
                    $diferencia = round($sesionHoy->monto_cierre - $efectivoEnCaja, 2);
                @endphp
                @if($diferencia != 0)
                    <span class="badge {{ $diferencia > 0 ? 'bg-success' : 'bg-danger' }} ms-1" style="font-size:.78rem;">
                        {{ $diferencia > 0 ? '+' : '' }}S/ {{ number_format($diferencia, 2) }}
                        {{ $diferencia > 0 ? 'sobrante' : 'faltante' }}
                    </span>
                @else
                    <span class="badge bg-success" style="font-size:.78rem;"><i class="bi bi-check2 me-1"></i>Cuadrado</span>
                @endif
            </div>
        @endif
    </div>
</div>
@endif

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- KPI Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card kpi-card">
            <div class="text-muted small">Ventas cobradas</div>
            <h4 class="text-primary mb-0">S/ {{ number_format($totalVentasCobradas, 2) }}</h4>
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
            <h4 class="text-success mb-0">S/ {{ number_format($totalOtrosIngresos, 2) }}</h4>
            <small class="text-muted">Sin pagos de ventas</small>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card kpi-card">
            <div class="text-muted small">Compras</div>
            <h4 class="text-warning mb-0">S/ {{ number_format($totalCompras, 2) }}</h4>
            <small class="text-muted">Egresos por compras</small>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card kpi-card">
            <div class="text-muted small">Otras salidas</div>
            <h4 class="text-danger mb-0">S/ {{ number_format($totalSalidas - $totalCompras, 2) }}</h4>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card kpi-card">
            <div class="text-muted small">Saldo de caja</div>
            <h4 class="mb-0 {{ $balance >= 0 ? 'text-success' : 'text-danger' }}">
                S/ {{ number_format($balance, 2) }}
            </h4>
            <small class="text-muted">Cobrado + otros − salidas</small>
        </div>
    </div>
</div>

{{-- Efectivo en caja --}}
<div class="card mb-4" style="border-left: 4px solid #f59e0b;">
    <div class="card-body py-3 d-flex align-items-center gap-4 flex-wrap">
        <div>
            <div class="text-muted small mb-1"><i class="bi bi-cash-coin me-1"></i>Efectivo actual en caja</div>
            <h3 class="mb-0 {{ $efectivoEnCaja >= 0 ? 'text-warning-emphasis fw-bold' : 'text-danger fw-bold' }}">
                S/ {{ number_format($efectivoEnCaja, 2) }}
            </h3>
        </div>
        <div class="vr d-none d-md-block"></div>
        <div class="small text-muted d-flex flex-column gap-1">
            @if($sesionHoy && $sesionHoy->monto_apertura > 0)
                <span><i class="bi bi-plus-circle text-success me-1"></i>
                    Apertura: <strong>S/ {{ number_format($sesionHoy->monto_apertura, 2) }}</strong>
                </span>
            @endif
            <span><i class="bi bi-plus-circle text-primary me-1"></i>
                Cobrado en efectivo: <strong>S/ {{ number_format($ventasPorMetodo->get('efectivo', 0), 2) }}</strong>
            </span>
            @if($comprasEnEfectivo > 0)
                <span><i class="bi bi-dash-circle text-danger me-1"></i>
                    Compras en efectivo: <strong>S/ {{ number_format($comprasEnEfectivo, 2) }}</strong>
                </span>
            @endif
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
                            <td>
                                @if($metodo === 'efectivo')
                                    <i class="bi bi-cash me-1 text-warning"></i>
                                @elseif($metodo === 'transferencia')
                                    <i class="bi bi-bank me-1 text-primary"></i>
                                @elseif(in_array($metodo, ['yape','plin']))
                                    <i class="bi bi-phone me-1 text-success"></i>
                                @endif
                                {{ ucfirst($metodo) }}
                            </td>
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
                @php $cobrada = $v->estado === 'pagado'; @endphp
                <tr class="{{ !$cobrada && $v->estado !== 'anulado' ? 'table-warning' : '' }}
                            {{ $v->estado === 'anulado' ? 'table-secondary text-muted' : '' }}">
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
                        @elseif($v->estado !== 'anulado')
                            <a href="/casadets/ventas/{{ $v->id }}/pago"
                               class="btn btn-xs btn-outline-warning py-0 px-1" style="font-size:.75rem;">
                                <i class="bi bi-cash me-1"></i>Cobrar
                            </a>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td class="text-end fw-semibold">
                        S/ {{ number_format($cobrada ? $v->total_cobrado : $v->total, 2) }}
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
                    <th class="text-end text-primary">S/ {{ number_format($totalVentasCobradas, 2) }}</th>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

{{-- Movimientos del período --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>
            Movimientos
            <span class="badge bg-secondary ms-1">{{ $movimientos->count() }}</span>
            @if($movimientos->where('estado','anulado')->count())
                <span class="badge bg-light text-muted ms-1" style="font-size:.7rem;">
                    {{ $movimientos->where('estado','anulado')->count() }} anulado(s)
                </span>
            @endif
        </span>
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
                    <th>Origen</th>
                    <th>Documento</th>
                    <th>Estado</th>
                    <th class="text-end">Monto</th>
                </tr>
            </thead>
            <tbody>
                @forelse($movimientos as $m)
                <tr class="{{ $m->estado === 'anulado' ? 'text-muted' : '' }}">
                    <td class="text-muted small">{{ $m->fecha->format('d/m/Y') }}</td>
                    <td>
                        @if($m->tipo === 'ingreso')
                            <span class="badge bg-success {{ $m->estado === 'anulado' ? 'opacity-50' : '' }}">Ingreso</span>
                        @elseif($m->tipo === 'salida')
                            <span class="badge bg-danger {{ $m->estado === 'anulado' ? 'opacity-50' : '' }}">Salida</span>
                        @else
                            <span class="badge bg-secondary opacity-50">{{ ucfirst($m->tipo) }}</span>
                        @endif
                        @if($m->subtipo === 'pago_venta')
                            <br><span class="badge bg-light text-secondary" style="font-size:.6rem;">venta</span>
                        @elseif($m->subtipo === 'compra')
                            <br><span class="badge bg-light text-secondary" style="font-size:.6rem;">compra</span>
                        @elseif($m->subtipo === 'saldo_favor_usado')
                            <br><span class="badge bg-light text-info" style="font-size:.6rem;">saldo favor</span>
                        @endif
                    </td>
                    <td>{{ $m->categoria }}</td>
                    <td>
                        <span class="badge {{ ($m->origen ?? 'manual') === 'auto' ? 'bg-info text-dark' : 'bg-secondary' }}" style="font-size:.65rem;">
                            {{ ($m->origen ?? 'manual') === 'auto' ? 'auto' : 'manual' }}
                        </span>
                    </td>
                    <td class="small text-muted">{{ ucfirst($m->documento_tipo ?? '') }} {{ $m->documento_numero ?? '' }}</td>
                    <td>
                        @if($m->estado === 'anulado')
                            <span class="badge bg-secondary" style="font-size:.65rem;">Anulado</span>
                        @endif
                    </td>
                    <td class="text-end fw-semibold {{ $m->estado === 'anulado' ? 'text-decoration-line-through text-muted' : '' }}">
                        S/ {{ number_format($m->monto, 2) }}
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-3">Sin movimientos en este período</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
