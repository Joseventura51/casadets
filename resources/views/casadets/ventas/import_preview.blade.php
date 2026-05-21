@extends('layouts.app')

@section('content')
@php
    $metodos = ['ninguno','efectivo','tarjeta','yape','plin','transferencia'];
    $metodoLabels = ['ninguno'=>'Ninguno (pendiente)','efectivo'=>'Efectivo','tarjeta'=>'Tarjeta','yape'=>'Yape','plin'=>'Plin','transferencia'=>'Transferencia'];
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

@if(!empty($omitidos ?? []))
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
    <i class="bi bi-info-circle text-primary me-1"></i>
    Selecciona el <strong>método de pago</strong> y el <strong>monto cobrado</strong> por cada venta.
    Si aún no se cobró, deja <strong>Ninguno</strong> y quedará como <em>pendiente</em>.
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
            $docLetra = strtoupper($g['doc'] ?? '');
            $badgeCls = $docLetra === 'B' ? 'bg-secondary' : ($docLetra === 'F' ? 'bg-primary' : 'bg-warning text-dark');
        @endphp
        <div class="card mb-2 venta-card shadow-sm border-0"
             data-idx="{{ $i }}"
             data-total-real="{{ $g['total'] }}">

            {{-- HEADER --}}
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="venta-num">#{{ $i + 1 }}</span>
                    <span class="text-dark fw-semibold">{{ \Carbon\Carbon::parse($g['fecha'])->format('d/m/Y') }}</span>
                    @if($numFmt)
                        <span class="badge {{ $badgeCls }} doc-badge">{{ $numFmt }}</span>
                    @endif
                    @if(!empty($g['razon_social']))
                        <span class="text-dark" style="font-size:.9rem;">
                            <i class="bi bi-building me-1 text-secondary"></i>{{ $g['razon_social'] }}
                        </span>
                    @endif
                    <span class="text-muted small">
                        {{ $g['detalles'] ? count($g['detalles']).' producto(s)' : '' }}
                        · S/ {{ number_format($g['total'], 2) }}
                    </span>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-venta">
                    <i class="bi bi-trash"></i>
                </button>
            </div>

            <div class="card-body py-3">
                <input type="hidden" name="ventas[{{ $i }}][session_idx]" value="{{ $i }}">
                <input type="hidden" name="ventas[{{ $i }}][vendedor_id]" value="{{ $vendedor_id_default }}">
                {{-- detalles_json se actualiza por JS antes del submit --}}
                <input type="hidden" name="ventas[{{ $i }}][detalles_json]"
                       class="detalles-json-input"
                       value="{{ json_encode($g['detalles']) }}">

                {{-- Tabla de productos con código editable --}}
                <div class="mb-3">
                    <details open>
                        <summary class="small fw-semibold text-secondary mb-2" style="cursor:pointer;list-style:none;">
                            <i class="bi bi-box-seam me-1"></i>Productos
                            <span class="badge bg-light text-secondary border ms-1" style="font-size:.72rem;">{{ count($g['detalles']) }}</span>
                        </summary>
                        <div class="table-responsive mt-2">
                            <table class="table table-sm table-bordered mb-0 detalles-table" style="font-size:.82rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th>Producto</th>
                                        <th style="width:120px;">Código</th>
                                        <th class="text-end" style="width:70px;">Cant.</th>
                                        <th class="text-end" style="width:80px;">P.Unit</th>
                                        <th class="text-end" style="width:85px;">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($g['detalles'] as $di => $d)
                                    <tr>
                                        <td class="det-producto">{{ $d['producto'] }}</td>
                                        <td>
                                            <input type="text"
                                                class="form-control form-control-sm det-codigo"
                                                value="{{ $d['codigo'] ?? '' }}"
                                                placeholder="—"
                                                maxlength="100">
                                        </td>
                                        <td class="text-end text-muted det-cantidad">{{ $d['cantidad'] }}</td>
                                        <td class="text-end text-muted det-precio">{{ $d['precio_unitario'] }}</td>
                                        <td class="text-end fw-semibold det-subtotal">{{ number_format($d['subtotal'], 2) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                </div>

                <div class="row g-3 align-items-end">
                    {{-- MÉTODOS DE PAGO --}}
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold mb-1">
                            <i class="bi bi-credit-card me-1"></i>Método de pago
                        </label>
                        <div class="pagos-container" data-venta-idx="{{ $i }}">
                            <div class="pago-row">
                                <select name="ventas[{{ $i }}][pagos][0][metodo]"
                                        class="form-select form-select-sm metodo-sel" style="flex:1;">
                                    @foreach($metodos as $m)
                                        <option value="{{ $m }}" {{ $m == $metodo_pago_default ? 'selected' : '' }}>
                                            {{ $metodoLabels[$m] ?? ucfirst($m) }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="input-group input-group-sm" style="width:120px;">
                                    <span class="input-group-text py-0 px-1 bg-white border-end-0 small text-muted">S/</span>
                                    <input type="number" name="ventas[{{ $i }}][pagos][0][monto]"
                                        value="0" step="0.01" min="0"
                                        class="form-control form-control-sm text-end monto-pago border-start-0"
                                        required>
                                </div>
                                <button type="button" class="btn btn-sm p-1 lh-1 text-danger border-0 bg-transparent btn-del-pago" style="font-size:1rem;">
                                    <i class="bi bi-x-circle-fill"></i>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="btn-add-pago w-100 mt-1 btn-agregar-pago">
                            <i class="bi bi-plus-lg me-1"></i>Agregar método
                        </button>
                    </div>

                    {{-- TOTALES --}}
                    <div class="col-md-6">
                        <div class="row g-2 text-center">
                            <div class="col-4">
                                <div class="text-muted small">Total productos</div>
                                <div class="fw-semibold">S/ {{ number_format($g['total'], 2) }}</div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted small">Total cobrado</div>
                                <div class="total-cobrado-display">S/ 0.00</div>
                                <input type="hidden" name="ventas[{{ $i }}][total_cobrado]"
                                       value="0" class="total-cobrado-hidden">
                            </div>
                            <div class="col-4">
                                <div class="text-muted small">Diferencia</div>
                                <div class="diferencia-pill bg-light text-muted w-100">—</div>
                            </div>
                        </div>
                    </div>
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
const METODOS       = @json($metodos);
const METODO_LABELS = @json($metodoLabels);

// Serializa la tabla de productos (con codigos editados) al hidden detalles_json
function serializarDetalles(card) {
    const jsonInput = card.querySelector('.detalles-json-input');
    const base = JSON.parse(jsonInput.value);
    const rows = card.querySelectorAll('.detalles-table tbody tr');
    rows.forEach((tr, idx) => {
        const codigoInput = tr.querySelector('.det-codigo');
        if (codigoInput && base[idx] !== undefined) {
            base[idx].codigo = codigoInput.value.trim();
        }
    });
    jsonInput.value = JSON.stringify(base);
}

document.getElementById('formImport').addEventListener('submit', function() {
    document.querySelectorAll('.venta-card').forEach(card => serializarDetalles(card));
});

function opcionesSelect(ventaIdx, pagoIdx, metodoSel) {
    return METODOS.map(m =>
        `<option value="${m}" ${m===metodoSel?'selected':''}>${METODO_LABELS[m]||m}</option>`
    ).join('');
}

function recalcPagos(card) {
    let totalCob = 0;
    card.querySelectorAll('.monto-pago').forEach(inp => totalCob += parseFloat(inp.value) || 0);
    const realRaw = parseFloat(card.dataset.totalReal) || 0;

    card.querySelector('.total-cobrado-display').textContent = 'S/ ' + totalCob.toFixed(2);
    card.querySelector('.total-cobrado-hidden').value = totalCob.toFixed(2);

    const d = totalCob - realRaw;
    const pill = card.querySelector('.diferencia-pill');
    if (Math.abs(d) < 0.005) {
        pill.className = 'diferencia-pill bg-light text-muted w-100';
        pill.textContent = 'Exacto';
    } else if (d > 0) {
        pill.className = 'diferencia-pill bg-success text-white w-100';
        pill.textContent = '+S/ ' + d.toFixed(2);
    } else {
        pill.className = 'diferencia-pill bg-danger text-white w-100';
        pill.textContent = '-S/ ' + Math.abs(d).toFixed(2);
    }
}

function crearPagoRow(ventaIdx, pagoIdx) {
    const div = document.createElement('div');
    div.className = 'pago-row';
    div.innerHTML = `
        <select name="ventas[${ventaIdx}][pagos][${pagoIdx}][metodo]"
                class="form-select form-select-sm metodo-sel" style="flex:1;">
            ${opcionesSelect(ventaIdx, pagoIdx, 'ninguno')}
        </select>
        <div class="input-group input-group-sm" style="width:120px;">
            <span class="input-group-text py-0 px-1 bg-white border-end-0 small text-muted">S/</span>
            <input type="number" name="ventas[${ventaIdx}][pagos][${pagoIdx}][monto]"
                value="0" step="0.01" min="0"
                class="form-control form-control-sm text-end monto-pago border-start-0" required>
        </div>
        <button type="button" class="btn btn-sm p-1 lh-1 text-danger border-0 bg-transparent btn-del-pago" style="font-size:1rem;">
            <i class="bi bi-x-circle-fill"></i>
        </button>`;
    return div;
}

function reindexarPagos(container, ventaIdx) {
    container.querySelectorAll('.pago-row').forEach((row, idx) => {
        row.querySelector('select').name = `ventas[${ventaIdx}][pagos][${idx}][metodo]`;
        row.querySelector('input[type=number]').name = `ventas[${ventaIdx}][pagos][${idx}][monto]`;
    });
}

document.querySelectorAll('.venta-card').forEach(card => {
    const idx = card.dataset.idx;
    recalcPagos(card);

    card.addEventListener('input', e => {
        if (e.target.matches('.monto-pago')) recalcPagos(card);
    });

    card.addEventListener('click', e => {
        const btnD = e.target.closest('.btn-del-pago');
        if (btnD) {
            const container = card.querySelector('.pagos-container');
            if (container.querySelectorAll('.pago-row').length <= 1) {
                alert('Debe quedar al menos un método de pago.'); return;
            }
            btnD.closest('.pago-row').remove();
            reindexarPagos(container, idx);
            recalcPagos(card);
        }
    });

    card.querySelector('.btn-agregar-pago').addEventListener('click', () => {
        const container = card.querySelector('.pagos-container');
        const pagoIdx = container.querySelectorAll('.pago-row').length;
        const row = crearPagoRow(idx, pagoIdx);
        container.appendChild(row);
        row.querySelector('.monto-pago').focus();
    });

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
</script>
@endsection
