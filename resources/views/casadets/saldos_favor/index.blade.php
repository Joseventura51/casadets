@extends('layouts.app')

@section('content')
<style>
.saldo-card { border-left: 4px solid #0dcaf0; }
.badge-disponible     { background:#d1ecf1; color:#0c5460; border:1px solid #bee5eb; }
.badge-parcial        { background:#fff3cd; color:#856404; border:1px solid #ffc107; }
.badge-usado          { background:#e2e3e5; color:#383d41; border:1px solid #c8c9ca; }
.badge-manual         { background:#e8d5f5; color:#5a2d82; border:1px solid #c9a0dc; }
.badge-nc             { background:#ffecd2; color:#7c3a00; border:1px solid #f5b97e; }
.badge-excedente      { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
.saldo-row-highlight  { background:#f0fbff !important; }
.saldo-item-selectable { cursor:pointer; transition: background .15s; border:2px solid transparent; border-radius:8px; }
.saldo-item-selectable:hover { background:#f0f9ff; border-color:#0dcaf0; }
.saldo-item-selectable.selected { background:#e0f5fc; border-color:#0dcaf0; }
.kpi-box { border-radius:12px; padding:1.1rem 1.4rem; }
.nc-row { transition: background .15s; }
.nc-row:hover { background: #fff8f0 !important; }
</style>

{{-- ══════════════════════════════════════════════════════════
     Modal 1: Aplicar saldo existente a una venta
     ══════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="modalAplicar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-wallet2 me-2 text-info"></i>Aplicar saldo a venta
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="modalAlerta" class="alert d-none mb-3"></div>

                {{-- Info del cliente --}}
                <div class="mb-3 p-3 bg-light rounded">
                    <div class="fw-semibold mb-1" id="mClienteNombre">—</div>
                    <div class="text-muted small">Saldo total disponible:
                        <span class="fw-bold text-info" id="mSaldoTotalDisponible">S/ 0.00</span>
                    </div>
                </div>

                {{-- Paso 1: elegir saldo --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold">1. Saldo a usar</label>
                    <div id="mSaldosLista" class="d-flex flex-column gap-2">
                        <div class="text-muted small text-center py-2">
                            <div class="spinner-border spinner-border-sm me-1"></div>Cargando…
                        </div>
                    </div>
                </div>

                {{-- Paso 2: elegir venta --}}
                <div class="mb-3" id="mVentaBloque" style="display:none">
                    <label class="form-label fw-semibold">2. Venta a cobrar</label>
                    <select id="mVentaId" class="form-select">
                        <option value="">Cargando…</option>
                    </select>
                    <div class="text-muted small mt-1" id="mVentaInfo"></div>
                </div>

                {{-- Paso 3: monto --}}
                <div class="mb-3" id="mMontoBloque" style="display:none">
                    <label class="form-label fw-semibold">3. Monto a aplicar</label>
                    <div class="input-group">
                        <span class="input-group-text">S/</span>
                        <input type="number" id="mMonto" class="form-control text-end" step="0.01" min="0.01">
                    </div>
                    <div class="d-flex gap-2 mt-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnUsarTodoSaldo">
                            Usar todo el saldo seleccionado
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnCubrirDeuda">
                            Cubrir deuda exacta
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-info text-white" id="btnConfirmarAplicar" disabled>
                    <i class="bi bi-check-lg me-1"></i>Confirmar aplicación
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════
     Modal 2: Crear saldo a favor manualmente
     ══════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="modalNuevoSaldo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2 text-success"></i>Nuevo saldo a favor manual
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="/casadets/saldos-favor/crear" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info small py-2 mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Usa esta opción para registrar un saldo a favor de forma manual, por ejemplo por un adelanto o ajuste comercial.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Cliente <span class="text-danger">*</span></label>
                        <select name="cliente_id" id="nsClienteId" class="form-select @error('cliente_id') is-invalid @enderror" required>
                            <option value="">— Selecciona un cliente —</option>
                            @foreach($todosClientes as $c)
                                <option value="{{ $c->id }}" {{ old('cliente_id') == $c->id ? 'selected' : '' }}>
                                    {{ $c->nombre }}{{ $c->documento ? ' — ' . $c->documento : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('cliente_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Monto (S/) <span class="text-danger">*</span></label>
                            <input type="number" name="monto" class="form-control text-end" step="0.01" min="0.01" placeholder="0.00" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Fecha <span class="text-danger">*</span></label>
                            <input type="date" name="fecha" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Descripción / Motivo</label>
                        <input type="text" name="descripcion" class="form-control" placeholder="Ej: Adelanto de pago, ajuste comercial…" maxlength="255">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-plus-lg me-1"></i>Crear saldo a favor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════
     Modal 3: Convertir notas de crédito a saldo a favor
     ══════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="modalConvertirNC" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-arrow-repeat me-2 text-warning"></i>Convertir nota de crédito a saldo a favor
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning small py-2 mb-3">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Al convertir una NC, el monto pasa a ser un <strong>saldo a favor</strong> del cliente que puede aplicarse a futuras ventas.
                    Esta acción es <strong>manual e irreversible</strong>.
                </div>
                <div id="ncLista"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

{{-- Encabezado ─────────────────────────────────────────────── --}}
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0"><i class="bi bi-wallet2 me-2 text-info"></i>Saldos a favor</h3>
        <p class="text-muted mb-0 small">Excedentes de pago y notas de crédito disponibles por cliente</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-warning btn-sm"
                data-bs-toggle="modal" data-bs-target="#modalConvertirNC">
            <i class="bi bi-arrow-repeat me-1"></i>Desde nota de crédito
        </button>
        <button type="button" class="btn btn-success btn-sm"
                data-bs-toggle="modal" data-bs-target="#modalNuevoSaldo">
            <i class="bi bi-plus-lg me-1"></i>Nuevo Saldo
        </button>
    </div>
</div>

{{-- Flash messages --}}
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2" role="alert">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
    <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
    <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
</div>
@endif
@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>Corrige los siguientes errores:</strong>
    <ul class="mb-0 mt-1 small">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
    <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- KPIs ──────────────────────────────────────────────────── --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="kpi-box bg-info bg-opacity-10 border border-info border-opacity-25">
            <div class="text-muted small">Total disponible</div>
            <div class="fs-4 fw-bold text-info">S/ {{ number_format($totalDisponible, 2) }}</div>
            <div class="text-muted small">{{ $totalRegistros }} registro(s)</div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="kpi-box bg-primary bg-opacity-10 border border-primary border-opacity-25">
            <div class="text-muted small">Clientes con saldo</div>
            <div class="fs-4 fw-bold text-primary">{{ $totalClientes }}</div>
            <div class="text-muted small">cliente(s) activos</div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="kpi-box bg-success bg-opacity-10 border border-success border-opacity-25">
            <div class="text-muted small">Mayor saldo individual</div>
            @php $mayorSaldo = $clientes->max('saldo_total') ?? 0; @endphp
            <div class="fs-4 fw-bold text-success">S/ {{ number_format($mayorSaldo, 2) }}</div>
            <div class="text-muted small">por cliente</div>
        </div>
    </div>
</div>

@if($clientes->isEmpty())
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
        <i class="bi bi-wallet2 text-muted fs-1 d-block mb-3"></i>
        <h5 class="text-muted">No hay saldos a favor activos</h5>
        <p class="text-muted small">Los saldos se generan automáticamente cuando un pago excede el total de una venta,<br>o puedes crear uno manualmente con el botón de arriba.</p>
        <button type="button" class="btn btn-success mt-2"
                data-bs-toggle="modal" data-bs-target="#modalNuevoSaldo">
            <i class="bi bi-plus-lg me-1"></i>Nuevo Saldo
        </button>
    </div>
</div>
@else

{{-- Una card por cliente ─────────────────────────────────── --}}
@foreach($clientes as $cliente)
@php
    $saldosActivos   = $cliente->saldos;
    $saldosHistorial = $cliente->saldos_historial;
@endphp
<div class="card border-0 shadow-sm mb-3 saldo-card" id="card-cliente-{{ $cliente->id }}">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
        <div>
            <span class="fw-bold">{{ $cliente->nombre }}</span>
            @if($cliente->documento)
                <span class="text-muted small ms-2">{{ $cliente->documento }}</span>
            @endif
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="text-end">
                <div class="text-muted small">Saldo total disponible</div>
                <div class="fw-bold text-info fs-5">S/ {{ number_format($cliente->saldo_total, 2) }}</div>
            </div>
            @if($cliente->ventas_pendientes_count > 0)
            <button type="button" class="btn btn-info btn-sm text-white"
                    onclick="abrirModalCliente({{ $cliente->id }}, '{{ addslashes($cliente->nombre) }}', {{ (float)$cliente->saldo_total }})">
                <i class="bi bi-lightning-fill me-1"></i>Aplicar saldo
            </button>
            @else
            <span class="badge bg-light text-muted border">Sin ventas pendientes</span>
            @endif
        </div>
    </div>

    {{-- Tabla de saldos activos --}}
    @if($saldosActivos->count())
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Origen</th>
                    <th>Fecha</th>
                    <th class="text-end">Original</th>
                    <th class="text-end">Disponible</th>
                    <th class="text-center">Estado</th>
                    <th class="text-end pe-3">Acción</th>
                </tr>
            </thead>
            <tbody>
                @foreach($saldosActivos as $s)
                @php
                    $esNC = $s->venta_origen_id && optional($s->ventaOrigen)->documento_tipo === 'nota_credito';
                    $esExcedente = $s->pago_id && !$esNC;
                    $esManual = !$s->pago_id && !$s->venta_origen_id;
                @endphp
                <tr class="saldo-row-highlight">
                    <td class="ps-3 small">
                        @if($esNC)
                            <span class="badge badge-nc me-1" title="Convertido desde nota de crédito">
                                <i class="bi bi-arrow-repeat"></i> NC
                            </span>
                        @elseif($esExcedente)
                            <span class="badge badge-excedente me-1" title="Excedente de pago">
                                <i class="bi bi-arrow-up-circle"></i> Excedente
                            </span>
                        @else
                            <span class="badge badge-manual me-1" title="Creado manualmente">
                                <i class="bi bi-pencil-square"></i> Manual
                            </span>
                        @endif
                        {{ $s->descripcion ?? '—' }}
                    </td>
                    <td class="small text-muted">{{ $s->fecha->format('d/m/Y') }}</td>
                    <td class="text-end text-muted small">S/ {{ number_format($s->monto_original, 2) }}</td>
                    <td class="text-end fw-semibold text-info">S/ {{ number_format($s->monto_disponible, 2) }}</td>
                    <td class="text-center">
                        @if($s->estado === 'disponible')
                            <span class="badge badge-disponible">Disponible</span>
                        @elseif($s->estado === 'parcialmente_usado')
                            <span class="badge badge-parcial">Parcial</span>
                        @endif
                    </td>
                    <td class="text-end pe-3">
                        @if($cliente->ventas_pendientes_count > 0)
                        <button type="button" class="btn btn-sm btn-outline-info"
                                onclick="abrirModalSaldoEspecifico({{ $cliente->id }}, '{{ addslashes($cliente->nombre) }}', {{ (float)$cliente->saldo_total }}, {{ $s->id }})">
                            <i class="bi bi-lightning-fill me-1"></i>Usar este
                        </button>
                        @else
                        <span class="text-muted small">Sin ventas pendientes</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Historial usado (colapsable) --}}
    @if($saldosHistorial->count())
    <div class="card-footer bg-white p-0">
        <button class="btn btn-link btn-sm text-muted w-100 text-start py-2 px-3"
            data-bs-toggle="collapse" data-bs-target="#hist-{{ $cliente->id }}">
            <i class="bi bi-clock-history me-1"></i>
            Ver historial usado ({{ $saldosHistorial->count() }} registro{{ $saldosHistorial->count() > 1 ? 's' : '' }})
        </button>
        <div class="collapse" id="hist-{{ $cliente->id }}">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <tbody>
                        @foreach($saldosHistorial as $s)
                        <tr class="text-muted">
                            <td class="ps-3 small">{{ $s->descripcion ?? '—' }}</td>
                            <td class="small">{{ $s->fecha->format('d/m/Y') }}</td>
                            <td class="text-end small">S/ {{ number_format($s->monto_original, 2) }}</td>
                            <td class="text-end small text-muted">S/ 0.00</td>
                            <td class="text-center"><span class="badge badge-usado">Usado</span></td>
                            <td class="pe-3"></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>
@endforeach
@endif

<script>
/* ══════════════════════════════════════════════════════════════
   Modal 1 — Aplicar saldo
   Estado interno del modal
═══════════════════════════════════════════════════════════════ */
let _modalClienteId   = null;
let _modalSaldoId     = null;   // saldo actualmente seleccionado en la UI
let _modalSaldoDisp   = 0;      // monto disponible del saldo seleccionado
let _modalDeuda       = 0;
let _modalInst        = null;
let _todosLosSaldos   = [];     // todos los saldos del cliente cargados desde el servidor

function _resetModal() {
    document.getElementById('modalAlerta').classList.add('d-none');
    document.getElementById('mSaldosLista').innerHTML = '<div class="text-muted small text-center py-2"><div class="spinner-border spinner-border-sm me-1"></div>Cargando…</div>';
    document.getElementById('mVentaId').innerHTML = '<option value="">— Selecciona primero un saldo —</option>';
    document.getElementById('mVentaInfo').textContent = '';
    document.getElementById('mMonto').value = '';
    document.getElementById('mVentaBloque').style.display  = 'none';
    document.getElementById('mMontoBloque').style.display  = 'none';
    document.getElementById('btnConfirmarAplicar').disabled = true;
    _modalSaldoId   = null;
    _modalSaldoDisp = 0;
    _modalDeuda     = 0;
}

function _origenLabel(s) {
    if (s.tipo_origen === 'nc') return '<span class="badge badge-nc me-1"><i class="bi bi-arrow-repeat"></i> NC</span>';
    if (s.tipo_origen === 'excedente') return '<span class="badge badge-excedente me-1"><i class="bi bi-arrow-up-circle"></i> Excedente</span>';
    return '<span class="badge badge-manual me-1"><i class="bi bi-pencil-square"></i> Manual</span>';
}

function _renderSaldos(saldos, preselectId) {
    const contenedor = document.getElementById('mSaldosLista');
    if (!saldos.length) {
        contenedor.innerHTML = '<div class="text-muted small">Este cliente no tiene saldos disponibles.</div>';
        return;
    }

    contenedor.innerHTML = saldos.map(s => `
        <div class="saldo-item-selectable p-2 ${preselectId === s.id ? 'selected' : ''}"
             data-saldo-id="${s.id}" data-disponible="${s.monto_disponible}"
             onclick="seleccionarSaldo(${s.id}, ${s.monto_disponible})">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    ${_origenLabel(s)}
                    <span class="small">${s.descripcion || '—'}</span>
                    <span class="text-muted small ms-2">${s.fecha}</span>
                </div>
                <div class="text-end">
                    <div class="fw-bold text-info">S/ ${s.monto_disponible.toFixed(2)}</div>
                    <div class="text-muted" style="font-size:.75rem">de S/ ${s.monto_original.toFixed(2)}</div>
                </div>
            </div>
        </div>
    `).join('');

    // Auto-seleccionar si hay preselección o solo hay uno
    if (preselectId) {
        const s = saldos.find(x => x.id === preselectId);
        if (s) seleccionarSaldo(s.id, s.monto_disponible);
    } else if (saldos.length === 1) {
        seleccionarSaldo(saldos[0].id, saldos[0].monto_disponible);
    }
}

function seleccionarSaldo(saldoId, disponible) {
    _modalSaldoId   = saldoId;
    _modalSaldoDisp = disponible;

    // Resaltar seleccionado
    document.querySelectorAll('.saldo-item-selectable').forEach(el => {
        el.classList.toggle('selected', parseInt(el.dataset.saldoId) === saldoId);
    });

    // Mostrar bloque de ventas
    const ventaBloque = document.getElementById('mVentaBloque');
    ventaBloque.style.display = '';
    ventaBloque.style.removeProperty('display');

    const sel = document.getElementById('mVentaId');
    if (sel.options.length <= 1 && sel.options[0]?.value === '') {
        // Cargar ventas pendientes si no están cargadas aún
        sel.innerHTML = '<option value="">Cargando ventas pendientes…</option>';
        fetch(`/casadets/saldos-favor/cliente/${_modalClienteId}/ventas.json`)
            .then(r => r.json())
            .then(ventas => {
                if (!ventas.length) {
                    sel.innerHTML = '<option value="">Sin ventas pendientes</option>';
                    return;
                }
                sel.innerHTML = '<option value="">— Selecciona una venta —</option>'
                    + ventas.map(v => `<option value="${v.id}" data-deuda="${v.saldo_pendiente}">${v.label}</option>`).join('');
            });
    }

    // Limpiar monto si cambia el saldo
    document.getElementById('mMonto').value = '';
    document.getElementById('mVentaInfo').textContent = '';
    document.getElementById('mMontoBloque').style.display = 'none';
    document.getElementById('btnConfirmarAplicar').disabled = true;
}

function abrirModalCliente(clienteId, clienteNombre, saldoTotal) {
    _modalClienteId = clienteId;
    _resetModal();

    document.getElementById('mClienteNombre').textContent      = clienteNombre;
    document.getElementById('mSaldoTotalDisponible').textContent = 'S/ ' + saldoTotal.toFixed(2);

    if (!_modalInst) _modalInst = new bootstrap.Modal(document.getElementById('modalAplicar'));
    _modalInst.show();

    // Cargar todos los saldos del cliente
    fetch(`/casadets/saldos-favor/cliente/${clienteId}/saldos.json`)
        .then(r => r.json())
        .then(saldos => {
            _todosLosSaldos = saldos;
            _renderSaldos(saldos, null);
        })
        .catch(() => {
            document.getElementById('mSaldosLista').innerHTML = '<div class="alert alert-danger small">Error al cargar saldos.</div>';
        });
}

function abrirModalSaldoEspecifico(clienteId, clienteNombre, saldoTotal, preselectSaldoId) {
    _modalClienteId = clienteId;
    _resetModal();

    document.getElementById('mClienteNombre').textContent       = clienteNombre;
    document.getElementById('mSaldoTotalDisponible').textContent = 'S/ ' + saldoTotal.toFixed(2);

    if (!_modalInst) _modalInst = new bootstrap.Modal(document.getElementById('modalAplicar'));
    _modalInst.show();

    fetch(`/casadets/saldos-favor/cliente/${clienteId}/saldos.json`)
        .then(r => r.json())
        .then(saldos => {
            _todosLosSaldos = saldos;
            _renderSaldos(saldos, preselectSaldoId);
        })
        .catch(() => {
            document.getElementById('mSaldosLista').innerHTML = '<div class="alert alert-danger small">Error al cargar saldos.</div>';
        });
}

// Cambio de venta: mostrar bloque de monto
document.getElementById('mVentaId').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    _modalDeuda = parseFloat(opt.dataset.deuda || 0);

    const montoBloque = document.getElementById('mMontoBloque');
    if (this.value && _modalSaldoId) {
        montoBloque.style.display = '';
        montoBloque.style.removeProperty('display');
        document.getElementById('mVentaInfo').textContent =
            'Pendiente: S/ ' + _modalDeuda.toFixed(2) + ' — Saldo disponible: S/ ' + _modalSaldoDisp.toFixed(2);
        document.getElementById('mMonto').value = Math.min(_modalSaldoDisp, _modalDeuda).toFixed(2);
        document.getElementById('btnConfirmarAplicar').disabled = false;
    } else {
        montoBloque.style.display = 'none';
        document.getElementById('btnConfirmarAplicar').disabled = true;
    }
});

document.getElementById('btnUsarTodoSaldo').addEventListener('click', () => {
    document.getElementById('mMonto').value = _modalSaldoDisp.toFixed(2);
});
document.getElementById('btnCubrirDeuda').addEventListener('click', () => {
    document.getElementById('mMonto').value = Math.min(_modalSaldoDisp, _modalDeuda).toFixed(2);
});

document.getElementById('btnConfirmarAplicar').addEventListener('click', async () => {
    const ventaId = document.getElementById('mVentaId').value;
    const monto   = parseFloat(document.getElementById('mMonto').value);
    const alerta  = document.getElementById('modalAlerta');

    alerta.classList.add('d-none');

    if (!_modalSaldoId) {
        alerta.className = 'alert alert-warning'; alerta.textContent = 'Selecciona un saldo a usar.'; alerta.classList.remove('d-none'); return;
    }
    if (!ventaId) {
        alerta.className = 'alert alert-warning'; alerta.textContent = 'Selecciona una venta.'; alerta.classList.remove('d-none'); return;
    }
    if (!monto || monto <= 0) {
        alerta.className = 'alert alert-warning'; alerta.textContent = 'Ingresa un monto válido.'; alerta.classList.remove('d-none'); return;
    }
    if (monto > _modalSaldoDisp + 0.005) {
        alerta.className = 'alert alert-danger'; alerta.textContent = 'El monto supera el saldo disponible (S/ ' + _modalSaldoDisp.toFixed(2) + ').'; alerta.classList.remove('d-none'); return;
    }

    const btn = document.getElementById('btnConfirmarAplicar');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Aplicando…';

    try {
        const form = new FormData();
        form.append('venta_id', ventaId);
        form.append('monto', monto.toFixed(2));
        form.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}');

        const res  = await fetch(`/casadets/saldos-favor/${_modalSaldoId}/aplicar`, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: form,
        });
        const data = await res.json();

        if (!res.ok) throw new Error(data.message || 'Error al aplicar el saldo.');

        _modalInst.hide();
        window.location.reload();

    } catch (err) {
        alerta.className = 'alert alert-danger';
        alerta.textContent = err.message;
        alerta.classList.remove('d-none');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Confirmar aplicación';
    }
});

/* ── Modal 2: Nuevo Saldo — abrir automáticamente si hay errores ── */
@if($errors->has('cliente_id') || $errors->has('monto') || $errors->has('fecha'))
document.addEventListener('DOMContentLoaded', function () {
    new bootstrap.Modal(document.getElementById('modalNuevoSaldo')).show();
});
@endif

/* ── Modal 3: Convertir NC ─────────────────────────────────── */
document.getElementById('modalConvertirNC').addEventListener('shown.bs.modal', function () {
    const contenedor = document.getElementById('ncLista');
    if (!contenedor) return;
    contenedor.innerHTML = '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm me-2"></div>Cargando notas de crédito…</div>';

    fetch('/casadets/saldos-favor/notas-credito.json')
        .then(r => r.json())
        .then(ncs => {
            if (!ncs.length) {
                contenedor.innerHTML = `
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-check-circle text-success fs-1 d-block mb-2"></i>
                        <div>No hay notas de crédito pendientes de convertir.</div>
                        <div class="small text-muted mt-1">Todas las NC ya fueron procesadas o no tienen cliente asignado.</div>
                    </div>`;
                return;
            }

            let html = `<div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Documento</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th class="text-end">Monto</th>
                            <th class="text-end pe-3">Acción</th>
                        </tr>
                    </thead>
                    <tbody>`;

            ncs.forEach(nc => {
                html += `<tr class="nc-row">
                    <td class="ps-3">
                        <span class="badge bg-danger me-1">NC</span>
                        <span class="fw-semibold">${nc.numero}</span>
                    </td>
                    <td class="small">${nc.cliente}</td>
                    <td class="small text-muted">${nc.fecha}</td>
                    <td class="text-end fw-semibold text-danger">S/ ${nc.monto.toFixed(2)}</td>
                    <td class="text-end pe-3">
                        <button type="button" class="btn btn-sm btn-warning text-dark btn-convertir-nc"
                                data-id="${nc.id}" data-monto="${nc.monto.toFixed(2)}"
                                data-doc="${nc.numero}" data-cliente="${nc.cliente}">
                            <i class="bi bi-arrow-repeat me-1"></i>Convertir
                        </button>
                    </td>
                </tr>`;
            });

            html += `</tbody></table></div>`;
            contenedor.innerHTML = html;

            document.querySelectorAll('.btn-convertir-nc').forEach(btn => {
                btn.addEventListener('click', async function () {
                    const id      = this.dataset.id;
                    const monto   = this.dataset.monto;
                    const doc     = this.dataset.doc;
                    const cliente = this.dataset.cliente;

                    if (!confirm(`¿Convertir "${doc}" de ${cliente} por S/ ${monto} a saldo a favor?\n\nEsta acción no se puede deshacer.`)) return;

                    this.disabled = true;
                    this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                    const form = new FormData();
                    form.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}');

                    try {
                        await fetch(`/casadets/saldos-favor/nc/${id}/convertir`, {
                            method: 'POST',
                            body: form,
                        });
                        window.location.href = '/casadets/saldos-favor';
                    } catch (err) {
                        alert('Error al convertir: ' + err.message);
                        this.disabled = false;
                        this.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Convertir';
                    }
                });
            });
        })
        .catch(() => {
            contenedor.innerHTML = '<div class="alert alert-danger">Error al cargar notas de crédito.</div>';
        });
});
</script>
@endsection
