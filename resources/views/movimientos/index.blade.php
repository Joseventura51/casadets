@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="h3 mb-1">Movimientos</h2>
        <p class="text-muted mb-0">Ledger de ingresos y salidas — fuente única de verdad financiera.</p>
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
            <div class="col-md-2">
                <label class="form-label small mb-1">Tipo</label>
                <select name="tipo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="ingreso" {{ request('tipo') === 'ingreso' ? 'selected' : '' }}>Ingreso</option>
                    <option value="salida"  {{ request('tipo') === 'salida'  ? 'selected' : '' }}>Salida</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Subtipo</label>
                <select name="subtipo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="pago_venta"       {{ request('subtipo') === 'pago_venta'       ? 'selected' : '' }}>Pago de venta</option>
                    <option value="saldo_favor_usado" {{ request('subtipo') === 'saldo_favor_usado' ? 'selected' : '' }}>Saldo a favor</option>
                    <option value="manual"           {{ request('subtipo') === 'manual'           ? 'selected' : '' }}>Manual</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Origen</label>
                <select name="origen" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="auto"   {{ request('origen') === 'auto'   ? 'selected' : '' }}>Automático</option>
                    <option value="manual" {{ request('origen') === 'manual' ? 'selected' : '' }}>Manual</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Desde</label>
                <input type="date" name="desde" value="{{ request('desde') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Hasta</label>
                <input type="date" name="hasta" value="{{ request('hasta') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary w-100">Filtrar</button>
                <a href="/movimientos" class="btn btn-sm btn-outline-secondary">✕</a>
            </div>
        </form>
    </div>
</div>

