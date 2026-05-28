@extends('layouts.app')

@section('content')
<style>
.dias-badge { font-size:.72rem; padding:.2rem .55rem; border-radius:20px; font-weight:600; }
.select-estado { font-size:.78rem; padding:.2rem .5rem; border-radius:20px; font-weight:600; cursor:pointer; border:1.5px solid; appearance:none; -webkit-appearance:none; text-align:center; min-width:110px; }
.select-estado.est-pendiente { border-color:#adb5bd; background:#f8f9fa; color:#495057; }
.select-estado.est-parcial   { border-color:#fd7e14; background:#fff3cd; color:#7c4a00; }
.select-estado.est-pagado    { border-color:#198754; background:#d1e7dd; color:#155724; }
.select-estado.est-anulado   { border-color:#dc3545; background:#f8d7da; color:#842029; }
.fila-amarillo { background: #fff9e6 !important; }
.fila-rojo     { background: #fff0f0 !important; }
.fila-oculta   { display: none !important; }
.filter-input { font-size:.78rem; border-radius:5px; border:1px solid #ced4da; padding:.2rem .4rem; background:#fff; width:100%; min-width:0; transition:border-color .15s,box-shadow .15s; }
.filter-input:focus { outline:none; border-color:#86b7fe; box-shadow:0 0 0 2px rgba(13,110,253,.15); }
.thead-filter td { background:#fff3cd; padding:.3rem .5rem; border-bottom:2px solid #ffe69c; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h3 class="mb-0"><i class="bi bi-clock-history me-2 text-danger"></i>Ventas Pendientes</h3>
        <p class="text-muted mb-0 small">Ventas de días anteriores que aún no han sido cobradas.</p>
    </div>
    <a href="/casadets/ventas" class="btn btn-outline-secondary btn-sm">← Ver todas las ventas</a>
</div>

{{-- ── Filtros server-side ─────────────────────────────────── --}}
<form method="GET" action="/casadets/pendientes" class="card p-3 mb-3" data-dynamic-filter>
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-4">
            <label class="form-label small mb-1">Vendedor</label>
            <select name="vendedor_id" class="form-select form-select-sm">
                <option value="">Todos</option>
                @foreach($vendedores as $vnd)
                    <option value="{{ $vnd->id }}" {{ request('vendedor_id') == $vnd->id ? 'selected' : '' }}>
                        {{ $vnd->nombre }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm w-100">Filtrar</button>
            <a href="/casadets/pendientes" class="btn btn-outline-secondary btn-sm w-100">Limpiar</a>
        </div>
    </div>
</form>

{{-- ── KPIs ────────────────────────────────────────────────── --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-6">
        <div class="card p-3">
            <small class="text-muted">Total pendientes</small>
            <h4 class="mb-0 text-danger fw-bold" id="kpiConteo">{{ $ventas->count() }}</h4>
            <small class="text-muted">ventas pendientes / parciales</small>
        </div>
    </div>
    <div class="col-6 col-md-6">
        <div class="card p-3">
            <small class="text-muted">Monto total</small>
            <h4 class="mb-0 text-danger fw-bold" id="kpiMonto">S/ {{ number_format($ventas->sum('total'), 2) }}</h4>
            <small class="text-muted">por cobrar</small>
        </div>
    </div>
</div>

{{-- ── Tabla con filtros en vivo ───────────────────────────── --}}
<div class="card">
    <div class="table-responsive">
        <table class="table mb-0 align-middle" id="tablaPendientes">
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
                {{-- Fila de búsqueda en vivo --}}
                <tr class="thead-filter">
                    <td colspan="4"></td>
                    <td><input type="text" id="fVendedor"  class="filter-input" placeholder="Vendedor…"></td>
                    <td><input type="text" id="fCliente"   class="filter-input" placeholder="Nombre o RUC…"></td>
                    <td><input type="text" id="fProducto"  class="filter-input" placeholder="Producto…"></td>
                    <td><input type="text" id="fDocumento" class="filter-input" placeholder="F001-001…"></td>
                    <td><input type="text" id="fTotal"     class="filter-input text-end" placeholder="166.32"></td>
                    <td class="text-end">
                        <button id="btnLimpiar" type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Limpiar búsqueda">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </td>
                </tr>
            </thead>
            <tbody>
                @forelse($ventas as $v)
                @php
                    $dias        = $v->fecha->diffInDays(today());
                    $vencimiento = $v->fecha->copy()->addDays(30);
                    $filaClass   = $dias >= 20 ? 'fila-rojo' : ($dias >= 10 ? 'fila-amarillo' : '');
                    $diasColor   = $dias >= 20 ? 'danger'    : ($dias >= 10 ? 'warning'        : 'secondary');
                    $diasText    = $diasColor  === 'warning'  ? 'text-dark' : 'text-white';
                    $productosStr = $v->detalles->map(fn($d) => $d->producto)->join(', ');
                    $clienteTxt  = ($v->cliente->nombre ?? '') . ' ' . ($v->cliente->documento ?? '');
                    $docTxt      = ($v->documento_tipo ?? '') . ' ' . ($v->documento_numero ?? '');
                @endphp
                <tr class="fila-pendiente {{ $filaClass }}"
                    data-vendedor="{{ strtolower($v->vendedor->nombre ?? '') }}"
                    data-cliente="{{ strtolower($clienteTxt) }}"
                    data-producto="{{ strtolower($productosStr) }}"
                    data-documento="{{ strtolower($docTxt) }}"
                    data-total="{{ number_format($v->total, 2) }}">
                    <td>
                        @php $estV = $v->estado ?? 'pendiente'; @endphp
                        <form action="/casadets/ventas/{{ $v->id }}/estado" method="POST">
                            @csrf
                            <select name="estado" class="select-estado est-{{ $estV }}"
                                onchange="this.form.submit()">
                                <option value="pendiente" {{ $estV==='pendiente'?'selected':'' }}>⏳ Pendiente</option>
                                <option value="parcial"   {{ $estV==='parcial'  ?'selected':'' }}>◑ Parcial</option>
                                <option value="pagado"    {{ $estV==='pagado'   ?'selected':'' }}>✔ Pagado</option>
                                <option value="anulado"   {{ $estV==='anulado'  ?'selected':'' }}>✕ Anulado</option>
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
                    <td style="max-width:200px;">
                        @if($v->detalles->count() === 1)
                            <span class="small">{{ $v->detalles->first()->producto }}</span>
                        @elseif($v->detalles->count() > 1)
                            <span class="small">{{ $v->detalles->first()->producto }}</span>
                            <span class="badge bg-info text-dark ms-1">+{{ $v->detalles->count() - 1 }}</span>
                        @endif
                    </td>
                    <td class="small">{{ $v->documento_tipo ? ucfirst($v->documento_tipo).' '.$v->documento_numero : '—' }}</td>
                    <td class="text-end fw-semibold">
                        S/ {{ number_format($v->total, 2) }}
                        @if((float)$v->pagado > 0)
                            <br><small class="text-success">Cobrado: S/ {{ number_format($v->pagado, 2) }}</small>
                            @php $saldoPend = max(0, (float)$v->total - (float)$v->pagado); @endphp
                            @if($saldoPend > 0)
                                <br><small class="text-danger fw-semibold">Falta: S/ {{ number_format($saldoPend, 2) }}</small>
                            @endif
                        @endif
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end flex-wrap">
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
                    <th colspan="8" class="text-end">Total por cobrar (visibles)</th>
                    <th class="text-end text-danger" id="totalVisible">S/ {{ number_format($ventas->sum('total'), 2) }}</th>
                    <th></th>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

<div id="sinResultados" class="text-center text-muted py-4" style="display:none;">
    <i class="bi bi-search me-1"></i> Sin resultados para la búsqueda aplicada.
</div>

<script>
document.querySelectorAll('.select-estado').forEach(sel => {
    sel.addEventListener('change', function() { this.className = 'select-estado est-' + this.value; });
});

// ── Búsqueda en vivo sobre las filas cargadas ─────────────────
const filtros = {
    vendedor:  document.getElementById('fVendedor'),
    cliente:   document.getElementById('fCliente'),
    producto:  document.getElementById('fProducto'),
    documento: document.getElementById('fDocumento'),
    total:     document.getElementById('fTotal'),
};

const filas       = document.querySelectorAll('.fila-pendiente');
const kpiConteo   = document.getElementById('kpiConteo');
const kpiMonto    = document.getElementById('kpiMonto');
const totalVis    = document.getElementById('totalVisible');
const sinResult   = document.getElementById('sinResultados');

function normalizar(s) {
    return (s || '').toLowerCase()
        .replace(/[áàä]/g,'a').replace(/[éèë]/g,'e')
        .replace(/[íìï]/g,'i').replace(/[óòö]/g,'o')
        .replace(/[úùü]/g,'u').replace(/ñ/g,'n');
}

function aplicarFiltros() {
    const vals = {};
    for (const [k, el] of Object.entries(filtros)) vals[k] = normalizar(el.value.trim());
    const hayFiltro = Object.values(vals).some(v => v !== '');

    let conteo = 0;
    let monto  = 0;

    filas.forEach(tr => {
        const d = {
            vendedor:  normalizar(tr.dataset.vendedor),
            cliente:   normalizar(tr.dataset.cliente),
            producto:  normalizar(tr.dataset.producto),
            documento: normalizar(tr.dataset.documento),
            total:     normalizar(tr.dataset.total),
        };
        const ok =
            (!vals.vendedor  || d.vendedor.includes(vals.vendedor))   &&
            (!vals.cliente   || d.cliente.includes(vals.cliente))     &&
            (!vals.producto  || d.producto.includes(vals.producto))   &&
            (!vals.documento || d.documento.includes(vals.documento)) &&
            (!vals.total     || d.total.includes(vals.total));

        tr.classList.toggle('fila-oculta', !ok);
        if (ok) {
            conteo++;
            monto += parseFloat(tr.dataset.total.replace(/,/g, '')) || 0;
        }
    });

    if (kpiConteo) kpiConteo.textContent = conteo;
    if (kpiMonto)  kpiMonto.textContent  = 'S/ ' + monto.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    if (totalVis)  totalVis.textContent  = 'S/ ' + monto.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    if (sinResult) sinResult.style.display = (hayFiltro && conteo === 0) ? '' : 'none';
}

Object.values(filtros).forEach(el => el.addEventListener('input', aplicarFiltros));

document.getElementById('btnLimpiar').addEventListener('click', () => {
    Object.values(filtros).forEach(el => el.value = '');
    aplicarFiltros();
});
</script>
@endsection
