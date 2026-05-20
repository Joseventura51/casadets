@extends('layouts.app')

@section('content')
@php
    $metodos = ['efectivo','tarjeta','yape','plin','transferencia'];
    $metodoColores = ['efectivo'=>'success','tarjeta'=>'primary','yape'=>'purple','plin'=>'info','transferencia'=>'warning'];
@endphp

<style>
.venta-card { border-radius: 10px; overflow: hidden; }
.venta-card .card-header { border-bottom: 1px solid rgba(0,0,0,.08); }
.pago-row { background: #f8f9fa; border-radius: 8px; padding: .5rem .75rem; margin-bottom: .4rem; display: flex; gap: .5rem; align-items: center; }
.pago-row select, .pago-row input { border-radius: 6px; }
.btn-add-pago { border: 1.5px dashed #0d6efd; border-radius: 8px; font-size: .8rem; padding: .25rem .75rem; color: #0d6efd; background: transparent; }
.btn-add-pago:hover { background: #e8f0fe; }
.total-cobrado-display { font-size: 1.15rem; font-weight: 700; color: #0d6efd; }
.diferencia-pill { font-size: .8rem; padding: .2rem .55rem; border-radius: 20px; display: inline-block; }
.productos-tabla th { font-size: .78rem; text-transform: uppercase; letter-spacing: .03em; color: #6c757d; }
.doc-badge { font-size: .78rem; padding: .25rem .55rem; border-radius: 20px; letter-spacing: .02em; }
.venta-num { font-size: .8rem; color: #6c757d; font-weight: 500; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0"><i class="bi bi-eye me-2 text-primary"></i>Vista previa de importación</h3>
        <p class="text-muted mb-0 small">
            <span id="contadorVentas" class="fw-semibold text-dark">{{ count($grupos) }}</span> venta(s) detectadas.
            Revisa y confirma antes de guardar.
        </p>
    </div>
    <a href="/casadets/ventas/import" class="btn btn-outline-secondary btn-sm">← Cancelar</a>
</div>

@if($errors->any())
    <div class="alert alert-danger mb-3">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

@if(!empty($omitidos))
    <div class="alert alert-warning mb-3 d-flex gap-2 align-items-start">
        <i class="bi bi-skip-forward-fill fs-5 mt-1 text-warning"></i>
        <div>
            <strong>{{ count($omitidos) }} documento(s) omitidos por ya existir en el sistema:</strong>
            <div class="mt-1 d-flex flex-wrap gap-1">
                @foreach($omitidos as $dup)
                    <span class="badge bg-warning text-dark" style="font-size:.78rem;">{{ $dup }}</span>
                @endforeach
            </div>
            <div class="mt-1 text-muted small">Solo se muestran abajo los documentos nuevos.</div>
        </div>
    </div>
@endif

<div class="alert alert-light border small mb-3 py-2">
    <i class="bi bi-pencil-square text-primary me-1"></i>
    Puedes editar <strong>vendedor</strong>, <strong>métodos de pago</strong> (uno o varios), <strong>productos</strong> y <strong>totales</strong>. El total cobrado se calcula automáticamente de los pagos.
</div>

<form action="/casadets/ventas/import/confirm" method="POST" id="formImport">
    @csrf

    <div class="d-flex justify-content-end mb-3">
        <button type="submit" class="btn btn-success px-4">
            <i class="bi bi-check-lg me-1"></i> Confirmar e importar todo
        </button>
    </div>

    <div id="ventasContainer">
        @foreach($grupos as $i => $g)
        @php
            $numFmt = trim(($g['serie'] ?? '') . '-' . ($g['numero'] ?? ''), '-');
            $esDup = in_array($numFmt, $duplicadosExistentes);
            $docLetra = strtoupper($g['doc'] ?? '');
            $badgeCls = $docLetra === 'B' ? 'bg-secondary' : ($docLetra === 'F' ? 'bg-primary' : 'bg-warning text-dark');
        @endphp
        <div class="card mb-3 venta-card shadow-sm {{ $esDup ? 'border-danger border-2' : 'border-0' }}" data-idx="{{ $i }}">

            {{-- HEADER --}}
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="venta-num">#{{ $i + 1 }}</span>
                    <span class="text-dark fw-semibold">{{ \Carbon\Carbon::parse($g['fecha'])->format('d/m/Y') }}</span>
                    @if($numFmt)
                        <span class="badge {{ $badgeCls }} doc-badge">{{ $numFmt }}</span>
                    @endif
                    @if(!empty($g['razon_social']))
                        <span class="text-dark fw-semibold" style="font-size:.9rem;">
                            <i class="bi bi-building me-1 text-secondary"></i>{{ $g['razon_social'] }}
                        </span>
                        @if(!empty($g['ruc']))
                            <span class="text-muted" style="font-size:.78rem;">RUC: {{ $g['ruc'] }}</span>
                        @endif
                    @endif
                    @if($esDup)
                        <span class="badge bg-danger"><i class="bi bi-exclamation-triangle me-1"></i>Duplicada</span>
                    @endif
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-venta">
                    <i class="bi bi-trash me-1"></i>Eliminar
                </button>
            </div>

            <div class="card-body pt-3">
                <input type="hidden" name="ventas[{{ $i }}][fecha]"        value="{{ $g['fecha'] }}">
                <input type="hidden" name="ventas[{{ $i }}][doc]"          value="{{ $g['doc'] }}">
                <input type="hidden" name="ventas[{{ $i }}][serie]"        value="{{ $g['serie'] }}">
                <input type="hidden" name="ventas[{{ $i }}][numero]"       value="{{ $g['numero'] }}">
                <input type="hidden" name="ventas[{{ $i }}][razon_social]" value="{{ $g['razon_social'] ?? '' }}">
                <input type="hidden" name="ventas[{{ $i }}][ruc]"          value="{{ $g['ruc'] ?? '' }}">

                <div class="row g-3 mb-3">

                    {{-- VENDEDOR --}}
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1"><i class="bi bi-person me-1"></i>Vendedor</label>
                        <select name="ventas[{{ $i }}][vendedor_id]" class="form-select form-select-sm" required>
                            @foreach($vendedores as $v)
                                <option value="{{ $v->id }}" {{ $v->id == $vendedor_id_default ? 'selected' : '' }}>{{ $v->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- MÉTODOS DE PAGO --}}
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold mb-1"><i class="bi bi-credit-card me-1"></i>Métodos de pago</label>
                        <div class="pagos-container" data-venta-idx="{{ $i }}">
                            {{-- Fila inicial con el total completo --}}
                            <div class="pago-row">
                                <select name="ventas[{{ $i }}][pagos][0][metodo]" class="form-select form-select-sm metodo-sel" style="flex:1;">
                                    @foreach($metodos as $m)
                                        <option value="{{ $m }}" {{ $m == $metodo_pago_default ? 'selected' : '' }}>{{ ucfirst($m) }}</option>
                                    @endforeach
                                </select>
                                <div class="input-group input-group-sm" style="width:110px;">
                                    <span class="input-group-text py-0 px-1 bg-white border-end-0 small text-muted">S/</span>
                                    <input type="number" name="ventas[{{ $i }}][pagos][0][monto]"
                                        value="0"
                                        step="0.01" min="0"
                                        class="form-control form-control-sm text-end monto-pago border-start-0"
                                        style="width:75px;" required>
                                </div>
                                <button type="button" class="btn btn-sm p-1 lh-1 text-danger border-0 bg-transparent btn-del-pago" title="Quitar" style="font-size:1rem;">
                                    <i class="bi bi-x-circle-fill"></i>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="btn-add-pago w-100 mt-1 btn-agregar-pago">
                            <i class="bi bi-plus-lg me-1"></i>Agregar método
                        </button>
                    </div>

                    {{-- TOTALES --}}
                    <div class="col-md-5">
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small fw-semibold mb-1">Total productos</label>
                                <div class="form-control form-control-sm bg-light text-end fw-semibold total-real-display">
                                    S/ {{ number_format($g['total'], 2) }}
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold mb-1">Total cobrado</label>
                                <div class="total-cobrado-display text-end py-1">S/ {{ number_format($g['total'], 2) }}</div>
                                <input type="hidden" name="ventas[{{ $i }}][total_cobrado]"
                                    value="{{ number_format($g['total'], 2, '.', '') }}"
                                    class="total-cobrado-hidden">
                            </div>
                            <div class="col-12">
                                <div class="diferencia-pill bg-light text-muted w-100 text-center">Sin diferencia</div>
                            </div>
                        </div>
                    </div>

                </div>

                {{-- PRODUCTOS --}}
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 productos-tabla">
                        <thead class="table-light">
                            <tr>
                                <th>Producto</th>
                                <th class="text-end" style="width:90px;">Cant.</th>
                                <th class="text-end" style="width:115px;">Precio</th>
                                <th class="text-end" style="width:110px;">Subtotal</th>
                                <th style="width:40px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($g['detalles'] as $j => $d)
                            <tr class="producto-row">
                                <td>
                                    <input type="text" name="ventas[{{ $i }}][detalles][{{ $j }}][producto]"
                                        value="{{ $d['producto'] }}" class="form-control form-control-sm" required>
                                </td>
                                <td>
                                    <input type="number" name="ventas[{{ $i }}][detalles][{{ $j }}][cantidad]"
                                        value="{{ rtrim(rtrim(number_format($d['cantidad'], 2, '.', ''), '0'), '.') }}"
                                        step="0.01" min="0" class="form-control form-control-sm text-end cantidad-input" required>
                                </td>
                                <td>
                                    <input type="number" name="ventas[{{ $i }}][detalles][{{ $j }}][precio_unitario]"
                                        value="{{ number_format($d['precio_unitario'], 2, '.', '') }}"
                                        step="0.01" min="0" class="form-control form-control-sm text-end precio-input" required>
                                </td>
                                <td class="text-end text-muted subtotal-display" style="font-size:.87rem;">
                                    S/ {{ number_format($d['subtotal'], 2) }}
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm p-0 text-danger border-0 bg-transparent btn-eliminar-producto" style="font-size:1rem;">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
        @endforeach
    </div>

    <div id="sinVentas" class="alert alert-secondary text-center" style="display:none;">
        No quedan ventas para importar. <a href="/casadets/ventas/import">Volver</a>.
    </div>

    <div class="d-flex justify-content-between align-items-center mt-3 pb-4">
        <a href="/casadets/ventas/import" class="btn btn-outline-secondary">← Volver</a>
        <button type="submit" class="btn btn-success px-4">
            <i class="bi bi-check-lg me-1"></i> Confirmar e importar todo
        </button>
    </div>
</form>

<script>
const METODOS = @json($metodos);

/* ── Recalcula subtotales de productos y actualiza total real ── */
function recalcProductos(card) {
    let totalReal = 0;
    card.querySelectorAll('.producto-row').forEach(row => {
        const c = parseFloat(row.querySelector('.cantidad-input').value) || 0;
        const p = parseFloat(row.querySelector('.precio-input').value) || 0;
        const sub = c * p;
        row.querySelector('.subtotal-display').textContent = 'S/ ' + sub.toFixed(2);
        totalReal += sub;
    });
    card.querySelector('.total-real-display').textContent = 'S/ ' + totalReal.toFixed(2);
    card.dataset.totalReal = totalReal;
    recalcDiferencia(card);
}

/* ── Recalcula total cobrado desde pagos y diferencia ── */
function recalcPagos(card) {
    let totalCob = 0;
    card.querySelectorAll('.monto-pago').forEach(inp => totalCob += parseFloat(inp.value) || 0);
    card.querySelector('.total-cobrado-display').textContent = 'S/ ' + totalCob.toFixed(2);
    card.querySelector('.total-cobrado-hidden').value = totalCob.toFixed(2);
    card.dataset.totalCob = totalCob;
    recalcDiferencia(card);
}

function recalcDiferencia(card) {
    const real = parseFloat(card.dataset.totalReal) || 0;
    const cob  = parseFloat(card.dataset.totalCob ?? card.querySelector('.total-cobrado-hidden').value) || 0;
    const d = cob - real;
    const pill = card.querySelector('.diferencia-pill');
    if (Math.abs(d) < 0.005) {
        pill.className = 'diferencia-pill bg-light text-muted w-100 text-center';
        pill.textContent = 'Sin diferencia';
    } else if (d > 0) {
        pill.className = 'diferencia-pill bg-success text-white w-100 text-center';
        pill.textContent = '+S/ ' + d.toFixed(2) + ' de más';
    } else {
        pill.className = 'diferencia-pill bg-danger text-white w-100 text-center';
        pill.textContent = 'Faltan S/ ' + Math.abs(d).toFixed(2);
    }
}

/* ── Crea una nueva fila de pago ── */
function crearPagoRow(ventaIdx, pagoIdx, metodoSel = 'efectivo', monto = '') {
    const div = document.createElement('div');
    div.className = 'pago-row';
    div.innerHTML = `
        <select name="ventas[${ventaIdx}][pagos][${pagoIdx}][metodo]" class="form-select form-select-sm metodo-sel" style="flex:1;">
            ${METODOS.map(m => `<option value="${m}" ${m===metodoSel?'selected':''}>${m.charAt(0).toUpperCase()+m.slice(1)}</option>`).join('')}
        </select>
        <div class="input-group input-group-sm" style="width:110px;">
            <span class="input-group-text py-0 px-1 bg-white border-end-0 small text-muted">S/</span>
            <input type="number" name="ventas[${ventaIdx}][pagos][${pagoIdx}][monto]"
                value="${monto}" step="0.01" min="0"
                class="form-control form-control-sm text-end monto-pago border-start-0"
                style="width:75px;" required>
        </div>
        <button type="button" class="btn btn-sm p-1 lh-1 text-danger border-0 bg-transparent btn-del-pago" title="Quitar" style="font-size:1rem;">
            <i class="bi bi-x-circle-fill"></i>
        </button>`;
    return div;
}

/* ── Reindexa los nombres de inputs de pagos en un container ── */
function reindexarPagos(container, ventaIdx) {
    container.querySelectorAll('.pago-row').forEach((row, idx) => {
        row.querySelector('select').name = `ventas[${ventaIdx}][pagos][${idx}][metodo]`;
        row.querySelector('input').name  = `ventas[${ventaIdx}][pagos][${idx}][monto]`;
    });
}

/* ── Inicializa cada tarjeta de venta ── */
document.querySelectorAll('.venta-card').forEach(card => {
    const idx = card.dataset.idx;

    recalcProductos(card);
    recalcPagos(card);

    /* Edición de productos */
    card.addEventListener('input', e => {
        if (e.target.matches('.cantidad-input, .precio-input')) recalcProductos(card);
        if (e.target.matches('.monto-pago')) recalcPagos(card);
    });

    /* Eliminar producto */
    card.addEventListener('click', e => {
        const btnP = e.target.closest('.btn-eliminar-producto');
        if (btnP) {
            const filas = card.querySelectorAll('.producto-row');
            if (filas.length <= 1) { alert('No puedes eliminar el último producto. Elimina la venta completa.'); return; }
            btnP.closest('tr').remove();
            recalcProductos(card);
        }

        /* Eliminar fila de pago */
        const btnD = e.target.closest('.btn-del-pago');
        if (btnD) {
            const container = card.querySelector('.pagos-container');
            const filas = container.querySelectorAll('.pago-row');
            if (filas.length <= 1) { alert('Debe quedar al menos un método de pago.'); return; }
            btnD.closest('.pago-row').remove();
            reindexarPagos(container, idx);
            recalcPagos(card);
        }
    });

    /* Agregar fila de pago */
    card.querySelector('.btn-agregar-pago').addEventListener('click', () => {
        const container = card.querySelector('.pagos-container');
        const pagoIdx = container.querySelectorAll('.pago-row').length;
        const row = crearPagoRow(idx, pagoIdx, 'efectivo', '');
        container.appendChild(row);
        row.querySelector('.monto-pago').focus();
    });

    /* Eliminar venta completa */
    card.querySelector('.btn-eliminar-venta').addEventListener('click', () => {
        if (confirm('¿Eliminar esta venta? No se importará.')) {
            card.remove();
            actualizarContador();
        }
    });
});

function actualizarContador() {
    const n = document.querySelectorAll('.venta-card').length;
    document.getElementById('contadorVentas').textContent = n;
    document.getElementById('sinVentas').style.display = n === 0 ? '' : 'none';
}

document.getElementById('formImport').addEventListener('submit', e => {
    if (document.querySelectorAll('.venta-card').length === 0) {
        e.preventDefault();
        alert('No hay ventas para importar.');
    }
});
</script>
@endsection
