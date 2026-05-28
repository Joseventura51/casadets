@extends('layouts.app')

@section('content')
@php
    $metodos = ['ninguno','efectivo','tarjeta','yape','plin','transferencia'];
    $metodoLabels = ['ninguno'=>'Ninguno (dejar pendiente)','efectivo'=>'Efectivo','tarjeta'=>'Tarjeta','yape'=>'Yape','plin'=>'Plin','transferencia'=>'Transferencia'];

    $yaPagedo      = (float) $venta->pagado;
    $totalVenta    = (float) $venta->total;
    $saldoPendiente = max(0, $totalVenta - $yaPagedo);
    $ventaPagada    = $venta->estado === 'pagado';

    $metodosActuales = array_filter(explode(',', $venta->metodo_pago ?? ''));
    $primerosMetodos = !empty($metodosActuales) ? $metodosActuales : ['ninguno'];
@endphp

<style>
.pago-row { background:#f8f9fa; border-radius:8px; padding:.5rem .75rem; margin-bottom:.4rem; }
.pago-row-top { display:flex; gap:.5rem; align-items:center; }
.pago-row-desc { padding:.3rem .75rem .1rem; display:none; }
.pago-row-desc.visible { display:block; }
.btn-add-pago { border:1.5px dashed #0d6efd; border-radius:8px; font-size:.82rem; padding:.3rem .9rem; color:#0d6efd; background:transparent; cursor:pointer; width:100%; margin-top:.3rem; }
.btn-add-pago:hover { background:#e8f0fe; }
.total-pill { font-size:1.4rem; font-weight:700; }
.diferencia-pill { font-size:.82rem; padding:.2rem .6rem; border-radius:20px; display:inline-block; }
.historial-row { font-size:.85rem; }
.banco-hint { font-size:.75rem; color:#6c757d; }
</style>

<div id="toastContainer" style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;min-width:300px;"></div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0"><i class="bi bi-cash-stack me-2 text-success"></i>Verificar pago</h3>
        <p class="text-muted mb-0 small">
            Venta #{{ $venta->id }} · {{ $venta->fecha->format('d/m/Y') }} · {{ $venta->vendedor->nombre ?? '—' }}
            @if($venta->documento_numero)
                · <span class="badge bg-primary">{{ $venta->documento_tipo }} {{ $venta->documento_numero }}</span>
            @endif
            · Estado:
            @php
                $badgeClass = match($venta->estado) {
                    'pagado'    => 'bg-success',
                    'parcial'   => 'bg-warning text-dark',
                    'anulado'   => 'bg-danger',
                    default     => 'bg-secondary',
                };
                $estadoLabel = match($venta->estado) {
                    'pagado'    => 'Pagado',
                    'parcial'   => 'Pago parcial',
                    'anulado'   => 'Anulado',
                    default     => 'Pendiente',
                };
            @endphp
            <span class="badge {{ $badgeClass }}">{{ $estadoLabel }}</span>
        </p>
    </div>
    <a href="/casadets/ventas/{{ $venta->id }}" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<div id="alertContainer"></div>

@if($ventaPagada)
<div class="alert alert-success d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-check-circle-fill fs-5"></i>
    <div>
        <strong>Esta venta ya está completamente pagada (S/ {{ number_format($yaPagedo, 2) }}).</strong>
        Cualquier nuevo pago que registres generará un <strong>saldo a favor</strong> para el cliente.
    </div>
</div>
@elseif($venta->estado === 'parcial')
<div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-hourglass-split fs-5"></i>
    <div>
        <strong>Pago parcial:</strong> se ha cobrado S/ {{ number_format($yaPagedo, 2) }} de S/ {{ number_format($totalVenta, 2) }}.
        Queda pendiente <strong>S/ {{ number_format($saldoPendiente, 2) }}</strong>.
    </div>
</div>
@endif

@if(isset($saldoFavor) && $saldoFavor > 0 && !$ventaPagada)
<div class="alert alert-info border-info mb-3 p-0 overflow-hidden">
    <div class="d-flex align-items-center gap-3 px-3 py-2">
        <i class="bi bi-wallet2 fs-4 text-info flex-shrink-0"></i>
        <div class="flex-grow-1">
            <strong>Saldo a favor disponible: S/ {{ number_format($saldoFavor, 2) }}</strong>
            <span class="text-muted small ms-2">— puedes aplicarlo a esta venta antes de cobrar</span>
        </div>
        <button class="btn btn-sm btn-info text-white flex-shrink-0"
            type="button" data-bs-toggle="collapse" data-bs-target="#panelSaldos">
            <i class="bi bi-chevron-down me-1"></i>Ver saldos
        </button>
    </div>
    <div class="collapse" id="panelSaldos">
        <div class="border-top border-info border-opacity-25 bg-white">
            <div id="saldosAplicarAlerta" class="mx-3 mt-2 d-none alert"></div>
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Origen</th>
                        <th>Fecha</th>
                        <th class="text-end">Disponible</th>
                        <th class="text-end pe-3">Aplicar</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($saldosDisponibles as $sf)
                    <tr>
                        <td class="ps-3 small text-muted">{{ $sf->descripcion ?? 'Saldo a favor' }}</td>
                        <td class="small text-muted">{{ $sf->fecha->format('d/m/Y') }}</td>
                        <td class="text-end fw-semibold text-info">S/ {{ number_format($sf->monto_disponible, 2) }}</td>
                        <td class="text-end pe-3">
                            <div class="d-flex align-items-center justify-content-end gap-1">
                                <div class="input-group input-group-sm" style="width:100px;">
                                    <span class="input-group-text py-0 px-1 bg-white border-end-0 text-muted small">S/</span>
                                    <input type="number" id="montoSaldo{{ $sf->id }}"
                                        value="{{ number_format(min((float)$sf->monto_disponible, $saldoPendiente), 2, '.', '') }}"
                                        step="0.01" min="0.01" max="{{ $sf->monto_disponible }}"
                                        class="form-control form-control-sm text-end border-start-0">
                                </div>
                                <button type="button"
                                    class="btn btn-sm btn-info text-white btn-aplicar-saldo"
                                    data-saldo-id="{{ $sf->id }}"
                                    data-disponible="{{ (float)$sf->monto_disponible }}"
                                    data-input="montoSaldo{{ $sf->id }}">
                                    <i class="bi bi-lightning-fill"></i> Usar
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="px-3 py-2 text-muted small border-top">
                Al aplicar un saldo se registra automáticamente en caja y se actualiza el estado de la venta.
            </div>
        </div>
    </div>
</div>
@endif

<div class="row g-3">

    {{-- Resumen productos --}}
    <div class="col-md-5">
        <div class="card border-0 shadow-sm">
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
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="3" class="text-end">Total venta</th>
                            <th class="text-end">S/ {{ number_format($totalVenta, 2) }}</th>
                        </tr>
                        @if($yaPagedo > 0)
                        <tr>
                            <td colspan="3" class="text-end text-muted small">Ya cobrado</td>
                            <td class="text-end text-success fw-semibold">S/ {{ number_format($yaPagedo, 2) }}</td>
                        </tr>
                        <tr>
                            <td colspan="3" class="text-end text-muted small">Saldo pendiente</td>
                            <td class="text-end text-{{ $saldoPendiente > 0 ? 'danger' : 'success' }} fw-semibold">
                                S/ {{ number_format($saldoPendiente, 2) }}
                            </td>
                        </tr>
                        @endif
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Historial de pagos anteriores --}}
        @if(isset($historial) && $historial->count() > 0)
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-clock-history me-1 text-secondary"></i> Historial de cobros
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle historial-row">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Método / Banco</th>
                            <th class="text-end">Aplicado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($historial as $h)
                        <tr>
                            <td>{{ $h->created_at->format('d/m/Y') }}</td>
                            <td>
                                @foreach($h->pago->metodos ?? [] as $met)
                                    <span class="badge bg-secondary">{{ ucfirst($met->metodo) }}</span>
                                    @if($met->descripcion)
                                        <span class="banco-hint">{{ $met->descripcion }}</span>
                                    @endif
                                @endforeach
                                @if(($h->pago->metodos ?? collect())->isEmpty())
                                    @foreach(explode(',', $h->pago->metodo_pago ?? '—') as $met)
                                        <span class="badge bg-secondary">{{ ucfirst(trim($met)) }}</span>
                                    @endforeach
                                @endif
                            </td>
                            <td class="text-end fw-semibold text-success">S/ {{ number_format($h->monto_aplicado, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="2" class="text-end">Total cobrado</th>
                            <th class="text-end">S/ {{ number_format($historial->sum('monto_aplicado'), 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @endif
    </div>

    {{-- Nuevo pago --}}
    <div class="col-md-7">
        <form id="formPago" action="/casadets/ventas/{{ $venta->id }}/pago" method="POST">
            @csrf
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                    <span>
                        <i class="bi bi-credit-card me-1"></i>
                        {{ $ventaPagada ? 'Registrar pago adicional' : 'Registrar pago' }}
                    </span>
                    <div class="d-flex align-items-center gap-3">
                        <div>
                            <span class="text-muted small me-1">Ingresando:</span>
                            <span class="total-pill text-primary" id="totalCobradoDisplay">S/ 0.00</span>
                        </div>
                        <div>
                            <span class="diferencia-pill bg-light text-muted" id="diferenciaPill">—</span>
                        </div>
                    </div>
                </div>
                <div class="card-body">

                    <div class="text-muted small mb-2">
                        <i class="bi bi-info-circle me-1"></i>
                        Para transferencias puedes indicar el banco o número de cuenta en el campo <strong>Banco / referencia</strong>.
                    </div>

                    <div id="pagosContainer">
                        @foreach($primerosMetodos as $pi => $met)
                        <div class="pago-row">
                            <div class="pago-row-top">
                                <select name="pagos[{{ $pi }}][metodo]" class="form-select form-select-sm metodo-sel" style="flex:1.2;">
                                    @foreach($metodos as $m)
                                        <option value="{{ $m }}" {{ trim($met)==$m ? 'selected' : '' }}>
                                            {{ $metodoLabels[$m] ?? ucfirst($m) }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="input-group input-group-sm" style="width:130px;">
                                    <span class="input-group-text py-0 px-1 bg-white border-end-0 text-muted small">S/</span>
                                    <input type="number" name="pagos[{{ $pi }}][monto]"
                                        value="{{ $pi === 0 && !$ventaPagada ? number_format($saldoPendiente ?: 0, 2, '.', '') : '' }}"
                                        step="0.01" min="0"
                                        class="form-control form-control-sm text-end monto-pago border-start-0" required>
                                </div>
                                <button type="button" class="btn p-1 lh-1 text-danger border-0 bg-transparent btn-del-pago" style="font-size:1.1rem;" title="Quitar">
                                    <i class="bi bi-x-circle-fill"></i>
                                </button>
                            </div>
                            <div class="pago-row-desc {{ in_array(trim($met), ['transferencia','tarjeta']) ? 'visible' : '' }}">
                                <input type="text" name="pagos[{{ $pi }}][descripcion]"
                                    class="form-control form-control-sm desc-pago"
                                    placeholder="Banco / referencia (ej: BCP Cta 1234-56)"
                                    maxlength="200">
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <button type="button" class="btn-add-pago" id="btnAgregarPago">
                        <i class="bi bi-plus-lg me-1"></i> Agregar método de pago
                    </button>

                    <div class="mt-3 p-3 bg-light rounded">
                        <div class="row text-center">
                            <div class="col-3">
                                <div class="text-muted small">Total venta</div>
                                <div class="fw-semibold">S/ {{ number_format($totalVenta, 2) }}</div>
                            </div>
                            <div class="col-3">
                                <div class="text-muted small">Ya cobrado</div>
                                <div class="fw-semibold text-success">S/ {{ number_format($yaPagedo, 2) }}</div>
                            </div>
                            <div class="col-3">
                                <div class="text-muted small">Este pago</div>
                                <div class="fw-bold text-primary" id="totalResumen">S/ 0.00</div>
                            </div>
                            <div class="col-3">
                                <div class="text-muted small">{{ $ventaPagada ? 'Saldo favor' : 'Diferencia' }}</div>
                                <div id="difResumen" class="fw-semibold">—</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <div class="d-flex justify-content-between align-items-center gap-3">
                        <a href="/casadets/ventas/{{ $venta->id }}" class="btn btn-outline-secondary">Cancelar</a>
                        <div class="d-flex align-items-center gap-2">
                            <label class="form-label mb-0 text-muted small text-nowrap">Estado al guardar:</label>
                            <select name="estado_manual" id="estadoManual" class="form-select form-select-sm" style="width:auto;">
                                <option value="" selected>Automático</option>
                                <option value="pendiente">⏳ Pendiente</option>
                                <option value="pagado">✔ Pagado</option>
                                <option value="anulado">✕ Anulado</option>
                            </select>
                            <span id="estadoAutoLabel" class="badge bg-secondary small text-nowrap">se calculará al guardar</span>
                        </div>
                        <button id="btnGuardar" class="btn btn-success px-4">
                            <i class="bi bi-check-lg me-1"></i> Guardar pago
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

</div>

<script>
const METODOS        = @json($metodos);
const METODO_LABELS  = @json($metodoLabels);
const TOTAL_REAL     = {{ (float) $totalVenta }};
const YA_PAGADO      = {{ (float) $yaPagedo }};
const SALDO_PENDIENTE = {{ (float) $saldoPendiente }};
const VENTA_PAGADA   = {{ $ventaPagada ? 'true' : 'false' }};
const VENTA_ID       = {{ $venta->id }};
let pagoIdx = {{ count($primerosMetodos) }};

// Métodos que muestran el campo banco/referencia
const METODOS_CON_DESC = ['transferencia', 'tarjeta'];

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
        const metodo = row ? row.querySelector('select')?.value : '';
        if (metodo !== 'ninguno') total += parseFloat(i.value) || 0;
    });

    document.getElementById('totalCobradoDisplay').textContent = 'S/ ' + total.toFixed(2);
    document.getElementById('totalResumen').textContent = 'S/ ' + total.toFixed(2);

    const pill  = document.getElementById('diferenciaPill');
    const dRes  = document.getElementById('difResumen');

    if (VENTA_PAGADA) {
        if (total > 0) {
            pill.className = 'diferencia-pill bg-info text-white';
            pill.textContent = 'Saldo favor +S/ ' + total.toFixed(2);
            dRes.className = 'fw-semibold text-info';
            dRes.textContent = '+S/ ' + total.toFixed(2);
        } else {
            pill.className = 'diferencia-pill bg-light text-muted'; pill.textContent = '—';
            dRes.className = 'fw-semibold text-muted'; dRes.textContent = '—';
        }
    } else {
        const nuevoTotal = YA_PAGADO + total;
        const d = nuevoTotal - TOTAL_REAL;
        if (Math.abs(d) < 0.005) {
            pill.className = 'diferencia-pill bg-success text-white'; pill.textContent = 'Pago exacto';
            dRes.className = 'fw-semibold text-success'; dRes.textContent = 'Exacto';
        } else if (d > 0) {
            pill.className = 'diferencia-pill bg-info text-white'; pill.textContent = 'Excede S/ '+d.toFixed(2);
            dRes.className = 'fw-semibold text-info'; dRes.textContent = '+S/ '+d.toFixed(2)+' (saldo favor)';
        } else {
            const falta = Math.abs(d);
            pill.className = 'diferencia-pill bg-danger text-white'; pill.textContent = 'Faltan S/ '+falta.toFixed(2);
            dRes.className = 'fw-semibold text-danger'; dRes.textContent = '-S/ '+falta.toFixed(2);
        }
    }
    updateAutoLabel(total);
}

function updateAutoLabel(nuevoPago) {
    const sel = document.getElementById('estadoManual');
    const lbl = document.getElementById('estadoAutoLabel');
    if (sel.value !== '') { lbl.style.display = 'none'; return; }
    lbl.style.display = '';

    if (VENTA_PAGADA) {
        if (nuevoPago > 0) {
            lbl.className = 'badge bg-info text-white small text-nowrap';
            lbl.textContent = '→ generará saldo a favor';
        } else {
            lbl.className = 'badge bg-secondary small text-nowrap';
            lbl.textContent = '→ sin cambios';
        }
        return;
    }

    const nuevoTotal = YA_PAGADO + nuevoPago;
    if (nuevoTotal >= TOTAL_REAL - 0.005 && nuevoPago > 0) {
        lbl.className = 'badge bg-success small text-nowrap';
        lbl.textContent = '→ se marcará Pagado';
    } else if (nuevoPago > 0) {
        const falta = TOTAL_REAL - nuevoTotal;
        lbl.className = 'badge bg-warning text-dark small text-nowrap';
        lbl.textContent = '→ quedará Pago parcial (falta S/ ' + Math.abs(falta).toFixed(2) + ')';
    } else {
        lbl.className = 'badge bg-secondary small text-nowrap';
        lbl.textContent = '→ quedará Pendiente';
    }
}

document.getElementById('estadoManual').addEventListener('change', () => {
    let total = 0;
    document.querySelectorAll('.monto-pago').forEach(i => {
        const row = i.closest('.pago-row');
        const metodo = row ? row.querySelector('select')?.value : '';
        if (metodo !== 'ninguno') total += parseFloat(i.value) || 0;
    });
    updateAutoLabel(total);
});

function reindex() {
    document.querySelectorAll('#pagosContainer .pago-row').forEach((row, i) => {
        const sel = row.querySelector('select.metodo-sel');
        const inp = row.querySelector('input.monto-pago');
        const desc = row.querySelector('input.desc-pago');
        if (sel)  sel.name  = `pagos[${i}][metodo]`;
        if (inp)  inp.name  = `pagos[${i}][monto]`;
        if (desc) desc.name = `pagos[${i}][descripcion]`;
    });
    pagoIdx = document.querySelectorAll('#pagosContainer .pago-row').length;
}

function crearFila(met = 'ninguno', monto = '') {
    const showDesc = METODOS_CON_DESC.includes(met);
    const opts = METODOS.map(m => `<option value="${m}" ${m===met?'selected':''}>${METODO_LABELS[m]||m}</option>`).join('');
    const div = document.createElement('div');
    div.className = 'pago-row';
    div.innerHTML = `
        <div class="pago-row-top">
            <select name="pagos[${pagoIdx}][metodo]" class="form-select form-select-sm metodo-sel" style="flex:1.2;">
                ${opts}
            </select>
            <div class="input-group input-group-sm" style="width:130px;">
                <span class="input-group-text py-0 px-1 bg-white border-end-0 text-muted small">S/</span>
                <input type="number" name="pagos[${pagoIdx}][monto]"
                    value="${monto}" step="0.01" min="0"
                    class="form-control form-control-sm text-end monto-pago border-start-0" required>
            </div>
            <button type="button" class="btn p-1 lh-1 text-danger border-0 bg-transparent btn-del-pago" style="font-size:1.1rem;" title="Quitar">
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
    if (e.target.classList.contains('monto-pago') || e.target.classList.contains('metodo-sel')) recalc();
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

// Inicializar toggleDesc en filas existentes
document.querySelectorAll('#pagosContainer .pago-row').forEach(toggleDesc);

// ── AJAX submit ────────────────────────────────────────────────
function showToast(msg, type = 'success') {
    const el = document.createElement('div');
    el.className = `alert alert-${type} shadow mb-2`;
    el.style.cssText = 'animation:fadeIn .2s;';
    el.innerHTML = `<i class="bi bi-${type==='success'?'check-circle':'exclamation-circle'} me-2"></i>${msg}`;
    document.getElementById('toastContainer').appendChild(el);
    setTimeout(() => el.remove(), 4500);
}

document.getElementById('formPago').addEventListener('submit', async (e) => {
    e.preventDefault();

    let totalNuevo = 0;
    document.querySelectorAll('.monto-pago').forEach(i => {
        const row = i.closest('.pago-row');
        const metodo = row ? row.querySelector('select')?.value : '';
        if (metodo !== 'ninguno') totalNuevo += parseFloat(i.value) || 0;
    });

    const btn = document.getElementById('btnGuardar');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando…';

    try {
        const fd  = new FormData(e.target);
        const res = await fetch(e.target.action, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': fd.get('_token') },
            body: fd,
        });
        const json = await res.json();

        if (json.success) {
            showToast('Pago guardado correctamente.' + (json.msg_saldo_favor ? ' ' + json.msg_saldo_favor : ''), 'success');
            setTimeout(() => { window.location.href = '/casadets/ventas/' + VENTA_ID; }, 1500);
        } else {
            const errs = json.errors ? Object.values(json.errors).flat().join(' ') : (json.message || 'Error desconocido');
            showToast(errs, 'danger');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Guardar pago';
        }
    } catch(err) {
        showToast('Error de red: ' + err.message, 'danger');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Guardar pago';
    }
});

// ── Aplicar saldo a favor (AJAX) ───────────────────────────────
document.querySelectorAll('.btn-aplicar-saldo').forEach(btn => {
    btn.addEventListener('click', async () => {
        const saldoId    = btn.dataset.saldoId;
        const disponible = parseFloat(btn.dataset.disponible);
        const inputEl    = document.getElementById(btn.dataset.input);
        const monto      = parseFloat(inputEl?.value) || 0;
        const alerta     = document.getElementById('saldosAplicarAlerta');

        if (monto <= 0 || monto > disponible) {
            alerta.className = 'mx-3 mt-2 alert alert-warning';
            alerta.textContent = 'Monto inválido (debe ser > 0 y ≤ disponible).';
            alerta.classList.remove('d-none');
            return;
        }

        btn.disabled = true;
        try {
            const res = await fetch(`/casadets/saldos-favor/${saldoId}/aplicar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content
                        || document.querySelector('input[name=_token]')?.value
                        || '',
                },
                body: JSON.stringify({ venta_id: VENTA_ID, monto }),
            });
            const json = await res.json();
            if (json.success) {
                showToast(`Saldo a favor aplicado: S/ ${monto.toFixed(2)}. ${json.estado_venta === 'pagado' ? '¡Venta pagada!' : ''}`, 'success');
                setTimeout(() => window.location.reload(), 1200);
            } else {
                alerta.className = 'mx-3 mt-2 alert alert-danger';
                alerta.textContent = json.message || 'Error al aplicar saldo.';
                alerta.classList.remove('d-none');
                btn.disabled = false;
            }
        } catch(err) {
            alerta.className = 'mx-3 mt-2 alert alert-danger';
            alerta.textContent = 'Error de red.';
            alerta.classList.remove('d-none');
            btn.disabled = false;
        }
    });
});
</script>
@endsection