{{-- Totales de la página actual --}}
@if($movimientos->count())
<div class="row g-2 mb-3">
    <div class="col-md-3">
        <div class="card border-success border-opacity-25">
            <div class="card-body py-2">
                <div class="small text-muted">Ingresos (esta página)</div>
                <div class="fw-bold text-success">S/ {{ number_format($totales['ingresos'], 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-danger border-opacity-25">
            <div class="card-body py-2">
                <div class="small text-muted">Salidas (esta página)</div>
                <div class="fw-bold text-danger">S/ {{ number_format($totales['salidas'], 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card {{ $totales['balance'] >= 0 ? 'border-primary' : 'border-warning' }} border-opacity-25">
            <div class="card-body py-2">
                <div class="small text-muted">Balance (esta página)</div>
                <div class="fw-bold {{ $totales['balance'] >= 0 ? 'text-primary' : 'text-warning' }}">
                    S/ {{ number_format($totales['balance'], 2) }}
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-secondary border-opacity-25">
            <div class="card-body py-2">
                <div class="small text-muted">Registros</div>
                <div class="fw-bold text-secondary">{{ $movimientos->total() }} total</div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Tabla ledger con filas expandibles --}}
<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table mb-0 align-middle" id="tablaMovimientos">
            <thead class="table-light">
                <tr>
                    <th style="width:2rem;"></th>
                    <th>Tipo</th>
                    <th>Categoría</th>
                    <th>Cliente</th>
                    <th>Documento</th>
                    <th>Origen</th>
                    <th class="text-end">Monto</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
                @forelse($movimientos as $m)

                {{-- Fila principal: click para expandir --}}
                <tr class="mov-row"
                    data-bs-toggle="collapse"
                    data-bs-target="#det-{{ $m->id }}"
                    style="cursor:pointer;"
                    aria-expanded="false">
                    <td class="text-center text-muted">
                        <i class="bi bi-chevron-right toggle-icon small"></i>
                    </td>
                    <td>
                        <span class="badge {{ $m->tipo === 'ingreso' ? 'bg-success' : 'bg-danger' }}">
                            {{ ucfirst($m->tipo) }}
                        </span>
                        @if($m->subtipo === 'pago_venta')
                            <br><span class="badge bg-light text-secondary" style="font-size:.62rem;">pago venta</span>
                        @elseif($m->subtipo === 'saldo_favor_usado')
                            <br><span class="badge bg-light text-info" style="font-size:.62rem;">saldo favor</span>
                        @elseif($m->subtipo === 'manual')
                            <br><span class="badge bg-light text-secondary" style="font-size:.62rem;">manual</span>
                        @endif
                    </td>
                    <td>{{ $m->categoria }}</td>
                    <td class="small text-muted">{{ $m->cliente->nombre ?? '—' }}</td>
                    <td class="small text-muted">
                        @if($m->documento_tipo)
                            {{ ucfirst($m->documento_tipo) }}{{ $m->documento_numero ? ' '.$m->documento_numero : '' }}
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ ($m->origen ?? 'manual') === 'auto' ? 'bg-info text-dark' : 'bg-secondary' }}" style="font-size:.65rem;">
                            {{ ($m->origen ?? 'manual') === 'auto' ? 'automático' : 'manual' }}
                        </span>
                    </td>
                    <td class="text-end fw-semibold {{ $m->tipo === 'ingreso' ? 'text-success' : 'text-danger' }}">
                        {{ $m->tipo === 'ingreso' ? '+' : '−' }} S/ {{ number_format($m->monto, 2) }}
                    </td>
                    <td class="small text-muted">{{ $m->fecha->format('d/m/Y') }}</td>
                </tr>

                {{-- Fila de detalle expandible --}}
                <tr class="collapse-row">
                    <td colspan="8" class="p-0 border-0">
                        <div class="collapse" id="det-{{ $m->id }}">
                            <div class="px-4 py-3 bg-light border-bottom">
                                <div class="row g-3">

                                    {{-- Columna 1: Información básica --}}
                                    <div class="col-md-3">
                                        <h6 class="text-uppercase text-muted small mb-2">Información</h6>
                                        <dl class="row small mb-0">
                                            <dt class="col-5 text-muted fw-normal">Tipo</dt>
                                            <dd class="col-7 mb-1">{{ ucfirst($m->tipo) }}</dd>

                                            @if($m->subtipo)
                                            <dt class="col-5 text-muted fw-normal">Subtipo</dt>
                                            <dd class="col-7 mb-1">{{ str_replace('_', ' ', $m->subtipo) }}</dd>
                                            @endif

                                            <dt class="col-5 text-muted fw-normal">Origen</dt>
                                            <dd class="col-7 mb-1">
                                                <span class="badge {{ ($m->origen ?? 'manual') === 'auto' ? 'bg-info text-dark' : 'bg-secondary' }}" style="font-size:.65rem;">
                                                    {{ ($m->origen ?? 'manual') === 'auto' ? 'automático' : 'manual' }}
                                                </span>
                                            </dd>

                                            <dt class="col-5 text-muted fw-normal">Fecha</dt>
                                            <dd class="col-7 mb-1">{{ $m->fecha->format('d/m/Y') }}</dd>

                                            <dt class="col-5 text-muted fw-normal">Monto</dt>
                                            <dd class="col-7 mb-1 fw-semibold">S/ {{ number_format($m->monto, 2) }}</dd>

                                            @if($m->referencia_id)
                                            <dt class="col-5 text-muted fw-normal">Ref.</dt>
                                            <dd class="col-7 mb-1 text-muted">
                                                {{ ucfirst($m->referencia_tipo ?? '') }} #{{ $m->referencia_id }}
                                            </dd>
                                            @endif

                                            @if($m->observaciones)
                                            <dt class="col-5 text-muted fw-normal">Nota</dt>
                                            <dd class="col-7 mb-0 text-muted">{{ $m->observaciones }}</dd>
                                            @endif
                                        </dl>
                                    </div>

                                    {{-- Columna 2: Cliente --}}
                                    @if($m->cliente)
                                    <div class="col-md-2">
                                        <h6 class="text-uppercase text-muted small mb-2">Cliente</h6>
                                        <p class="small mb-0 fw-semibold">{{ $m->cliente->nombre }}</p>
                                        @if($m->cliente->documento)
                                            <p class="small text-muted mb-0">{{ $m->cliente->documento }}</p>
                                        @endif
                                    </div>
                                    @endif

                                    {{-- Columna 3: Métodos de pago (solo si viene de CobranzaService) --}}
                                    @if($m->pago && $m->referencia_tipo === 'pago' && $m->pago->metodos->count())
                                    <div class="col-md-3">
                                        <h6 class="text-uppercase text-muted small mb-2">Métodos de pago</h6>
                                        <table class="table table-xs table-sm mb-0" style="font-size:.85rem;">
                                            <tbody>
                                                @foreach($m->pago->metodos as $pm)
                                                <tr>
                                                    <td class="py-1 ps-0 border-0">
                                                        <span class="badge bg-success bg-opacity-10 text-success">
                                                            {{ ucfirst($pm->metodo) }}
                                                        </span>
                                                    </td>
                                                    <td class="py-1 pe-0 border-0 text-end fw-semibold">
                                                        S/ {{ number_format($pm->monto, 2) }}
                                                    </td>
                                                </tr>
                                                @endforeach
                                                @if($m->pago->metodos->count() > 1)
                                                <tr class="border-top">
                                                    <td class="py-1 ps-0 text-muted small">Total</td>
                                                    <td class="py-1 pe-0 text-end fw-bold">
                                                        S/ {{ number_format($m->pago->metodos->sum('monto'), 2) }}
                                                    </td>
                                                </tr>
                                                @endif
                                            </tbody>
                                        </table>
                                    </div>
                                    @endif

                                    {{-- Columna 4: Ventas aplicadas (solo para pago_venta) --}}
                                    @if($m->pago && $m->referencia_tipo === 'pago' && $m->pago->detalles->count())
                                    <div class="col-md-{{ $m->cliente ? '4' : '4' }}">
                                        <h6 class="text-uppercase text-muted small mb-2">Ventas aplicadas</h6>
                                        @foreach($m->pago->detalles as $dpf)
                                        @php
                                            $venta = $dpf->venta;
                                            $docLabel = trim(ucfirst($venta?->documento_tipo ?? 'Venta') . ' ' . ($venta?->documento_numero ?? '#'.($dpf->venta_id)));
                                            $saldoPendiente = max(0, (float)($venta?->total ?? 0) - (float)($venta?->pagado ?? 0));
                                        @endphp
                                        <div class="border rounded p-2 mb-1" style="font-size:.85rem;">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <span class="fw-semibold">{{ $docLabel }}</span>
                                                <span class="text-success fw-bold ms-2">
                                                    S/ {{ number_format($dpf->monto_aplicado, 2) }}
                                                </span>
                                            </div>
                                            <div class="text-muted mt-1">
                                                Total venta:
                                                <strong>S/ {{ number_format($venta?->total ?? 0, 2) }}</strong>
                                                &nbsp;·&nbsp;
                                                Saldo pendiente:
                                                <strong class="{{ $saldoPendiente > 0 ? 'text-warning' : 'text-success' }}">
                                                    S/ {{ number_format($saldoPendiente, 2) }}
                                                </strong>
                                            </div>
                                            @if($venta)
                                            <div class="mt-1">
                                                <a href="/casadets/ventas/{{ $venta->id }}" class="small text-muted">
                                                    Ver venta →
                                                </a>
                                            </div>
                                            @endif
                                        </div>
                                        @endforeach
                                    </div>
                                    @endif

                                </div>{{-- /row --}}
                            </div>
                        </div>
                    </td>
                </tr>

                @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-5">
                        No hay movimientos registrados.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($movimientos->hasPages())
<div class="d-flex justify-content-center mt-3">
    {{ $movimientos->links() }}
</div>
@endif

<script>
// Rotar ícono chevron al expandir/colapsar
document.querySelectorAll('.mov-row').forEach(function(row) {
    const targetId = row.getAttribute('data-bs-target');
    const collapseEl = document.querySelector(targetId);
    if (!collapseEl) return;

    collapseEl.addEventListener('show.bs.collapse', function() {
        row.querySelector('.toggle-icon')?.classList.replace('bi-chevron-right', 'bi-chevron-down');
        row.classList.add('table-active');
    });
    collapseEl.addEventListener('hide.bs.collapse', function() {
        row.querySelector('.toggle-icon')?.classList.replace('bi-chevron-down', 'bi-chevron-right');
        row.classList.remove('table-active');
    });
});
</script>
@endsection
