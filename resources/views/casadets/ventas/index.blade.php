@extends('layouts.app')

@section('content')
<style>
.fila-pagado   { background: #d1e7dd !important; }
.fila-parcial  { background: #fff3cd !important; }
.fila-anulado  { background: #f8d7da !important; opacity:.85; }
.fila-canjeada { background: #e9ecef !important; opacity:.9; }
.select-estado { font-size:.78rem; padding:.2rem .5rem; border-radius:20px; font-weight:600; cursor:pointer; border:1.5px solid; appearance:none; -webkit-appearance:none; text-align:center; min-width:110px; }
.select-estado.est-pendiente { border-color:#adb5bd; background:#f8f9fa; color:#495057; }
.select-estado.est-parcial   { border-color:#fd7e14; background:#fff3cd; color:#7c4a00; }
.select-estado.est-pagado    { border-color:#198754; background:#d1e7dd; color:#155724; }
.select-estado.est-anulado   { border-color:#dc3545; background:#f8d7da; color:#842029; }
.select-estado.est-canjeada  { border-color:#6c757d; background:#e9ecef; color:#495057; }
.filter-input { font-size:.78rem; border-radius:5px; border:1px solid #ced4da; padding:.2rem .4rem; background:#fff; width:100%; min-width:0; transition:border-color .15s,box-shadow .15s; }
.filter-input:focus { outline:none; border-color:#86b7fe; box-shadow:0 0 0 2px rgba(13,110,253,.15); }
.thead-filter td { background:#e9f0fb; padding:.3rem .5rem; border-bottom:2px solid #c8d8f5; }
.fila-oculta { display:none !important; }
.pagination { justify-content: center; margin-top: 1rem; }
.pagination .page-item { margin: 0 .125rem; }
.pagination .page-link { min-width: 2.4rem; padding: .4rem .75rem; }
.pagination .page-item.active .page-link { background-color: #0d6efd; border-color: #0d6efd; }
.pagination .page-link:hover { background-color: #e7f1ff; }
.pagination .page-item.disabled .page-link { color: #adb5bd; }
</style>

@if(!$cajaAbierta)
<div class="alert alert-warning d-flex align-items-center gap-2 mb-3 py-2">
    <i class="bi bi-lock-fill fs-5 flex-shrink-0"></i>
    <div class="flex-grow-1">
        <strong>Caja cerrada.</strong> No puedes crear ni modificar ventas hasta que se abra la caja del día.
    </div>
    <a href="/casadets/caja" class="btn btn-sm btn-warning flex-shrink-0">
        <i class="bi bi-box-arrow-in-right me-1"></i>Ir a Caja
    </a>
</div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">Ventas</h3>
        <p class="text-muted mb-0">Registro de ventas por vendedor.</p>
    </div>
    <div class="d-flex gap-2">
        @if(auth()->user()->puedeHacer('ventas.importar'))
        <a href="/casadets/ventas/import" class="btn btn-outline-success">
            <i class="bi bi-file-earmark-spreadsheet"></i> Importar Excel
        </a>
        @endif
        <a id="btnExportar" href="/casadets/ventas/export" class="btn btn-outline-secondary" data-todas="{{ $todas ? '1' : '0' }}">
            <i class="bi bi-download"></i> Exportar Excel
        </a>
        @if(auth()->user()->puedeHacer('ventas.crear'))
        <a href="/casadets/ventas/create" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Nueva venta
        </a>
        @endif
    </div>
</div>

{{-- ── RANGO DE FECHAS (server-side) ──────────────────────────── --}}
<form method="GET" action="/casadets/ventas" id="formFechas" class="mb-3" data-dynamic-filter data-default-today>
    <div class="d-flex align-items-end gap-3 flex-wrap">

        <div class="d-flex align-items-center gap-2">
            <div>
                <label class="d-block" style="font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#6c757d;margin-bottom:.2rem;">Fecha Inicio</label>
                <div class="input-group input-group-sm" style="width:160px;">
                    <input type="date" name="desde" id="fDesde"
                           value="{{ $desde }}"
                           class="form-control form-control-sm" style="font-size:.82rem;">
                    <span class="input-group-text bg-white"><i class="bi bi-calendar3" style="font-size:.75rem;"></i></span>
                </div>
            </div>
            <div style="padding-top:1.2rem;color:#adb5bd;font-size:.9rem;">—</div>
            <div>
                <label class="d-block" style="font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#6c757d;margin-bottom:.2rem;">Fecha Fin</label>
                <div class="input-group input-group-sm" style="width:160px;">
                    <input type="date" name="hasta" id="fHasta"
                           value="{{ $hasta }}"
                           class="form-control form-control-sm" style="font-size:.82rem;">
                    <span class="input-group-text bg-white"><i class="bi bi-calendar3" style="font-size:.75rem;"></i></span>
                </div>
            </div>
            <div style="padding-top:1.2rem;display:flex;gap:.35rem;">
                <button type="submit" class="btn btn-sm btn-primary" style="font-size:.78rem;" title="Aplicar rango">
                    <i class="bi bi-search"></i>
                </button>
                <a href="/casadets/ventas" class="btn btn-sm btn-outline-secondary" style="font-size:.78rem;" title="Volver a hoy">
                    Hoy
                </a>
                <a href="/casadets/ventas?todas=1" class="btn btn-sm {{ $todas ? 'btn-secondary' : 'btn-outline-secondary' }}" style="font-size:.78rem;" title="Ver todas las fechas">
                    Todas
                </a>
            </div>
        </div>

        <div style="font-size:.8rem;color:#6c757d;padding-bottom:.1rem;">
            @if($todas)
                <span class="badge bg-secondary">Todas las fechas · {{ $ventas->total() }} ventas</span>
            @else
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">
                    {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }}
                    @if($desde !== $hasta) — {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }} @endif
                    · {{ $ventas->total() }} venta(s)
                </span>
            @endif
            <span id="contadorFiltro" style="display:none;">
                · Mostrando <span id="cntVisible" class="fw-bold text-dark">0</span> filtradas
            </span>
        </div>

    </div>
</form>

<div id="ventasContainer">
<form method="GET" action="/casadets/ventas" id="formFiltros">
    <input type="hidden" name="desde" value="{{ $desde }}">
    <input type="hidden" name="hasta" value="{{ $hasta }}">
    <input type="hidden" name="todas" value="{{ $todas ? '1' : '' }}">
<div class="card">
    <div class="table-responsive">
        <table class="table mb-0 align-middle" id="tablaVentas">
            <thead>
                <tr class="table-light">
                    <th style="width:125px;">Estado</th>
                    <th>Fecha</th>
                    <th>Vendedor</th>
                    <th>Cliente</th>
                    <th>Pago</th>
                    <th>Documento</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Acciones</th>
                </tr>
                <tr class="thead-filter">
                    <td>
                        <select id="fEstado" name="estado" class="filter-input">
                            <option value="">Todos</option>
                            <option value="pendiente" {{ isset($estado) && $estado === 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                            <option value="parcial" {{ isset($estado) && $estado === 'parcial' ? 'selected' : '' }}>Parcial</option>
                            <option value="pagado" {{ isset($estado) && $estado === 'pagado' ? 'selected' : '' }}>Pagado</option>
                            <option value="anulado" {{ isset($estado) && $estado === 'anulado' ? 'selected' : '' }}>Anulado</option>
                            <option value="canjeada" {{ isset($estado) && $estado === 'canjeada' ? 'selected' : '' }}>Ref. fiscal</option>
                        </select>
                    </td>
                    <td><input type="date" id="fFecha" name="fecha" value="{{ $fecha ?? '' }}" class="filter-input" style="font-size:.76rem;"></td>
                    <td><input type="text" id="fVendedor" name="vendedor" value="{{ $vendedor ?? '' }}" class="filter-input" placeholder="Buscar…"></td>
                    <td><input type="text" id="fCliente" name="cliente" value="{{ $cliente ?? '' }}" class="filter-input" placeholder="Nombre o RUC…"></td>
                    <td>
                        <select id="fPago" name="pago" class="filter-input">
                            <option value="">Todos</option>
                            @foreach(['efectivo'=>'Efectivo','tarjeta'=>'Tarjeta','yape'=>'Yape','plin'=>'Plin','transferencia'=>'Transferencia'] as $k=>$lbl)
                                <option value="{{ $k }}" {{ isset($pago) && $pago === $k ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            @if($seriesDisponibles->isNotEmpty())
                            <select id="fSerie" name="serie" class="filter-input" style="max-width:80px;" title="Filtrar por serie">
                                <option value="">Todas</option>
                                @foreach($seriesDisponibles as $s)
                                    <option value="{{ $s->codigo }}" {{ isset($serie) && $serie === $s->codigo ? 'selected' : '' }}>
                                        {{ $s->codigo }}
                                    </option>
                                @endforeach
                            </select>
                            @endif
                            <input type="text" id="fDocumento" name="documento" value="{{ $documento ?? '' }}" class="filter-input" placeholder="Nro…">
                        </div>
                    </td>
                    <td><input type="text" id="fTotal" name="total" value="{{ $total ?? '' }}" class="filter-input text-end" placeholder="84.48"></td>
                    <td class="text-end">
                        <button type="button" id="btnLimpiar" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Limpiar filtros" style="font-size:.75rem;">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </td>
                </tr>
            </thead>
            <tbody id="ventasTbody">
                @forelse($ventas as $v)
                @php
                    $metodosArr  = array_filter(explode(',', $v->metodo_pago ?? ''));
                    $estado      = $v->estado ?? 'pendiente';
                    $filaClase   = match($estado) { 'pagado'=>'fila-pagado','parcial'=>'fila-parcial','anulado'=>'fila-anulado','canjeada'=>'fila-canjeada',default=>'' };
                    $clienteTxt  = ($v->cliente->nombre ?? '') . ' ' . ($v->cliente->documento ?? '');
                    $esRefFiscal = ($v->es_referencia_fiscal ?? false) || $estado === 'canjeada';
                    $authU       = auth()->user();
                @endphp
                <tr class="{{ $filaClase }} fila-venta"
                    data-vendedor="{{ strtolower($v->vendedor->nombre ?? '') }}"
                    data-cliente="{{ strtolower($clienteTxt) }}"
                    data-estado="{{ $estado }}"
                    data-fecha="{{ $v->fecha->format('Y-m-d') }}"
                    data-documento="{{ strtolower(($v->documento_tipo ?? '') . ' ' . ($v->documento_numero ?? '')) }}"
                    data-total="{{ $esRefFiscal ? '' : number_format($v->total, 2) }}"
                    data-cobrado="{{ number_format($v->total_cobrado, 2) }}">
                    <td>
                        @if($estado === 'canjeada')
                            <span class="select-estado est-canjeada" style="display:inline-block;cursor:default;"
                                  title="Referencia fiscal — no genera deuda">
                                📋 Ref. fiscal
                            </span>
                        @else
                            <select class="select-estado est-{{ $estado }}"
                                    data-url="/casadets/ventas/{{ $v->id }}/estado"
                                    data-original="{{ $estado }}"
                                    onchange="cambiarEstadoFila(this)">
                                <option value="pendiente" {{ $estado==='pendiente'?'selected':'' }}>⏳ Pendiente</option>
                                <option value="parcial"   {{ $estado==='parcial'  ?'selected':'' }}>◑ Parcial</option>
                                <option value="pagado"    {{ $estado==='pagado'   ?'selected':'' }}>✓ Pagado</option>
                                <option value="anulado"   {{ $estado==='anulado'  ?'selected':'' }}>✕ Anulado</option>
                            </select>
                        @endif
                    </td>
                    <td>{{ $v->fecha->format('d/m/Y') }}</td>
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
                        <div class="d-flex flex-wrap gap-1">
                            @forelse($metodosArr as $m)
                                <span class="badge bg-light text-dark border">{{ ucfirst(trim($m)) }}</span>
                            @empty
                                <span class="text-muted">—</span>
                            @endforelse
                        </div>
                    </td>
                    <td>
                        @if($v->documento_tipo)
                            {{ ucfirst($v->documento_tipo) }} {{ $v->documento_numero }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-end fw-semibold">
                        @if($esRefFiscal)
                            <span class="text-muted small">Referencia fiscal</span>
                        @else
                            S/ {{ number_format($v->total, 2) }}
                        @endif
                        @if(!$esRefFiscal && (float)$v->pagado > 0 && $estado !== 'pagado')
                            <br><small class="text-success">Cobrado: S/ {{ number_format($v->pagado, 2) }}</small>
                            @php $saldoV = max(0, (float)$v->total - (float)$v->pagado); @endphp
                            @if($saldoV > 0)
                                <br><small class="text-danger">Saldo: S/ {{ number_format($saldoV, 2) }}</small>
                            @endif
                        @endif
                    </td>
                    <td class="text-end">
                        <div class="d-flex justify-content-end gap-1 flex-wrap">
                            <a href="/casadets/ventas/{{ $v->id }}" class="btn btn-sm btn-outline-secondary">Ver</a>
                            @if($authU->puedeHacer('ventas.editar'))
                            <a href="/casadets/ventas/{{ $v->id }}/edit" class="btn btn-sm btn-outline-primary">Editar</a>
                            @endif
                            @if(!$esRefFiscal && $authU->puedeHacer('ventas.pago'))
                            <a href="/casadets/ventas/{{ $v->id }}/pago" class="btn btn-sm btn-outline-success" title="Verificar pago">
                                <i class="bi bi-cash-stack"></i>
                            </a>
                            @endif
                            @if($authU->puedeHacer('ventas.eliminar'))
                            <form action="/casadets/ventas/{{ $v->id }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar venta?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No hay ventas en este período.</td></tr>
                @endforelse
            </tbody>
            @if($ventas->count())
            <tfoot id="ventasTfoot">
                <tr class="table-light">
                    <th colspan="6" class="text-end">Total cobrado (visibles)</th>
                    <th class="text-end" id="totalVisible">S/ {{ number_format($ventas->sum(fn($v) => $v->total_cobrado), 2) }}</th>
                    <th></th>
                </tr>
            </tfoot>
            @else
            <tfoot id="ventasTfoot"></tfoot>
            @endif
        </table>
    </div>
</div>
</form>

<div id="ventasPaginacion">
@if($ventas->hasPages())
<div class="d-flex justify-content-between align-items-center mt-3">
    <div>
        {{ $ventas->withQueryString()->links('pagination::bootstrap-5') }}
    </div>
    <div class="text-muted small">
        Mostrando {{ $ventas->count() }} de {{ $ventas->total() }} ventas
    </div>
</div>
@endif
</div>

</div>

<div id="filaNoResultados" class="text-center text-muted py-4" style="display:none;">
    <i class="bi bi-search me-1"></i> Sin resultados para los filtros aplicados.
</div>

<script>
const _csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

function cambiarEstadoFila(sel) {
    const nuevo    = sel.value;
    const original = sel.dataset.original;
    sel.className  = 'select-estado est-' + nuevo;
    fetch(sel.dataset.url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-TOKEN': _csrf,
        },
        body: '_method=POST&estado=' + encodeURIComponent(nuevo),
    }).then(r => {
        if (r.ok) {
            sel.dataset.original = nuevo;
            // actualizar clase de la fila
            const tr = sel.closest('tr');
            if (tr) {
                tr.classList.remove('fila-pagado','fila-parcial','fila-anulado','fila-canjeada');
                const mapa = {pagado:'fila-pagado',parcial:'fila-parcial',anulado:'fila-anulado',canjeada:'fila-canjeada'};
                if (mapa[nuevo]) tr.classList.add(mapa[nuevo]);
                tr.dataset.estado = nuevo;
            }
        } else {
            sel.value     = original;
            sel.className = 'select-estado est-' + original;
        }
    }).catch(() => {
        sel.value     = original;
        sel.className = 'select-estado est-' + original;
    });
}

// ── Filtrado JS en vivo (sobre las filas ya cargadas) ────────────
let formFiltros    = document.getElementById('formFiltros');
let filtros = {
    estado:    document.getElementById('fEstado'),
    vendedor:  document.getElementById('fVendedor'),
    cliente:   document.getElementById('fCliente'),
    pago:      document.getElementById('fPago'),
    serie:     document.getElementById('fSerie'),
    documento: document.getElementById('fDocumento'),
    total:     document.getElementById('fTotal'),
};
let fFecha = document.getElementById('fFecha');

    let filas        = document.querySelectorAll('.fila-venta');
    let cntVisible   = document.getElementById('cntVisible');
    let contador     = document.getElementById('contadorFiltro');
    let noResultados = document.getElementById('filaNoResultados');
    let totalVisible = document.getElementById('totalVisible');
const filtrosStorageKey = 'casadets.ventas.filtros-tabla';

function normalizar(str) {
    return (str || '').toLowerCase()
        .replace(/[áàä]/g,'a').replace(/[éèë]/g,'e')
        .replace(/[íìï]/g,'i').replace(/[óòö]/g,'o')
        .replace(/[úùü]/g,'u').replace(/ñ/g,'n');
}

function aplicarFiltros() {
    const vals = {};
    for (const [k, el] of Object.entries(filtros)) vals[k] = normalizar(el.value.trim());
    const fecha = fFecha.value;
    const hayFiltro = Object.values(vals).some(v => v !== '') || fecha;

    let visibles = 0;
    let totalCob = 0;

    filas.forEach(tr => {
        const d = {
            estado:    normalizar(tr.dataset.estado),
            vendedor:  normalizar(tr.dataset.vendedor),
            cliente:   normalizar(tr.dataset.cliente),
            pago:      normalizar(tr.dataset.pago),
            documento: normalizar(tr.dataset.documento),
            total:     normalizar(tr.dataset.total),
        };
        const fechaFila = tr.dataset.fecha || '';

        const ok =
            (!vals.estado    || d.estado === vals.estado)             &&
            (!vals.vendedor  || d.vendedor.includes(vals.vendedor))   &&
            (!vals.cliente   || d.cliente.includes(vals.cliente))     &&
            (!vals.pago      || d.pago.includes(vals.pago))           &&
            (!vals.documento || d.documento.includes(vals.documento)) &&
            (!vals.total     || d.total.includes(vals.total))         &&
            (!fecha          || fechaFila === fecha);

        tr.classList.toggle('fila-oculta', !ok);
        if (ok) { visibles++; totalCob += parseFloat((tr.dataset.cobrado || '0').replace(/,/g, '')) || 0; }
    });

    if (contador) contador.style.display = hayFiltro ? '' : 'none';
    if (cntVisible) cntVisible.textContent = visibles;
    if (noResultados) noResultados.style.display = (hayFiltro && visibles === 0) ? '' : 'none';
    if (totalVisible) totalVisible.textContent = 'S/ ' + totalCob.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function guardarFiltrosTabla() {
    // no-op: do not persist filters across pages (avoid 'marked' previous searches)
}

function restaurarFiltrosTabla() {
    // no-op: do not restore persisted filters
}

restaurarFiltrosTabla();
formFiltros?.addEventListener('submit', guardarFiltrosTabla);

[filtros.estado, filtros.pago, filtros.serie].forEach(el => el?.addEventListener('change', () => {
    // Do NOT clear 'todas' — Estado/Pago/Serie are orthogonal to the date range
    fetchFiltersAjax(false);
}));
fFecha.addEventListener('change', () => {
    // Filtering by a specific date implies leaving "all dates" mode
    const h = formFiltros?.querySelector('input[name="todas"]'); if (h) h.value = '';
    fetchFiltersAjax(false);
});

document.getElementById('btnLimpiar').addEventListener('click', () => {
    Object.values(filtros).forEach(el => el.value = '');
    fFecha.value = '';
    const h = formFiltros?.querySelector('input[name="todas"]'); if (h) h.value = '';
    actualizarExport();
    formFiltros?.submit();
});

// Actualizar href de exportar con el rango actual
let btnExportar = document.getElementById('btnExportar');
const fDesde = document.getElementById('fDesde');
const fHasta = document.getElementById('fHasta');
function actualizarExport() {
    if (!btnExportar) return;

    const params = new URLSearchParams();
    const exportaTodas = btnExportar.dataset.todas === '1';

    if (exportaTodas) {
        params.set('todas', '1');
    } else {
        if (fDesde?.value) params.set('desde', fDesde.value);
        if (fHasta?.value) params.set('hasta', fHasta.value);
    }

    const filtrosExport = {
        estado: filtros.estado?.value,
        fecha: fFecha?.value,
        vendedor: filtros.vendedor?.value,
        cliente: filtros.cliente?.value,
        pago: filtros.pago?.value,
        serie: filtros.serie?.value,
        documento: filtros.documento?.value,
        total: filtros.total?.value,
    };

    for (const [key, value] of Object.entries(filtrosExport)) {
        const limpio = (value || '').trim();
        if (limpio) params.set(key, limpio);
    }

    const query = params.toString();
    btnExportar.href = '/casadets/ventas/export' + (query ? '?' + query : '');
}
if (fDesde) fDesde.addEventListener('change', actualizarExport);
if (fHasta) fHasta.addEventListener('change', actualizarExport);
Object.values(filtros).forEach(el => el.addEventListener('input', actualizarExport));
fFecha.addEventListener('input', actualizarExport);
actualizarExport();

// ── AJAX live-search (debounced) ─────────────────────────────
let ajaxController = null;
function debounce(fn, ms) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

function rebindTableBehaviors() {
    // Los inputs de filtro (thead-filter) NUNCA se reemplazan — no re-enlazar.
    // Solo actualizar referencias a filas y paginación.
    filas        = document.querySelectorAll('.fila-venta');
    cntVisible   = document.getElementById('cntVisible');
    contador     = document.getElementById('contadorFiltro');
    noResultados = document.getElementById('filaNoResultados');
    totalVisible = document.getElementById('totalVisible');

    // Paginación AJAX — re-enlazar los nuevos links
    document.querySelectorAll('#ventasPaginacion .pagination a.page-link').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const href = link.href || link.getAttribute('href');
            if (!href) return;
            const pageParam = new URL(href, window.location.origin).searchParams.get('page');
            if (pageParam) fetchFiltersAjax(false, parseInt(pageParam));
        });
    });
}

async function fetchFiltersAjax(forceTodas = false, pageNum = null) {
    if (!formFiltros) return;
    const fd = new FormData(formFiltros);
    if (forceTodas) {
        fd.set('todas', '1');
        fd.set('page', '1');
    } else if (pageNum !== null) {
        fd.set('page', pageNum.toString());
    }
    const url = formFiltros.action + '?' + new URLSearchParams(fd).toString();
    if (ajaxController) ajaxController.abort();
    ajaxController = new AbortController();

    try {
        const res = await fetch(url, { signal: ajaxController.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const text = await res.text();
        const tmp = document.createElement('div');
        tmp.innerHTML = text;

        // Reemplazar SOLO tbody, tfoot y paginación — los inputs del thead nunca se tocan
        const newTbody = tmp.querySelector('#ventasTbody');
        const newTfoot = tmp.querySelector('#ventasTfoot');
        const newPag   = tmp.querySelector('#ventasPaginacion');

        if (newTbody) document.getElementById('ventasTbody')?.replaceWith(newTbody);
        if (newTfoot) document.getElementById('ventasTfoot')?.replaceWith(newTfoot);
        if (newPag)   document.getElementById('ventasPaginacion')?.replaceWith(newPag);

        rebindTableBehaviors();
        aplicarFiltros();
    } catch (err) {
        if (err.name !== 'AbortError') console.error('AJAX filter error', err);
    } finally {
        ajaxController = null;
    }
}

const debouncedFetch = debounce(() => {
    if (formFiltros) {
        const h = formFiltros.querySelector('input[name="todas"]');
        if (h) h.value = '1';
    }
    fetchFiltersAjax(true);
}, 200);

// Wire text inputs to live AJAX fetch
[filtros.vendedor, filtros.cliente, filtros.documento, filtros.total].forEach(el => {
    el?.addEventListener('input', debouncedFetch);
});

// Select and date listeners are registered above (lines 360-364) — do not duplicate here
</script>
@endsection
