@extends('layouts.app')

@section('content')
<style>
.saldo-card { border-left: 4px solid #0dcaf0; }
.saldo-card.sin-saldo { border-left-color: #dee2e6; opacity: .7; }
.badge-disponible     { background:#d1ecf1; color:#0c5460; border:1px solid #bee5eb; }
.badge-parcial        { background:#fff3cd; color:#856404; border:1px solid #ffc107; }
.badge-usado          { background:#e2e3e5; color:#383d41; border:1px solid #c8c9ca; }
.saldo-row-highlight  { background:#f0fbff !important; }
.kpi-box { border-radius:12px; padding:1.1rem 1.4rem; }
</style>

{{-- Modal: aplicar saldo ──────────────────────────────────── --}}
<div class="modal fade" id="modalAplicar" tabindex="-1" aria-labelledby="modalAplicarLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAplicarLabel">
                    <i class="bi bi-wallet2 me-2 text-info"></i>Aplicar saldo a venta
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="modalAlerta" class="alert d-none mb-3"></div>

                <div class="mb-3 p-3 bg-light rounded">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="text-muted small">Cliente</div>
                            <div class="fw-semibold" id="mClienteNombre">—</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Saldo disponible</div>
                            <div class="fw-bold text-info" id="mSaldoDisponible">S/ 0.00</div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Venta a cobrar</label>
                    <select id="mVentaId" class="form-select">
                        <option value="">Cargando…</option>
                    </select>
                    <div class="text-muted small mt-1" id="mVentaInfo"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Monto a aplicar</label>
                    <div class="input-group">
                        <span class="input-group-text">S/</span>
                        <input type="number" id="mMonto" class="form-control text-end" step="0.01" min="0.01" value="">
                    </div>
                    <div class="d-flex gap-2 mt-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnUsarTodoSaldo">Usar todo el saldo</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnCubrirDeuda">Cubrir deuda exacta</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-info text-white" id="btnConfirmarAplicar">
                    <i class="bi bi-check-lg me-1"></i> Confirmar aplicación
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Encabezado ─────────────────────────────────────────────── --}}
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0"><i class="bi bi-wallet2 me-2 text-info"></i>Saldos a favor</h3>
        <p class="text-muted mb-0 small">Excedentes de pago disponibles por cliente</p>
    </div>
</div>

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
        <p class="text-muted small">Los saldos se generan automáticamente cuando un pago excede el total de una venta.</p>
    </div>
</div>
@else

{{-- Una card por cliente ─────────────────────────────────── --}}
@foreach($clientes as $cliente)
@php
    $saldosActivos = $cliente->saldos->whereIn('estado', ['disponible','parcialmente_usado'])->where('monto_disponible', '>', 0);
    $saldosHistorial = $cliente->saldos->where('estado', 'usado');
@endphp
<div class="card border-0 shadow-sm mb-3 saldo-card" id="card-cliente-{{ $cliente->id }}">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
        <div class="d-flex align-items-center gap-3">
            <div>
                <span class="fw-bold">{{ $cliente->nombre }}</span>
                @if($cliente->documento)
                    <span class="text-muted small ms-2">{{ $cliente->documento }}</span>
                @endif
                @if($cliente->telefono)
                    <span class="text-muted small ms-2"><i class="bi bi-telephone me-1"></i>{{ $cliente->telefono }}</span>
                @endif
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="text-end">
                <div class="text-muted small">Saldo total disponible</div>
                <div class="fw-bold text-info fs-5">S/ {{ number_format($cliente->saldo_total, 2) }}</div>
            </div>
            @if($cliente->ventas_pendientes_count > 0)
            <button type="button" class="btn btn-info btn-sm text-white"
                onclick="abrirModal({{ $cliente->id }}, '{{ addslashes($cliente->nombre) }}', {{ (float)$cliente->saldo_total }}, null, {{ $saldosActivos->first()?->id ?? 'null' }}, {{ (float)($saldosActivos->first()?->monto_disponible ?? 0) }})">
                <i class="bi bi-lightning-fill me-1"></i>Aplicar saldo
            </button>
            @else
            <span class="badge bg-light text-muted border">Sin ventas pendientes</span>
            @endif
        </div>
    </div>

    {{-- Saldos activos ──────────────────────────── --}}
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
                <tr class="saldo-row-highlight">
                    <td class="ps-3 small">{{ $s->descripcion ?? '—' }}</td>
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
                            onclick="abrirModal({{ $cliente->id }}, '{{ addslashes($cliente->nombre) }}', {{ (float)$cliente->saldo_total }}, {{ $venta_id ?? 'null' }}, {{ $s->id }}, {{ (float)$s->monto_disponible }})">
                            <i class="bi bi-lightning-fill me-1"></i>Usar
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

    {{-- Historial usado (colapsable) ──────────── --}}
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
let modalSaldoId    = null;
let modalClienteId  = null;
let modalDisponible = 0;
let modalDeuda      = 0;
let modalInst       = null;

function abrirModal(clienteId, clienteNombre, saldoTotal, ventaId, saldoId, saldoDisponible) {
    modalClienteId  = clienteId;
    modalSaldoId    = saldoId;
    modalDisponible = saldoDisponible;

    document.getElementById('mClienteNombre').textContent  = clienteNombre;
    document.getElementById('mSaldoDisponible').textContent = 'S/ ' + saldoDisponible.toFixed(2);
    document.getElementById('mMonto').value  = '';
    document.getElementById('mVentaInfo').textContent = '';
    document.getElementById('modalAlerta').classList.add('d-none');

    const sel = document.getElementById('mVentaId');
    sel.innerHTML = '<option value="">Cargando ventas pendientes…</option>';

    if (!modalInst) modalInst = new bootstrap.Modal(document.getElementById('modalAplicar'));
    modalInst.show();

    fetch(`/casadets/saldos-favor/cliente/${clienteId}/ventas.json`)
        .then(r => r.json())
        .then(ventas => {
            if (!ventas.length) {
                sel.innerHTML = '<option value="">Sin ventas pendientes para este cliente</option>';
                return;
            }
            sel.innerHTML = '<option value="">— Selecciona una venta —</option>'
                + ventas.map(v => `<option value="${v.id}" data-deuda="${v.saldo_pendiente}">${v.label}</option>`).join('');
            if (ventaId) {
                sel.value = ventaId;
                sel.dispatchEvent(new Event('change'));
            }
        });
}

document.getElementById('mVentaId').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    modalDeuda = parseFloat(opt.dataset.deuda || 0);
    if (modalDeuda > 0) {
        document.getElementById('mVentaInfo').textContent =
            'Saldo pendiente de esta venta: S/ ' + modalDeuda.toFixed(2);
        // Sugerir el mínimo entre saldo disponible y deuda
        document.getElementById('mMonto').value = Math.min(modalDisponible, modalDeuda).toFixed(2);
    } else {
        document.getElementById('mVentaInfo').textContent = '';
        document.getElementById('mMonto').value = '';
    }
});

document.getElementById('btnUsarTodoSaldo').addEventListener('click', () => {
    document.getElementById('mMonto').value = modalDisponible.toFixed(2);
});
document.getElementById('btnCubrirDeuda').addEventListener('click', () => {
    document.getElementById('mMonto').value = Math.min(modalDisponible, modalDeuda).toFixed(2);
});

document.getElementById('btnConfirmarAplicar').addEventListener('click', async () => {
    const ventaId = document.getElementById('mVentaId').value;
    const monto   = parseFloat(document.getElementById('mMonto').value);
    const alerta  = document.getElementById('modalAlerta');

    alerta.classList.add('d-none');

    if (!ventaId) {
        alerta.className = 'alert alert-warning'; alerta.textContent = 'Selecciona una venta.'; alerta.classList.remove('d-none'); return;
    }
    if (!monto || monto <= 0) {
        alerta.className = 'alert alert-warning'; alerta.textContent = 'Ingresa un monto válido.'; alerta.classList.remove('d-none'); return;
    }
    if (monto > modalDisponible + 0.005) {
        alerta.className = 'alert alert-danger'; alerta.textContent = 'El monto supera el saldo disponible (S/ ' + modalDisponible.toFixed(2) + ').'; alerta.classList.remove('d-none'); return;
    }

    const btn = document.getElementById('btnConfirmarAplicar');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Aplicando…';

    try {
        const form = new FormData();
        form.append('venta_id', ventaId);
        form.append('monto', monto.toFixed(2));
        form.append('_token', document.querySelector('meta[name="csrf-token"]')?.content
            || '{{ csrf_token() }}');

        const res  = await fetch(`/casadets/saldos-favor/${modalSaldoId}/aplicar`, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: form,
        });
        const data = await res.json();

        if (!res.ok) throw new Error(data.message || 'Error al aplicar el saldo.');

        modalInst.hide();
        // Recargar página para reflejar cambios
        window.location.reload();

    } catch (err) {
        alerta.className = 'alert alert-danger';
        alerta.textContent = err.message;
        alerta.classList.remove('d-none');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Confirmar aplicación';
    }
});
</script>
@endsection
