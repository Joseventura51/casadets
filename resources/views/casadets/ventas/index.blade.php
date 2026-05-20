@extends('layouts.app')

@section('content')
<style>
.fila-pagado  { background: #d1e7dd !important; }
.fila-anulado { background: #f8d7da !important; opacity:.85; }
.select-estado { font-size:.78rem; padding:.2rem .5rem; border-radius:20px; font-weight:600; cursor:pointer; border:1.5px solid; appearance:none; -webkit-appearance:none; text-align:center; min-width:110px; }
.select-estado.est-pendiente { border-color:#adb5bd; background:#f8f9fa; color:#495057; }
.select-estado.est-pagado    { border-color:#198754; background:#d1e7dd; color:#155724; }
.select-estado.est-anulado   { border-color:#dc3545; background:#f8d7da; color:#842029; }

.filter-input {
    font-size: .82rem;
    border-radius: 7px;
    border: 1.5px solid #e0e0e0;
    padding: .3rem .55rem;
    background: #fafafa;
    transition: border-color .15s, box-shadow .15s;
    width: 100%;
}
.filter-input:focus {
    outline: none;
    border-color: #0d6efd;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(13,110,253,.1);
}
.filter-label {
    font-size: .7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #6c757d;
    margin-bottom: .25rem;
    display: block;
}
.filter-bar {
    background: #fff;
    border-radius: 10px;
    padding: .85rem 1rem;
    border: 1px solid #e9ecef;
    box-shadow: 0 1px 4px rgba(0,0,0,.05);
}
.fila-oculta { display: none !important; }
#contadorFiltro { font-size: .8rem; color: #6c757d; }
#contadorFiltro span { font-weight: 700; color: #212529; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">Ventas</h3>
        <p class="text-muted mb-0">Registro de ventas por vendedor.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/casadets/ventas/import" class="btn btn-outline-success">
            <i class="bi bi-file-earmark-spreadsheet"></i> Importar Excel
        </a>
        <a id="btnExportar" href="/casadets/ventas/export" class="btn btn-outline-secondary">
            <i class="bi bi-download"></i> Exportar Excel
        </a>
        <a href="/casadets/ventas/create" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Nueva venta
        </a>
    </div>
</div>

{{-- FILTROS EN VIVO --}}
<div class="filter-bar mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md col-6">
            <label class="filter-label">Vendedor</label>
            <input type="text" id="fVendedor" class="filter-input" placeholder="Buscar…">
        </div>
        <div class="col-md col-6">
            <label class="filter-label">Cliente</label>
            <input type="text" id="fCliente" class="filter-input" placeholder="Nombre o RUC…">
        </div>
        <div class="col-md col-6">
            <label class="filter-label">Estado</label>
            <select id="fEstado" class="filter-input">
                <option value="">Todos</option>
                <option value="pendiente">⏳ Pendiente</option>
                <option value="pagado">✓ Pagado</option>
                <option value="anulado">✕ Anulado</option>
            </select>
        </div>
        <div class="col-md col-6">
            <label class="filter-label">Fecha</label>
            <input type="text" id="fFecha" class="filter-input" placeholder="ej. 19/05/2026">
        </div>
        <div class="col-md col-6">
            <label class="filter-label">Producto</label>
            <input type="text" id="fProducto" class="filter-input" placeholder="Buscar…">
        </div>
        <div class="col-md col-6">
            <label class="filter-label">Pago</label>
            <select id="fPago" class="filter-input">
                <option value="">Todos</option>
                @foreach(['efectivo'=>'Efectivo','tarjeta'=>'Tarjeta','yape'=>'Yape','plin'=>'Plin','transferencia'=>'Transferencia'] as $k=>$lbl)
                    <option value="{{ $k }}">{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md col-6">
            <label class="filter-label">Documento</label>
            <input type="text" id="fDocumento" class="filter-input" placeholder="ej. F001-001">
        </div>
        <div class="col-md col-6">
            <label class="filter-label">Total</label>
            <input type="text" id="fTotal" class="filter-input" placeholder="ej. 84.48">
        </div>
        <div class="col-auto">
            <button id="btnLimpiar" class="btn btn-sm btn-outline-secondary" style="height:32px; padding:.2rem .75rem; font-size:.82rem;">
                <i class="bi bi-x-lg me-1"></i>Limpiar
            </button>
        </div>
    </div>
    <div class="mt-2" id="contadorFiltro" style="display:none;">
        Mostrando <span id="cntVisible">0</span> de <span id="cntTotal">0</span> ventas
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0 align-middle" id="tablaVentas">
            <thead class="table-light">
                <tr>
                    <th style="width:125px;">Estado</th>
                    <th>Fecha</th>
                    <th>Vendedor</th>
                    <th>Cliente</th>
                    <th>Productos</th>
                    <th>Pago</th>
                    <th>Documento</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ventas as $v)
                @php
                    $metodosArr = array_filter(explode(',', $v->metodo_pago ?? ''));
                    $estado = $v->estado ?? 'pendiente';
                    $filaClase = $estado === 'pagado' ? 'fila-pagado' : ($estado === 'anulado' ? 'fila-anulado' : '');
                    $productosTexto = $v->detalles->pluck('producto')->implode(' ');
                    $clienteTexto   = ($v->cliente->nombre ?? '') . ' ' . ($v->cliente->documento ?? '');
                @endphp
                <tr class="{{ $filaClase }} fila-venta"
                    data-vendedor="{{ strtolower($v->vendedor->nombre ?? '') }}"
                    data-cliente="{{ strtolower($clienteTexto) }}"
                    data-estado="{{ $estado }}"
                    data-fecha="{{ $v->fecha->format('d/m/Y') }}"
                    data-productos="{{ strtolower($productosTexto) }}"
                    data-pago="{{ strtolower($v->metodo_pago ?? '') }}"
                    data-documento="{{ strtolower(($v->documento_tipo ?? '') . ' ' . ($v->documento_numero ?? '')) }}"
                    data-total="{{ number_format($v->total_cobrado, 2) }}">
                    <td>
                        <form action="/casadets/ventas/{{ $v->id }}/estado" method="POST">
                            @csrf
                            <select name="estado"
                                class="select-estado est-{{ $estado }}"
                                onchange="this.form.submit()">
                                <option value="pendiente" {{ $estado==='pendiente'?'selected':'' }}>⏳ Pendiente</option>
                                <option value="pagado"    {{ $estado==='pagado'   ?'selected':'' }}>✓ Pagado</option>
                                <option value="anulado"   {{ $estado==='anulado'  ?'selected':'' }}>✕ Anulado</option>
                            </select>
                        </form>
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
                        @if($v->detalles->count() == 1)
                            {{ $v->detalles->first()->producto }}
                        @else
                            <span class="badge bg-info text-dark">{{ $v->detalles->count() }} productos</span>
                            <small class="text-muted d-block">{{ $v->detalles->pluck('producto')->take(2)->implode(', ') }}{{ $v->detalles->count() > 2 ? '…' : '' }}</small>
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
                        S/ {{ number_format($v->total_cobrado, 2) }}
                        @if($v->ajuste != 0)
                            <br><small class="{{ $v->ajuste > 0 ? 'text-success' : 'text-danger' }}">
                                ({{ $v->ajuste > 0 ? '+' : '' }}{{ number_format($v->ajuste, 2) }})
                            </small>
                        @endif
                    </td>
                    <td class="text-end">
                        <div class="d-flex justify-content-end gap-1 flex-wrap">
                            <a href="/casadets/ventas/{{ $v->id }}" class="btn btn-sm btn-outline-secondary">Ver</a>
                            <a href="/casadets/ventas/{{ $v->id }}/edit" class="btn btn-sm btn-outline-primary">Editar</a>
                            <a href="/casadets/ventas/{{ $v->id }}/pago" class="btn btn-sm btn-outline-success" title="Verificar pago">
                                <i class="bi bi-cash-stack"></i>
                            </a>
                            <form action="/casadets/ventas/{{ $v->id }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar venta?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr id="filaVacia"><td colspan="9" class="text-center text-muted py-4">No hay ventas registradas.</td></tr>
                @endforelse
            </tbody>
            @if($ventas->count())
            <tfoot>
                <tr class="table-light">
                    <th colspan="7" class="text-end">Total cobrado (visibles)</th>
                    <th class="text-end" id="totalVisible">S/ {{ number_format($ventas->sum(fn($v) => $v->total_cobrado), 2) }}</th>
                    <th></th>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

<div id="filaNoResultados" class="text-center text-muted py-4" style="display:none;">
    <i class="bi bi-search me-1"></i> Sin resultados para los filtros aplicados.
</div>

<script>
document.querySelectorAll('.select-estado').forEach(sel => {
    sel.addEventListener('change', function() {
        this.className = 'select-estado est-' + this.value;
    });
});

// ── Filtrado en vivo ──────────────────────────────────────────
const filtros = {
    vendedor:  document.getElementById('fVendedor'),
    cliente:   document.getElementById('fCliente'),
    estado:    document.getElementById('fEstado'),
    fecha:     document.getElementById('fFecha'),
    productos: document.getElementById('fProducto'),
    pago:      document.getElementById('fPago'),
    documento: document.getElementById('fDocumento'),
    total:     document.getElementById('fTotal'),
};

const filas       = document.querySelectorAll('.fila-venta');
const cntVisible  = document.getElementById('cntVisible');
const cntTotal    = document.getElementById('cntTotal');
const contador    = document.getElementById('contadorFiltro');
const noResultados = document.getElementById('filaNoResultados');
const totalVisible = document.getElementById('totalVisible');

if (cntTotal) cntTotal.textContent = filas.length;

function normalizar(str) {
    return str.toLowerCase()
        .replace(/[áàä]/g,'a').replace(/[éèë]/g,'e')
        .replace(/[íìï]/g,'i').replace(/[óòö]/g,'o')
        .replace(/[úùü]/g,'u').replace(/ñ/g,'n');
}

function aplicarFiltros() {
    const vals = {};
    for (const [k, el] of Object.entries(filtros)) {
        vals[k] = normalizar(el.value.trim());
    }

    const hayFiltro = Object.values(vals).some(v => v !== '');
    let visibles = 0;
    let totalCobrado = 0;

    filas.forEach(tr => {
        const d = {
            vendedor:  normalizar(tr.dataset.vendedor  || ''),
            cliente:   normalizar(tr.dataset.cliente   || ''),
            estado:    normalizar(tr.dataset.estado    || ''),
            fecha:     normalizar(tr.dataset.fecha     || ''),
            productos: normalizar(tr.dataset.productos || ''),
            pago:      normalizar(tr.dataset.pago      || ''),
            documento: normalizar(tr.dataset.documento || ''),
            total:     normalizar(tr.dataset.total     || ''),
        };

        const visible =
            (!vals.vendedor  || d.vendedor.includes(vals.vendedor))  &&
            (!vals.cliente   || d.cliente.includes(vals.cliente))    &&
            (!vals.estado    || d.estado === vals.estado)             &&
            (!vals.fecha     || d.fecha.includes(vals.fecha))        &&
            (!vals.productos || d.productos.includes(vals.productos)) &&
            (!vals.pago      || d.pago.includes(vals.pago))          &&
            (!vals.documento || d.documento.includes(vals.documento)) &&
            (!vals.total     || d.total.includes(vals.total));

        tr.classList.toggle('fila-oculta', !visible);

        if (visible) {
            visibles++;
            totalCobrado += parseFloat(tr.dataset.total) || 0;
        }
    });

    if (cntVisible) cntVisible.textContent = visibles;
    if (contador) contador.style.display = hayFiltro ? '' : 'none';
    if (noResultados) noResultados.style.display = (hayFiltro && visibles === 0) ? '' : 'none';
    if (totalVisible) totalVisible.textContent = 'S/ ' + totalCobrado.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

Object.values(filtros).forEach(el => el.addEventListener('input', aplicarFiltros));

document.getElementById('btnLimpiar').addEventListener('click', () => {
    Object.values(filtros).forEach(el => el.value = '');
    aplicarFiltros();
});
</script>
@endsection
