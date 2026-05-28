@extends('layouts.app')

@section('content')
@php
    $metodos = ['ninguno','efectivo','tarjeta','yape','plin','transferencia'];
    $metodoLabels = ['ninguno'=>'Ninguno (dejar pendiente)','efectivo'=>'Efectivo','tarjeta'=>'Tarjeta','yape'=>'Yape','plin'=>'Plin','transferencia'=>'Transferencia'];

    $yaPagedo       = (float) $venta->pagado;
    $totalVenta     = (float) $venta->total;
    $saldoPendiente = max(0, $totalVenta - $yaPagedo);
    $ventaPagada    = $venta->estado === 'pagado';

    $metodosActuales = array_filter(explode(',', $venta->metodo_pago ?? ''));
    $primerosMetodos = !empty($metodosActuales) ? $metodosActuales : ['ninguno'];
@endphp

<style>
.pago-header-total { min-width:0; }
.pago-header-total .total-pill { line-height:1; }
.pago-row {
    background:#fff;
    border:1px solid #dee2e6;
    border-left:4px solid #0d6efd;
    border-radius:8px;
    padding:.75rem;
    margin-bottom:.55rem;
    box-shadow:0 1px 2px rgba(0,0,0,.04);
}
.pago-row-top {
    display:grid;
    grid-template-columns:32px minmax(130px,1.25fr) minmax(108px,.85fr) 34px;
    gap:.55rem;
    align-items:end;
}
.pago-row-index {
    width:28px;
    height:28px;
    border-radius:50%;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    background:#e8f0fe;
    color:#0d6efd;
    font-size:.76rem;
    font-weight:700;
    margin-bottom:.16rem;
}
.pago-field-label {
    display:block;
    margin-bottom:.18rem;
    color:#6c757d;
    font-size:.68rem;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.02em;
}
.pago-row-desc { padding:.55rem 0 0 2.55rem; display:none; }
.pago-row-desc.visible { display:block; }
.btn-del-pago {
    width:30px;
    height:30px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border-radius:8px;
}
.btn-del-pago:hover { background:#f8d7da !important; }
.btn-add-pago { border:1.5px dashed #0d6efd; border-radius:8px; font-size:.82rem; padding:.55rem .9rem; color:#0d6efd; background:#fff; cursor:pointer; width:100%; margin-top:.35rem; font-weight:600; }
.btn-add-pago:hover { background:#e8f0fe; }
.pago-resumen { border:1px solid #dee2e6; border-radius:8px; }
.total-pill { font-size:1.3rem; font-weight:700; }
.diferencia-pill { font-size:.78rem; padding:.2rem .5rem; border-radius:20px; display:inline-block; white-space:nowrap; }
.historial-row { font-size:.85rem; }
.banco-hint { font-size:.73rem; color:#6c757d; }
.vale-adicional-card { border:1.5px solid #dee2e6; border-radius:8px; padding:.5rem .75rem; margin-bottom:.4rem; cursor:pointer; transition:background .1s, border-color .1s; }
.vale-adicional-card:hover { background:#f0f4ff; border-color:#0d6efd; }
.vale-adicional-card.seleccionado { background:#e8f0fe; border-color:#0d6efd; }
.vale-adicional-card.seleccionado .vale-check { color:#0d6efd; }
@media (max-width: 576px) {
    .pago-row-top { grid-template-columns:32px 1fr 34px; }
    .pago-monto-field { grid-column:2 / 3; }
    .pago-row-desc { padding-left:0; }
    .pago-header-total { width:100%; justify-content:flex-start !important; margin-top:.35rem; }
}
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
                $badgeClass = match($venta->estado) { 'pagado'=>'bg-success','parcial'=>'bg-warning text-dark','anulado'=>'bg-danger',default=>'bg-secondary' };
                $estadoLabel = match($venta->estado) { 'pagado'=>'Pagado','parcial'=>'Pago parcial','anulado'=>'Anulado',default=>'Pendiente' };
            @endphp
            <span class="badge {{ $badgeClass }}">{{ $estadoLabel }}</span>
        </p>
    </div>
    <a href="/casadets/ventas/{{ $venta->id }}" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

@if($ventaPagada)
<div class="alert alert-success d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-check-circle-fill fs-5"></i>
    <div>
        <strong>Esta venta ya está completamente pagada (S/ {{ number_format($yaPagedo, 2) }}).</strong>
        Cualquier nuevo pago generará un <strong>saldo a favor</strong> para el cliente.
    </div>
</div>
@elseif($venta->estado === 'parcial')
<div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-hourglass-split fs-5"></i>
    <div>
        <strong>Pago parcial:</strong> se cobró S/ {{ number_format($yaPagedo, 2) }} de S/ {{ number_format($totalVenta, 2) }}.
        Queda <strong>S/ {{ number_format($saldoPendiente, 2) }}</strong> pendiente.
    </div>
</div>
@endif

@if(isset($saldoFavor) && $saldoFavor > 0 && !$ventaPagada)
<div class="alert alert-info border-info mb-3 p-0 overflow-hidden">
    <div class="d-flex align-items-center gap-3 px-3 py-2">
        <i class="bi bi-wallet2 fs-4 text-info flex-shrink-0"></i>
        <div class="flex-grow-1">
            <strong>Saldo a favor disponible: S/ {{ number_format($saldoFavor, 2) }}</strong>
            <span class="text-muted small ms-2">— puedes aplicarlo antes de cobrar</span>
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
                        <th class="ps-3">Origen</th><th>Fecha</th>
                        <th class="text-end">Disponible</th><th class="text-end pe-3">Aplicar</th>
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
                Al aplicar un saldo se registra automáticamente y se actualiza el estado de la venta.
            </div>
        </div>
    </div>
</div>
@endif

<div id="alertContainer"></div>

{{-- ═══════════════════════════════════════════════════════════════ --}}
<div class="row g-3">

    {{-- ── Columna izquierda: productos + vales adicionales ──────── --}}
    <div class="col-md-5">

        {{-- Tabla de productos --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-receipt me-1"></i> Productos
                @if($venta->documento_numero)
                    <span class="badge bg-primary ms-1 fw-normal">{{ $venta->documento_tipo }} {{ $venta->documento_numero }}</span>
                @endif
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

        {{-- Vales adicionales del mismo cliente --}}
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white d-flex align-items-center justify-content-between">
                <span class="fw-semibold small">
                    <i class="bi bi-files me-1 text-primary"></i>
                    Otros vales del cliente
                    @if(isset($ventasPendientesCliente) && $ventasPendientesCliente->count() > 0)
                        <span class="badge bg-primary ms-1">{{ $ventasPendientesCliente->count() }}</span>
                    @endif
                </span>
                @if(isset($ventasPendientesCliente) && $ventasPendientesCliente->count() > 0)
                <span class="text-muted" style="font-size:.72rem;">Marca los que quieras cobrar juntos</span>
                @endif
            </div>
            <div class="card-body p-2">
                @if(!isset($ventasPendientesCliente) || !$venta->cliente_id)
                    <div class="text-muted small text-center py-2">
                        <i class="bi bi-person-x me-1"></i> Esta venta no tiene cliente asignado.
                    </div>
                @elseif($ventasPendientesCliente->isEmpty())
                    <div class="text-muted small text-center py-2">
                        <i class="bi bi-check-all me-1 text-success"></i> No hay más vales pendientes de este cliente.
                    </div>
                @else
                    @if($ventasPendientesCliente->count() > 3)
                    <input type="text" id="buscarVale"
                        class="form-control form-control-sm mb-2"
                        placeholder="Buscar por doc, fecha…">
                    @endif
                    <div id="listaVales">
                        @foreach($ventasPendientesCliente as $vp)
                        @php
                            $docVp = trim(ucfirst($vp->documento_tipo ?? '').' '.($vp->documento_numero ?? "#$vp->id"));
                            $buscarVp = strtolower($docVp.' '.$vp->fecha->format('d/m/Y'));
                        @endphp
                        <div class="vale-adicional-card"
                             data-buscar="{{ $buscarVp }}"
                             data-id="{{ $vp->id }}"
                             onclick="toggleVale(this)">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-square vale-check fs-5 text-muted flex-shrink-0"></i>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-semibold small">
                                        {{ $docVp }}
                                        <span class="badge {{ $vp->estado==='parcial' ? 'bg-warning text-dark' : 'bg-secondary' }} ms-1" style="font-size:.6rem;">
                                            {{ $vp->estado==='parcial' ? 'Parcial' : 'Pendiente' }}
                                        </span>
                                    </div>
                                    <div class="text-muted" style="font-size:.72rem;">{{ $vp->fecha->format('d/m/Y') }}</div>
                                </div>
                                <div class="text-end flex-shrink-0">
                                    <div class="fw-bold text-danger" style="font-size:.85rem;">S/ {{ number_format($vp->saldo_pendiente, 2) }}</div>
                                    @if((float)$vp->pagado > 0)
                                    <div class="text-muted" style="font-size:.65rem;">Total: S/ {{ number_format($vp->total, 2) }}</div>
                                    @endif
                                </div>
                                <input type="hidden" class="vale-id-hidden" value="{{ $vp->id }}">
                                <input type="hidden" class="vale-saldo-hidden" value="{{ $vp->saldo_pendiente }}">
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <div id="resumenAdicionales" class="mt-2 px-1 d-flex justify-content-between align-items-center" style="display:none !important;">
                        <span class="small text-muted"><strong class="text-primary" id="countAdicionales">0</strong> vale(s) adicional(es)</span>
                        <span class="fw-bold text-primary small" id="totalAdicionalesLabel">S/ 0.00</span>
                    </div>
                @endif
            </div>
        </div>

    </div>{{-- /col izquierda --}}

    {{-- ── Columna derecha: formulario + historial ───────────────── --}}
    <div class="col-md-7">

        {{-- Formulario de pago --}}
        <form id="formPago" action="/casadets/ventas/{{ $venta->id }}/pago" method="POST">
            @csrf
            <div id="ventasAdicionalesHidden"></div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-credit-card me-1"></i>
                        {{ $ventaPagada ? 'Registrar pago adicional' : 'Registrar pago' }}
                    </span>
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted small">Total:</span>
                        <span class="total-pill text-primary" id="totalCobradoDisplay">S/ 0.00</span>
                        <span class="diferencia-pill bg-light text-muted" id="diferenciaPill">—</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="text-muted small mb-2">
                        <i class="bi bi-info-circle me-1"></i>
                        Para transferencias, Yape o Plin indica el destino en <strong>Destino / referencia</strong>.
                    </div>

                    <div id="pagosContainer">
                        @foreach($primerosMetodos as $pi => $met)
                        <div class="pago-row">
                            <div class="pago-row-top">
                                <span class="pago-row-index">{{ $pi + 1 }}</span>
                                <div>
                                    <label class="pago-field-label">Método</label>
                                    <select name="pagos[{{ $pi }}][metodo]" class="form-select form-select-sm metodo-sel">
                                        @foreach($metodos as $m)
                                            <option value="{{ $m }}" {{ trim($met)==$m ? 'selected' : '' }}>
                                                {{ $metodoLabels[$m] ?? ucfirst($m) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="pago-monto-field">
                                    <label class="pago-field-label">Monto</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text py-0 px-1 bg-white border-end-0 text-muted small">S/</span>
                                        <input type="number" name="pagos[{{ $pi }}][monto]"
                                            value="{{ $pi === 0 && !$ventaPagada ? number_format($saldoPendiente ?: 0, 2, '.', '') : '' }}"
                                            step="0.01" min="0"
                                            class="form-control form-control-sm text-end monto-pago border-start-0" required>
                                    </div>
                                </div>
                                <button type="button" class="btn p-1 lh-1 text-danger border-0 bg-transparent btn-del-pago" title="Quitar">
                                    <i class="bi bi-x-circle-fill"></i>
                                </button>
                            </div>
                            <div class="pago-row-desc {{ in_array(trim($met), ['transferencia','tarjeta','yape','plin']) ? 'visible' : '' }}">
                                <label class="pago-field-label">Destino / referencia</label>
                                <input type="text" name="pagos[{{ $pi }}][descripcion]"
                                    class="form-control form-control-sm desc-pago"
                                    placeholder="Destino / referencia (ej: BCP, Yape Juan, Plin tienda)"
                                    maxlength="200">
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <button type="button" class="btn-add-pago" id="btnAgregarPago">
                        <i class="bi bi-plus-lg me-1"></i> Agregar método de pago
                    </button>

                    <div class="mt-3 p-2 bg-light pago-resumen">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="text-muted" style="font-size:.72rem;">Pendiente total</div>
                                <div class="fw-bold text-danger" id="pendienteTotalDisplay">S/ {{ number_format($saldoPendiente, 2) }}</div>
                                <div id="labelAdicionales" class="text-primary" style="font-size:.65rem;display:none;">
                                    +<span id="numAdicionalesLabel">0</span> vale(s)
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted" style="font-size:.72rem;">Este pago</div>
                                <div class="fw-bold text-primary" id="totalResumen">S/ 0.00</div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted" style="font-size:.72rem;">Diferencia</div>
                                <div id="difResumen" class="fw-semibold text-muted">—</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                        <a href="/casadets/ventas/{{ $venta->id }}" class="btn btn-outline-secondary btn-sm">Cancelar</a>
                        <div class="d-flex align-items-center gap-2">
                            <select name="estado_manual" id="estadoManual" class="form-select form-select-sm" style="width:auto;">
                                <option value="" selected>Automático</option>
                                <option value="pendiente">⏳ Pendiente</option>
                                <option value="pagado">✔ Pagado</option>
                                <option value="anulado">✕ Anulado</option>
                            </select>
                            <span id="estadoAutoLabel" class="badge bg-secondary small text-nowrap">se calculará al guardar</span>
                        </div>
                        <button id="btnGuardar" class="btn btn-success px-3">
                            <i class="bi bi-check-lg me-1"></i> Guardar pago
                        </button>
                    </div>
                </div>
            </div>
        </form>

        {{-- Historial de cobros (debajo del formulario) --}}
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
                            <th>Método / Destino</th>
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

    </div>{{-- /col derecha --}}

</div>{{-- /row --}}

<script>
const METODOS        = @json($metodos);
const METODO_LABELS  = @json($metodoLabels);
const TOTAL_REAL     = {{ (float) $totalVenta }};
const YA_PAGADO      = {{ (float) $yaPagedo }};
const SALDO_PENDIENTE = {{ (float) $saldoPendiente }};
const VENTA_PAGADA    = {{ $ventaPagada ? 'true' : 'false' }};
const VENTA_ID        = {{ $venta->id }};
const METODOS_CON_DESC = ['transferencia', 'tarjeta', 'yape', 'plin'];
let pagoIdx = {{ count($primerosMetodos) }};
let saldoAdicionalSel = 0;

// ── Toggle de tarjeta de vale ──────────────────────────────────
function toggleVale(card) {
    card.classList.toggle('seleccionado');
    const icon = card.querySelector('.vale-check');
    if (card.classList.contains('seleccionado')) {
        icon.className = 'bi bi-check-square-fill vale-check fs-5 text-primary flex-shrink-0';
    } else {
        icon.className = 'bi bi-square vale-check fs-5 text-muted flex-shrink-0';
    }
    actualizarValesAdicionales();
}

function actualizarValesAdicionales() {
    const selCards = document.querySelectorAll('.vale-adicional-card.seleccionado');
    saldoAdicionalSel = 0;

    const hidden = document.getElementById('ventasAdicionalesHidden');
    hidden.innerHTML = '';

    selCards.forEach(card => {
        const id     = card.querySelector('.vale-id-hidden').value;
        const saldo  = parseFloat(card.querySelector('.vale-saldo-hidden').value) || 0;
        saldoAdicionalSel += saldo;
        const h = document.createElement('input');
        h.type = 'hidden'; h.name = 'ventas_adicionales[]'; h.value = id;
        hidden.appendChild(h);
    });

    saldoAdicionalSel = Math.round(saldoAdicionalSel * 100) / 100;
    const saldoCombinado = SALDO_PENDIENTE + saldoAdicionalSel;

    // Actualizar panel de resumen izquierdo
    const resumen = document.getElementById('resumenAdicionales');
    if (selCards.length > 0 && resumen) {
        resumen.style.setProperty('display', 'flex', 'important');
        document.getElementById('countAdicionales').textContent = selCards.length;
        document.getElementById('totalAdicionalesLabel').textContent = 'S/ ' + saldoAdicionalSel.toFixed(2);
    } else if (resumen) {
        resumen.style.setProperty('display', 'none', 'important');
    }

    // Actualizar "Pendiente total" en resumen del formulario
    document.getElementById('pendienteTotalDisplay').textContent = 'S/ ' + saldoCombinado.toFixed(2);
    const lbl = document.getElementById('labelAdicionales');
    if (selCards.length > 0) {
        lbl.style.display = '';
        document.getElementById('numAdicionalesLabel').textContent = selCards.length;
    } else {
        lbl.style.display = 'none';
    }

    // Auto-rellenar el primer monto con el nuevo saldo combinado
    const primerMonto = document.querySelector('.monto-pago');
    if (primerMonto) primerMonto.value = saldoCombinado.toFixed(2);

    recalc();
}

// ── Descripción banco ──────────────────────────────────────────
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

// ── Recalcular totales ─────────────────────────────────────────
function recalc() {
    let total = 0;
    document.querySelectorAll('.monto-pago').forEach(i => {
        const row = i.closest('.pago-row');
        const met = row?.querySelector('select')?.value ?? '';
        if (met !== 'ninguno') total += parseFloat(i.value) || 0;
    });

    document.getElementById('totalCobradoDisplay').textContent = 'S/ ' + total.toFixed(2);
    document.getElementById('totalResumen').textContent = 'S/ ' + total.toFixed(2);

    const pill = document.getElementById('diferenciaPill');
    const dRes = document.getElementById('difResumen');
    const saldoCombinado = SALDO_PENDIENTE + saldoAdicionalSel;

    if (VENTA_PAGADA && saldoAdicionalSel <= 0) {
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
        const d = total - saldoCombinado;
        if (Math.abs(d) < 0.005) {
            pill.className = 'diferencia-pill bg-success text-white'; pill.textContent = 'Pago exacto';
            dRes.className = 'fw-semibold text-success'; dRes.textContent = 'Exacto';
        } else if (d > 0) {
            pill.className = 'diferencia-pill bg-info text-white'; pill.textContent = 'Excede S/ '+d.toFixed(2);
            dRes.className = 'fw-semibold text-info'; dRes.textContent = '+S/ '+d.toFixed(2);
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
    const saldoCombinado = SALDO_PENDIENTE + saldoAdicionalSel;
    if (VENTA_PAGADA && saldoAdicionalSel <= 0) {
        lbl.className = nuevoPago > 0
            ? 'badge bg-info text-white small text-nowrap'
            : 'badge bg-secondary small text-nowrap';
        lbl.textContent = nuevoPago > 0 ? '→ generará saldo a favor' : '→ sin cambios';
        return;
    }
    if (nuevoPago >= saldoCombinado - 0.005 && nuevoPago > 0) {
        lbl.className = 'badge bg-success small text-nowrap';
        lbl.textContent = '→ se marcarán Pagados';
    } else if (nuevoPago > 0) {
        lbl.className = 'badge bg-warning text-dark small text-nowrap';
        lbl.textContent = '→ pago parcial (falta S/ ' + Math.max(0, saldoCombinado - nuevoPago).toFixed(2) + ')';
    } else {
        lbl.className = 'badge bg-secondary small text-nowrap';
        lbl.textContent = '→ quedará Pendiente';
    }
}

document.getElementById('estadoManual').addEventListener('change', () => {
    let total = 0;
    document.querySelectorAll('.monto-pago').forEach(i => {
        const row = i.closest('.pago-row');
        if (row?.querySelector('select')?.value !== 'ninguno') total += parseFloat(i.value) || 0;
    });
    updateAutoLabel(total);
});

// ── Reindexar y eventos del contenedor ────────────────────────
function reindex() {
    document.querySelectorAll('#pagosContainer .pago-row').forEach((row, i) => {
        const num  = row.querySelector('.pago-row-index');
        const sel  = row.querySelector('select.metodo-sel');
        const inp  = row.querySelector('input.monto-pago');
        const desc = row.querySelector('input.desc-pago');
        if (num)  num.textContent  = i + 1;
        if (sel)  sel.name         = `pagos[${i}][metodo]`;
        if (inp)  inp.name         = `pagos[${i}][monto]`;
        if (desc) desc.name        = `pagos[${i}][descripcion]`;
    });
    pagoIdx = document.querySelectorAll('#pagosContainer .pago-row').length;
}

function crearFila(met = 'transferencia') {
    const showDesc = METODOS_CON_DESC.includes(met);
    const opts = METODOS.map(m => `<option value="${m}" ${m===met?'selected':''}>${METODO_LABELS[m]||m}</option>`).join('');
    const div  = document.createElement('div');
    div.className = 'pago-row';
    div.innerHTML = `
        <div class="pago-row-top">
            <span class="pago-row-index">${pagoIdx+1}</span>
            <div>
                <label class="pago-field-label">Método</label>
                <select name="pagos[${pagoIdx}][metodo]" class="form-select form-select-sm metodo-sel">${opts}</select>
            </div>
            <div class="pago-monto-field">
                <label class="pago-field-label">Monto</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text py-0 px-1 bg-white border-end-0 text-muted small">S/</span>
                    <input type="number" name="pagos[${pagoIdx}][monto]"
                        value="" step="0.01" min="0"
                        class="form-control form-control-sm text-end monto-pago border-start-0" required>
                </div>
            </div>
            <button type="button" class="btn p-1 lh-1 text-danger border-0 bg-transparent btn-del-pago" title="Quitar">
                <i class="bi bi-x-circle-fill"></i>
            </button>
        </div>
        <div class="pago-row-desc ${showDesc ? 'visible' : ''}">
            <label class="pago-field-label">Destino / referencia</label>
            <input type="text" name="pagos[${pagoIdx}][descripcion]"
                class="form-control form-control-sm desc-pago"
                placeholder="Destino / referencia (ej: BCP, Yape Juan, Plin tienda)"
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
    const row = crearFila('transferencia');
    document.getElementById('pagosContainer').appendChild(row);
    row.querySelector('.monto-pago').focus();
    recalc();
});

document.querySelectorAll('#pagosContainer .pago-row').forEach(toggleDesc);

// ── Buscador de vales (si hay > 3) ────────────────────────────
const buscarValeEl = document.getElementById('buscarVale');
if (buscarValeEl) {
    function normalizar(s) {
        return (s||'').toLowerCase()
            .replace(/[áàä]/g,'a').replace(/[éèë]/g,'e')
            .replace(/[íìï]/g,'i').replace(/[óòö]/g,'o')
            .replace(/[úùü]/g,'u').replace(/ñ/g,'n');
    }
    buscarValeEl.addEventListener('input', function() {
        const q = normalizar(this.value.trim());
        document.querySelectorAll('.vale-adicional-card').forEach(card => {
            const txt = normalizar(card.dataset.buscar || '');
            card.style.display = (!q || txt.includes(q)) ? '' : 'none';
        });
    });
}

// ── Toast ──────────────────────────────────────────────────────
function showToast(msg, type = 'success') {
    const el = document.createElement('div');
    el.className = `alert alert-${type} shadow mb-2`;
    el.innerHTML = `<i class="bi bi-${type==='success'?'check-circle':'exclamation-circle'} me-2"></i>${msg}`;
    document.getElementById('toastContainer').appendChild(el);
    setTimeout(() => el.remove(), 5000);
}

// ── Envío AJAX ─────────────────────────────────────────────────
document.getElementById('formPago').addEventListener('submit', async (e) => {
    e.preventDefault();
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
            const extra = json.msg_saldo_favor ? ' ' + json.msg_saldo_favor : '';
            showToast('Pago guardado correctamente.' + extra, 'success');
            setTimeout(() => { window.location.href = '/casadets/ventas/' + VENTA_ID; }, 1600);
        } else {
            const errs = json.errors
                ? Object.values(json.errors).flat().join(' ')
                : (json.message || 'Error desconocido');
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

// ── Aplicar saldo a favor ──────────────────────────────────────
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
            alerta.classList.remove('d-none'); return;
        }
        btn.disabled = true;
        try {
            const res = await fetch(`/casadets/saldos-favor/${saldoId}/aplicar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content
                        || document.querySelector('input[name=_token]')?.value || '',
                },
                body: JSON.stringify({ venta_id: VENTA_ID, monto }),
            });
            const json = await res.json();
            if (json.success) {
                showToast(`Saldo aplicado: S/ ${monto.toFixed(2)}.`, 'success');
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

recalc();
</script>
@endsection
