@extends('layouts.app')

@section('content')
@php
    $metodos = ['ninguno','efectivo','tarjeta','yape','plin','transferencia'];
    $metodoLabels = ['ninguno'=>'Ninguno','efectivo'=>'Efectivo','tarjeta'=>'Tarjeta','yape'=>'Yape','plin'=>'Plin','transferencia'=>'Transferencia'];
    $METODOS_CON_DESC = ['transferencia','tarjeta'];
@endphp

<style>
.pago-row { background:#f8f9fa; border-radius:8px; padding:.5rem .75rem; margin-bottom:.4rem; }
.pago-row-top { display:flex; gap:.5rem; align-items:center; }
.pago-row-desc { padding:.3rem .75rem .1rem; display:none; }
.pago-row-desc.visible { display:block; }
.btn-add-pago { border:1.5px dashed #0d6efd; border-radius:8px; font-size:.82rem; padding:.3rem .9rem; color:#0d6efd; background:transparent; cursor:pointer; width:100%; margin-top:.3rem; }
.btn-add-pago:hover { background:#e8f0fe; }
.total-pill { font-size:1.5rem; font-weight:700; }
.diferencia-pill { font-size:.82rem; padding:.2rem .6rem; border-radius:20px; display:inline-block; }
.venta-row-aplicado { font-size:.75rem; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0"><i class="bi bi-cash-stack me-2 text-success"></i>Pago múltiple</h3>
        <p class="text-muted mb-0 small">
            Cobrando <strong>{{ $ventas->count() }}</strong> ventas con un único pago
            — Total pendiente: <strong class="text-danger">S/ {{ number_format($totalPendiente, 2) }}</strong>
        </p>
    </div>
    <a href="/casadets/pendientes" class="btn btn-outline-secondary btn-sm">← Volver a pendientes</a>
</div>

@if($errors->any())
<div class="alert alert-danger">
    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
</div>
@endif

<div class="row g-3">

    {{-- Lista de ventas seleccionadas --}}
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-list-check me-1"></i> Ventas incluidas
                <span class="badge bg-primary ms-1">{{ $ventas->count() }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Doc.</th>
                            <th>Cliente</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Pendiente</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ventas as $v)
                        @php
                            $saldoV = max(0, (float) bcsub((string) $v->total, (string) $v->pagado, 2));
                        @endphp
                        <tr>
                            <td class="text-muted small">{{ $v->fecha->format('d/m/Y') }}</td>
                            <td class="small">
                                <span class="badge bg-secondary" style="font-size:.65rem;">
                                    {{ ucfirst($v->documento_tipo ?? '—') }}
                                </span>
                                {{ $v->documento_numero ?? '' }}
                            </td>
                            <td class="small">{{ $v->cliente->nombre ?? $v->vendedor->nombre ?? '—' }}</td>
                            <td class="text-end small">S/ {{ number_format($v->total, 2) }}</td>
                            <td class="text-end fw-semibold text-danger">S/ {{ number_format($saldoV, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="4" class="text-end">Total pendiente</th>
                            <th class="text-end text-danger">S/ {{ number_format($totalPendiente, 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="card-body py-2 border-top bg-light">
                <small class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    El pago se aplicará en orden de antigüedad hasta cubrir cada venta.
                </small>
            </div>
        </div>
    </div>

    {{-- Formulario de pago --}}
    <div class="col-md-6">
        <form id="formPagoMultiple" action="/casadets/ventas/pago-multiple" method="POST">
            @csrf
            {{-- Ventas seleccionadas (hidden) --}}
            @foreach($ventas as $v)
            <input type="hidden" name="ventas[]" value="{{ $v->id }}">
            @endforeach

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-credit-card me-1"></i> Registrar pago</span>
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted small">Total:</span>
                        <span class="total-pill text-primary" id="totalDisplay">S/ 0.00</span>
                        <span class="diferencia-pill bg-light text-muted" id="diferenciaPill">—</span>
                    </div>
                </div>
                <div class="card-body">

                    <div class="text-muted small mb-2">
                        <i class="bi bi-info-circle me-1"></i>
                        Para transferencias indica el banco o cuenta en <strong>Banco / referencia</strong>.
                    </div>

                    <div id="pagosContainer">
                        <div class="pago-row">
                            <div class="pago-row-top">
                                <select name="pagos[0][metodo]" class="form-select form-select-sm metodo-sel" style="flex:1.2;">
                                    @foreach($metodos as $m)
                                        <option value="{{ $m }}" {{ $m==='efectivo'?'selected':'' }}>{{ $metodoLabels[$m] }}</option>
                                    @endforeach
                                </select>
                                <div class="input-group input-group-sm" style="width:140px;">
                                    <span class="input-group-text py-0 px-1 bg-white border-end-0 text-muted small">S/</span>
                                    <input type="number" name="pagos[0][monto]"
                                        value="{{ number_format($totalPendiente, 2, '.', '') }}"
                                        step="0.01" min="0"
                                        class="form-control form-control-sm text-end monto-pago border-start-0" required>
                                </div>
                                <button type="button" class="btn p-1 lh-1 text-danger border-0 bg-transparent btn-del-pago" style="font-size:1.1rem;">
                                    <i class="bi bi-x-circle-fill"></i>
                                </button>
                            </div>
                            <div class="pago-row-desc">
                                <input type="text" name="pagos[0][descripcion]"
                                    class="form-control form-control-sm desc-pago"
                                    placeholder="Banco / referencia (ej: BCP Cta 1234-56)"
                                    maxlength="200">
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn-add-pago" id="btnAgregarPago">
                        <i class="bi bi-plus-lg me-1"></i> Agregar método de pago
                    </button>

                    {{-- Resumen distribución (simulación FIFO) --}}
                    <div class="mt-3 p-3 bg-light rounded" id="simulacionFIFO">
                        <div class="fw-semibold small mb-2 text-muted">
                            <i class="bi bi-calculator me-1"></i>Distribución estimada por venta:
                        </div>
                        <div id="fifoRows">
                            @foreach($ventas as $v)
                            @php $saldoV = max(0, (float) bcsub((string) $v->total, (string) $v->pagado, 2)); @endphp
                            <div class="d-flex justify-content-between align-items-center py-1 border-bottom"
                                 data-saldo="{{ $saldoV }}" data-id="{{ $v->id }}">
                                <span class="small">
                                    {{ ucfirst($v->documento_tipo ?? '') }} {{ $v->documento_numero ?? "#$v->id" }}
                                    <span class="text-muted">(falta S/ {{ number_format($saldoV, 2) }})</span>
                                </span>
                                <span class="badge venta-row-aplicado bg-secondary">—</span>
                            </div>
                            @endforeach
                        </div>
                        <div class="d-flex justify-content-between mt-2 small">
                            <span class="text-muted">Sobrante sin aplicar:</span>
                            <strong id="sobrante" class="text-muted">S/ 0.00</strong>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                    <a href="/casadets/pendientes" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-success px-4">
                        <i class="bi bi-check-lg me-1"></i> Confirmar pago múltiple
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
const METODOS       = @json($metodos);
const METODO_LABELS = @json($metodoLabels);
const TOTAL_PENDIENTE = {{ (float) $totalPendiente }};
const METODOS_CON_DESC = @json($METODOS_CON_DESC);
let pagoIdx = 1;

function toggleDesc(row) {
    const metodo = row.querySelector('select.metodo-sel')?.value ?? '';
    const desc   = row.querySelector('.pago-row-desc');
    if (!desc) return;
    if (METODOS_CON_DESC.includes(metodo)) {
        desc.classList.add('visible');
    } else {
        desc.classList.remove('visible');
        const inp = desc.querySelector('input');
        if (inp) inp.value = '';
    }
}

function recalc() {
    let total = 0;
    document.querySelectorAll('.monto-pago').forEach(i => {
        const row = i.closest('.pago-row');
        const met = row?.querySelector('select')?.value ?? '';
        if (met !== 'ninguno') total += parseFloat(i.value) || 0;
    });

    document.getElementById('totalDisplay').textContent = 'S/ ' + total.toFixed(2);

    const pill = document.getElementById('diferenciaPill');
    const dif  = total - TOTAL_PENDIENTE;
    if (Math.abs(dif) < 0.005) {
        pill.className = 'diferencia-pill bg-success text-white'; pill.textContent = 'Pago exacto';
    } else if (dif > 0) {
        pill.className = 'diferencia-pill bg-info text-white'; pill.textContent = 'Excede S/ ' + dif.toFixed(2);
    } else {
        pill.className = 'diferencia-pill bg-danger text-white'; pill.textContent = 'Faltan S/ ' + Math.abs(dif).toFixed(2);
    }

    // Simulación FIFO
    let restante = total;
    document.querySelectorAll('#fifoRows [data-saldo]').forEach(row => {
        const saldo = parseFloat(row.dataset.saldo);
        const badge = row.querySelector('.venta-row-aplicado');
        if (restante <= 0) {
            badge.className = 'badge venta-row-aplicado bg-secondary';
            badge.textContent = '—';
        } else if (restante >= saldo) {
            badge.className = 'badge venta-row-aplicado bg-success';
            badge.textContent = '✔ S/ ' + saldo.toFixed(2) + ' (completo)';
            restante = Math.round((restante - saldo) * 100) / 100;
        } else {
            badge.className = 'badge venta-row-aplicado bg-warning text-dark';
            badge.textContent = 'S/ ' + restante.toFixed(2) + ' (parcial)';
            restante = 0;
        }
    });
    document.getElementById('sobrante').textContent = 'S/ ' + Math.max(0, restante).toFixed(2);
}

function reindex() {
    document.querySelectorAll('#pagosContainer .pago-row').forEach((row, i) => {
        const sel  = row.querySelector('select.metodo-sel');
        const inp  = row.querySelector('input.monto-pago');
        const desc = row.querySelector('input.desc-pago');
        if (sel)  sel.name  = `pagos[${i}][metodo]`;
        if (inp)  inp.name  = `pagos[${i}][monto]`;
        if (desc) desc.name = `pagos[${i}][descripcion]`;
    });
    pagoIdx = document.querySelectorAll('#pagosContainer .pago-row').length;
}

function crearFila(met = 'transferencia', monto = '') {
    const showDesc = METODOS_CON_DESC.includes(met);
    const opts = METODOS.map(m => `<option value="${m}" ${m===met?'selected':''}>${METODO_LABELS[m]||m}</option>`).join('');
    const div = document.createElement('div');
    div.className = 'pago-row';
    div.innerHTML = `
        <div class="pago-row-top">
            <select name="pagos[${pagoIdx}][metodo]" class="form-select form-select-sm metodo-sel" style="flex:1.2;">
                ${opts}
            </select>
            <div class="input-group input-group-sm" style="width:140px;">
                <span class="input-group-text py-0 px-1 bg-white border-end-0 text-muted small">S/</span>
                <input type="number" name="pagos[${pagoIdx}][monto]"
                    value="${monto}" step="0.01" min="0"
                    class="form-control form-control-sm text-end monto-pago border-start-0" required>
            </div>
            <button type="button" class="btn p-1 lh-1 text-danger border-0 bg-transparent btn-del-pago" style="font-size:1.1rem;">
                <i class="bi bi-x-circle-fill"></i>
            </button>
        </div>
        <div class="pago-row-desc ${showDesc ? 'visible' : ''}">
            <input type="text" name="pagos[${pagoIdx}][descripcion]"
                class="form-control form-control-sm desc-pago"
                placeholder="Banco / referencia (ej: BCP Cta 1234-56)"
                maxlength="200">
        </div>`;
    pagoIdx++;
    return div;
}

document.getElementById('pagosContainer').addEventListener('input', e => {
    if (e.target.classList.contains('monto-pago')) recalc();
});
document.getElementById('pagosContainer').addEventListener('change', e => {
    if (e.target.classList.contains('metodo-sel')) {
        toggleDesc(e.target.closest('.pago-row'));
        recalc();
    }
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
    const row = crearFila('transferencia', '');
    document.getElementById('pagosContainer').appendChild(row);
    row.querySelector('.monto-pago').focus();
});

// Inicializar
document.querySelectorAll('#pagosContainer .pago-row').forEach(toggleDesc);
recalc();
</script>
@endsection
