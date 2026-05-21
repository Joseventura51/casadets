@extends('layouts.app')

@section('content')
@php
    $metodos = ['ninguno','efectivo','tarjeta','yape','plin','transferencia'];
    $metodoLabels = ['ninguno'=>'Ninguno (dejar pendiente)','efectivo'=>'Efectivo','tarjeta'=>'Tarjeta','yape'=>'Yape','plin'=>'Plin','transferencia'=>'Transferencia'];
@endphp

<style>
.pago-row { background:#f8f9fa; border-radius:8px; padding:.5rem .75rem; margin-bottom:.4rem; display:flex; gap:.5rem; align-items:center; }
.btn-add-pago { border:1.5px dashed #0d6efd; border-radius:8px; font-size:.82rem; padding:.3rem .9rem; color:#0d6efd; background:transparent; cursor:pointer; width:100%; margin-top:.3rem; }
.btn-add-pago:hover { background:#e8f0fe; }
.total-pill { font-size:1.4rem; font-weight:700; }
.diferencia-pill { font-size:.82rem; padding:.2rem .6rem; border-radius:20px; display:inline-block; }
</style>

<div id="toastContainer" style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;min-width:280px;"></div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0"><i class="bi bi-cash-stack me-2 text-success"></i>Verificar pago</h3>
        <p class="text-muted mb-0 small">
            Venta #{{ $venta->id }} · {{ $venta->fecha->format('d/m/Y') }} · {{ $venta->vendedor->nombre ?? '—' }}
            @if($venta->documento_numero)
                · <span class="badge bg-primary">{{ $venta->documento_numero }}</span>
            @endif
        </p>
    </div>
    <a href="/casadets/ventas/{{ $venta->id }}" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<div id="alertContainer"></div>

<div class="row g-3">

    {{-- Resumen productos --}}
    <div class="col-md-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-receipt me-1"></i> Productos de la venta
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Producto</th>
                            <th class="text-end">Cant.</th>
                            <th class="text-end">Precio</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($venta->detalles as $d)
                        <tr>
                            <td>{{ $d->producto }}</td>
                            <td class="text-end">{{ rtrim(rtrim(number_format($d->cantidad,2),'0'),'.') }}</td>
                            <td class="text-end">S/ {{ number_format($d->precio_unitario,2) }}</td>
                            <td class="text-end">S/ {{ number_format($d->subtotal,2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="table-light">
                            <th colspan="3" class="text-end">Total productos</th>
                            <th class="text-end">S/ {{ number_format($venta->total, 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- Verificar pago --}}
    <div class="col-md-7">
        <form id="formPago" action="/casadets/ventas/{{ $venta->id }}/pago" method="POST">
            @csrf
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-credit-card me-1"></i> Métodos de pago</span>
                    <div class="d-flex align-items-center gap-3">
                        <div>
                            <span class="text-muted small me-1">Total cobrado:</span>
                            <span class="total-pill text-primary" id="totalCobradoDisplay">S/ 0.00</span>
                        </div>
                        <div>
                            <span class="diferencia-pill bg-light text-muted" id="diferenciaPill">—</span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div id="pagosContainer">
                        @php
                            $metodosActuales = array_filter(explode(',', $venta->metodo_pago ?? ''));
                            $totalCobradoActual = $venta->total_cobrado;
                            $montoBase = count($metodosActuales) > 0
                                ? round($totalCobradoActual / count($metodosActuales), 2)
                                : round($totalCobradoActual, 2);
                            $primerosMetodos = !empty($metodosActuales) ? $metodosActuales : ['ninguno'];
                        @endphp
                        @foreach($primerosMetodos as $pi => $met)
                        <div class="pago-row">
                            <select name="pagos[{{ $pi }}][metodo]" class="form-select form-select-sm metodo-sel" style="flex:1;">
                                @foreach($metodos as $m)
                                    <option value="{{ $m }}" {{ trim($met)==$m ? 'selected' : '' }}>
                                        {{ $metodoLabels[$m] ?? ucfirst($m) }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="input-group input-group-sm" style="width:130px;">
                                <span class="input-group-text py-0 px-1 bg-white border-end-0 text-muted small">S/</span>
                                <input type="number" name="pagos[{{ $pi }}][monto]"
                                    value="{{ number_format($montoBase, 2, '.', '') }}"
                                    step="0.01" min="0"
                                    class="form-control form-control-sm text-end monto-pago border-start-0" required>
                            </div>
                            <button type="button" class="btn p-1 lh-1 text-danger border-0 bg-transparent btn-del-pago" style="font-size:1.1rem;" title="Quitar">
                                <i class="bi bi-x-circle-fill"></i>
                            </button>
                        </div>
                        @endforeach
                    </div>
                    <button type="button" class="btn-add-pago" id="btnAgregarPago">
                        <i class="bi bi-plus-lg me-1"></i> Agregar método de pago
                    </button>

                    <div class="mt-3 p-3 bg-light rounded">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="text-muted small">Total productos</div>
                                <div class="fw-semibold">S/ {{ number_format($venta->total, 2) }}</div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted small">Total cobrado</div>
                                <div class="fw-bold text-primary" id="totalResumen">S/ 0.00</div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted small">Diferencia</div>
                                <div id="difResumen" class="fw-semibold">—</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                    <a href="/casadets/ventas/{{ $venta->id }}" class="btn btn-outline-secondary">Cancelar</a>
                    <button id="btnGuardar" class="btn btn-success px-4">
                        <i class="bi bi-check-lg me-1"></i> Guardar pago
                    </button>
                </div>
            </div>
        </form>
    </div>

</div>

<script>
const METODOS       = @json($metodos);
const METODO_LABELS = @json($metodoLabels);
const TOTAL_REAL    = {{ (float) $venta->total }};
const VENTA_ID      = {{ $venta->id }};
let pagoIdx = {{ count($primerosMetodos) }};

function recalc() {
    let total = 0;
    // Solo suma métodos distintos a "ninguno"
    document.querySelectorAll('.monto-pago').forEach(i => {
        const row = i.closest('.pago-row');
        const metodo = row ? row.querySelector('select')?.value : '';
        if (metodo !== 'ninguno') total += parseFloat(i.value) || 0;
    });
    const d = total - TOTAL_REAL;
    document.getElementById('totalCobradoDisplay').textContent = 'S/ ' + total.toFixed(2);
    document.getElementById('totalResumen').textContent = 'S/ ' + total.toFixed(2);
    const pill = document.getElementById('diferenciaPill');
    const dRes = document.getElementById('difResumen');
    if (Math.abs(d) < 0.005) {
        pill.className = 'diferencia-pill bg-light text-muted'; pill.textContent = 'Sin diferencia';
        dRes.className = 'fw-semibold text-muted'; dRes.textContent = 'Exacto';
    } else if (d > 0) {
        pill.className = 'diferencia-pill bg-success text-white'; pill.textContent = '+S/ '+d.toFixed(2);
        dRes.className = 'fw-semibold text-success'; dRes.textContent = '+S/ '+d.toFixed(2);
    } else {
        pill.className = 'diferencia-pill bg-danger text-white'; pill.textContent = 'Faltan S/ '+Math.abs(d).toFixed(2);
        dRes.className = 'fw-semibold text-danger'; dRes.textContent = '-S/ '+Math.abs(d).toFixed(2);
    }
}

function reindex() {
    document.querySelectorAll('#pagosContainer .pago-row').forEach((row, i) => {
        row.querySelector('select').name = `pagos[${i}][metodo]`;
        row.querySelector('input').name  = `pagos[${i}][monto]`;
    });
    pagoIdx = document.querySelectorAll('#pagosContainer .pago-row').length;
}

function crearFila(met = 'ninguno', monto = '') {
    const div = document.createElement('div');
    div.className = 'pago-row';
    div.innerHTML = `
        <select name="pagos[${pagoIdx}][metodo]" class="form-select form-select-sm metodo-sel" style="flex:1;">
            ${METODOS.map(m=>`<option value="${m}" ${m===met?'selected':''}>${METODO_LABELS[m]||m}</option>`).join('')}
        </select>
        <div class="input-group input-group-sm" style="width:130px;">
            <span class="input-group-text py-0 px-1 bg-white border-end-0 text-muted small">S/</span>
            <input type="number" name="pagos[${pagoIdx}][monto]"
                value="${monto}" step="0.01" min="0"
                class="form-control form-control-sm text-end monto-pago border-start-0" required>
        </div>
        <button type="button" class="btn p-1 lh-1 text-danger border-0 bg-transparent btn-del-pago" style="font-size:1.1rem;" title="Quitar">
            <i class="bi bi-x-circle-fill"></i>
        </button>`;
    pagoIdx++;
    return div;
}

document.getElementById('pagosContainer').addEventListener('input', e => {
    if (e.target.classList.contains('monto-pago')) recalc();
});
document.getElementById('pagosContainer').addEventListener('click', e => {
    const btn = e.target.closest('.btn-del-pago');
    if (!btn) return;
    if (document.querySelectorAll('#pagosContainer .pago-row').length <= 1) {
        alert('Debe quedar al menos un método de pago.'); return;
    }
    btn.closest('.pago-row').remove();
    reindex(); recalc();
});
document.getElementById('btnAgregarPago').addEventListener('click', () => {
    const row = crearFila('efectivo', '');
    document.getElementById('pagosContainer').appendChild(row);
    row.querySelector('.monto-pago').focus();
});

// ── AJAX submit ───────────────────────────────────────────────
function showToast(msg, type = 'success') {
    const el = document.createElement('div');
    el.className = `alert alert-${type} shadow mb-2`;
    el.style.cssText = 'animation:fadeIn .2s;';
    el.innerHTML = `<i class="bi bi-${type==='success'?'check-circle':'exclamation-circle'} me-2"></i>${msg}`;
    document.getElementById('toastContainer').appendChild(el);
    setTimeout(() => el.remove(), 3500);
}

document.getElementById('formPago').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('btnGuardar');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando…';

    try {
        const res = await fetch(e.target.action, {
            method:  'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body:    new FormData(e.target),
        });
        const data = await res.json();

        if (!res.ok) {
            const msgs = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : (data.message || 'Error al guardar el pago.');
            throw new Error(msgs);
        }

        showToast('Pago guardado. Venta marcada como <strong>pagada</strong>.');
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Guardado';
        btn.className = 'btn btn-outline-success px-4';

        setTimeout(() => { window.location.href = `/casadets/ventas/${VENTA_ID}`; }, 1600);

    } catch (err) {
        showToast(err.message, 'danger');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Guardar pago';
    }
});

recalc();
</script>
@endsection
