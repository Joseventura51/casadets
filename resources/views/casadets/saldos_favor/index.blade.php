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
.nc-summary-card { border-left:4px solid #ffc107; border-radius:10px; }
.nc-cliente-select { min-width:220px; }
.nc-status-pill { font-size:.72rem; }

/* ── Autocomplete cliente ── */
.ns-sugerencias .list-group-item { cursor:pointer; padding:.45rem .75rem; font-size:.9rem; border-radius:0; }
.ns-sugerencias .list-group-item:hover,
.ns-sugerencias .list-group-item.active { background:#e8f4fd; color:#0c5460; }
.ns-sugerencias .list-group-item .doc-tag { font-size:.75rem; color:#6c757d; margin-left:.4rem; }
#nsClienteTexto.is-valid  { border-color:#198754; }
#nsClienteTexto.no-match  { border-color:#dc3545; }
</style>

@if(!$cajaAbierta)
<div class="alert alert-warning d-flex align-items-center gap-2 mb-3 py-2">
    <i class="bi bi-lock-fill fs-5 flex-shrink-0"></i>
    <div class="flex-grow-1">
        <strong>Caja cerrada.</strong> No puedes crear ni aplicar saldos a favor hasta que se abra la caja del día.
    </div>
    <a href="/casadets/caja" class="btn btn-sm btn-warning flex-shrink-0">
        <i class="bi bi-box-arrow-in-right me-1"></i>Ir a Caja
    </a>
</div>
@endif

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
                        <div class="ns-autocomplete-wrap position-relative">
                            <input type="text"
                                   id="nsClienteTexto"
                                   class="form-control @error('cliente_id') is-invalid @enderror"
                                   placeholder="Escribe nombre o documento…"
                                   autocomplete="off"
                                   value="{{ old('cliente_id') ? ($todosClientes->firstWhere('id', old('cliente_id'))->nombre ?? '') : '' }}">
                            <input type="hidden" name="cliente_id" id="nsClienteId"
                                   value="{{ old('cliente_id') }}">
                            <div id="nsClienteSugerencias"
                                 class="ns-sugerencias list-group shadow-sm position-absolute w-100 d-none"
                                 style="z-index:9999;max-height:220px;overflow-y:auto;top:100%;left:0"></div>
                            <div id="nsClienteEstado" class="form-text d-none"></div>
                        </div>
                        @error('cliente_id')
                            <div class="text-danger small mt-1">{{ $message }}</div>
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

{{-- ══════════════════════════════════════════════════════════
     Modal 4: Anular saldo a favor
     ══════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="modalAnularSaldo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger bg-opacity-10">
                <h5 class="modal-title text-danger">
                    <i class="bi bi-x-circle me-2"></i>Anular saldo a favor
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger small py-2 mb-3">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    Esta acción es <strong>irreversible</strong>. El saldo quedará anulado y no podrá usarse.
                    Si el saldo fue parcialmente utilizado, el remanente también se perderá.
                </div>
                <div class="mb-3 p-3 bg-light rounded" id="anularSaldoInfo">
                    <div class="small text-muted mb-1">Descripción:</div>
                    <div class="fw-semibold" id="anularSaldoDesc">—</div>
                    <div class="small text-muted mt-2">Monto disponible:</div>
                    <div class="fw-bold text-danger" id="anularSaldoMonto">S/ 0.00</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Motivo de anulación <span class="text-muted fw-normal">(opcional)</span></label>
                    <input type="text" id="anularSaldoMotivo" class="form-control"
                           placeholder="Ej: Error de registro, duplicado…" maxlength="255">
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="anularSaldoConfirm">
                    <label class="form-check-label small" for="anularSaldoConfirm">
                        Confirmo que deseo anular este saldo a favor
                    </label>
                </div>
                <div id="anularSaldoError" class="alert alert-danger d-none mt-3 py-2 small"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarAnularSaldo" disabled>
                    <i class="bi bi-x-circle me-1"></i>Anular saldo
                </button>
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
            @if($notasCreditoPendientes > 0)
                <span class="badge text-bg-warning ms-1">{{ $notasCreditoPendientes }}</span>
            @endif
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

@if($notasCreditoPendientes > 0)
<div class="card border-0 shadow-sm nc-summary-card mb-3">
    <div class="card-body d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center py-3">
        <div>
            <div class="fw-semibold">
                <i class="bi bi-arrow-repeat text-warning me-1"></i>
                Notas de credito pendientes de conversion
            </div>
            <div class="text-muted small">
                {{ $notasCreditoPendientes }} NC pendiente(s).
                @if($notasCreditoSinCliente > 0)
                    {{ $notasCreditoSinCliente }} necesita(n) cliente antes de convertirse.
                @else
                    Todas tienen cliente y pueden convertirse manualmente.
                @endif
            </div>
        </div>
        <button type="button" class="btn btn-outline-warning btn-sm align-self-start align-self-md-center"
                data-bs-toggle="modal" data-bs-target="#modalConvertirNC">
            <i class="bi bi-list-check me-1"></i>Revisar notas
        </button>
    </div>
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

{{-- Buscador de cliente ──────────────────────────────────── --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-search text-muted"></i>
            <input type="text" id="buscarCliente"
                   class="form-control form-control-sm"
                   placeholder="Buscar cliente por nombre o documento…"
                   style="max-width:380px;"
                   autocomplete="off">
            <span id="saldosBuscadorInfo" class="text-muted small ms-1"></span>
        </div>
    </div>
</div>

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
                        <div class="d-flex gap-1 justify-content-end flex-wrap">
                        @if($cliente->ventas_pendientes_count > 0)
                        <button type="button" class="btn btn-sm btn-outline-info"
                                onclick="abrirModalSaldoEspecifico({{ $cliente->id }}, '{{ addslashes($cliente->nombre) }}', {{ (float)$cliente->saldo_total }}, {{ $s->id }})">
                            <i class="bi bi-lightning-fill me-1"></i>Usar este
                        </button>
                        @else
                        <span class="text-muted small me-1">Sin ventas pendientes</span>
                        @endif
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                onclick="confirmarAnularSaldo({{ $s->id }}, '{{ addslashes($s->descripcion ?? '') }}', {{ number_format($s->monto_disponible, 2, '.', '') }})">
                            <i class="bi bi-x-circle me-1"></i>Anular
                        </button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Historial usado/anulado (colapsable) --}}
    @if($saldosHistorial->count())
    <div class="card-footer bg-white p-0">
        <button class="btn btn-link btn-sm text-muted w-100 text-start py-2 px-3"
            data-bs-toggle="collapse" data-bs-target="#hist-{{ $cliente->id }}">
            <i class="bi bi-clock-history me-1"></i>
            Ver historial ({{ $saldosHistorial->count() }} registro{{ $saldosHistorial->count() > 1 ? 's' : '' }})
        </button>
        <div class="collapse" id="hist-{{ $cliente->id }}">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <tbody>
                        @foreach($saldosHistorial as $s)
                        <tr class="text-muted @if($s->estado==='anulado') table-danger bg-danger bg-opacity-10 @endif">
                            <td class="ps-3 small">
                                {{ $s->descripcion ?? '—' }}
                                @if($s->estado === 'anulado' && $s->motivo_anulacion)
                                    <br><span class="text-danger fst-italic" style="font-size:.78rem;">Motivo: {{ $s->motivo_anulacion }}</span>
                                @endif
                            </td>
                            <td class="small">{{ $s->fecha->format('d/m/Y') }}</td>
                            <td class="text-end small">S/ {{ number_format($s->monto_original, 2) }}</td>
                            <td class="text-end small text-muted">S/ 0.00</td>
                            <td class="text-center">
                                @if($s->estado === 'anulado')
                                    <span class="badge bg-danger">Anulado</span>
                                    @if($s->anulado_at)
                                        <div class="text-muted" style="font-size:.7rem;">{{ $s->anulado_at->format('d/m/Y') }}</div>
                                    @endif
                                @else
                                    <span class="badge badge-usado">Usado</span>
                                @endif
                            </td>
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

@php
$clientesJsonAc = $todosClientes->map(fn($c) => [
    'id'     => $c->id,
    'nombre' => $c->nombre,
    'doc'    => $c->documento ?? '',
])->values()->toJson(JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
@endphp

<script>
/* ── Buscador de cliente en listado de saldos ─────────────── */
(function () {
    const inp  = document.getElementById('buscarCliente');
    const info = document.getElementById('saldosBuscadorInfo');
    if (!inp) return;

    function norm(s) {
        return (s || '').toLowerCase()
            .replace(/[áàä]/g,'a').replace(/[éèë]/g,'e')
            .replace(/[íìï]/g,'i').replace(/[óòö]/g,'o')
            .replace(/[úùü]/g,'u').replace(/ñ/g,'n');
    }

    inp.addEventListener('input', function () {
        const term = norm(this.value.trim());
        const cards = document.querySelectorAll('.saldo-card[id^="card-cliente-"]');
        let visibles = 0;
        cards.forEach(card => {
            const header = norm(card.querySelector('.card-header')?.textContent || '');
            const mostrar = !term || header.includes(term);
            card.style.display = mostrar ? '' : 'none';
            if (mostrar) visibles++;
        });
        if (info) info.textContent = term ? visibles + ' resultado(s)' : '';
    });
})();

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

/* ── Modal 2: Nuevo Saldo — autocomplete de cliente ── */
(function () {
    const CLIENTES = {!! $clientesJsonAc !!};

    const inpTexto      = document.getElementById('nsClienteTexto');
    const inpHidden     = document.getElementById('nsClienteId');
    const sugerencias   = document.getElementById('nsClienteSugerencias');
    const estadoDiv     = document.getElementById('nsClienteEstado');
    let   seleccionado  = false;   // true cuando el usuario eligió un ítem válido
    let   activeIdx     = -1;

    function normalize(s) {
        return s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    function mostrar(items) {
        activeIdx = -1;
        if (!items.length) {
            sugerencias.innerHTML = '<div class="list-group-item text-muted">Sin resultados</div>';
            sugerencias.classList.remove('d-none');
            return;
        }
        sugerencias.innerHTML = items.slice(0, 12).map(c =>
            `<div class="list-group-item" data-id="${c.id}" data-nombre="${c.nombre}">
                ${c.nombre}<span class="doc-tag">${c.doc}</span>
             </div>`
        ).join('');
        sugerencias.classList.remove('d-none');

        sugerencias.querySelectorAll('.list-group-item[data-id]').forEach(el => {
            el.addEventListener('mousedown', e => {
                e.preventDefault();
                elegir(parseInt(el.dataset.id), el.dataset.nombre);
            });
        });
    }

    function ocultar() {
        sugerencias.classList.add('d-none');
        activeIdx = -1;
    }

    function elegir(id, nombre) {
        inpHidden.value  = id;
        inpTexto.value   = nombre;
        seleccionado     = true;
        ocultar();
        inpTexto.classList.remove('no-match');
        inpTexto.classList.add('is-valid');
        estadoDiv.classList.add('d-none');
    }

    function invalidar() {
        inpHidden.value = '';
        seleccionado    = false;
        inpTexto.classList.remove('is-valid');
        inpTexto.classList.add('no-match');
        estadoDiv.textContent = 'Selecciona un cliente de la lista.';
        estadoDiv.className   = 'form-text text-danger';
    }

    inpTexto.addEventListener('input', function () {
        seleccionado    = false;
        inpHidden.value = '';
        inpTexto.classList.remove('is-valid', 'no-match');
        estadoDiv.classList.add('d-none');

        const q = normalize(this.value.trim());
        if (!q) { ocultar(); return; }

        const resultados = CLIENTES.filter(c =>
            normalize(c.nombre).includes(q) || normalize(c.doc).includes(q)
        );
        mostrar(resultados);
    });

    inpTexto.addEventListener('keydown', function (e) {
        const items = sugerencias.querySelectorAll('.list-group-item[data-id]');
        if (!items.length) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIdx = Math.min(activeIdx + 1, items.length - 1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIdx = Math.max(activeIdx - 1, 0);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (activeIdx >= 0 && items[activeIdx]) {
                const el = items[activeIdx];
                elegir(parseInt(el.dataset.id), el.dataset.nombre);
            }
            return;
        } else if (e.key === 'Escape') {
            ocultar(); return;
        } else { return; }

        items.forEach((el, i) => el.classList.toggle('active', i === activeIdx));
        if (items[activeIdx]) items[activeIdx].scrollIntoView({ block: 'nearest' });
    });

    inpTexto.addEventListener('blur', function () {
        setTimeout(() => {
            ocultar();
            if (this.value.trim() && !seleccionado) invalidar();
            if (!this.value.trim()) {
                inpTexto.classList.remove('is-valid', 'no-match');
                estadoDiv.classList.add('d-none');
            }
        }, 150);
    });

    // Bloquear submit si no hay cliente seleccionado
    document.getElementById('modalNuevoSaldo')
        .querySelector('form')
        .addEventListener('submit', function (e) {
            if (!inpHidden.value) {
                e.preventDefault();
                inpTexto.focus();
                invalidar();
            }
        });

    // Limpiar al cerrar el modal
    document.getElementById('modalNuevoSaldo').addEventListener('hidden.bs.modal', function () {
        inpTexto.value  = '';
        inpHidden.value = '';
        seleccionado    = false;
        inpTexto.classList.remove('is-valid', 'no-match');
        estadoDiv.classList.add('d-none');
        ocultar();
    });
})();

/* ── Modal 2: Nuevo Saldo — abrir automáticamente si hay errores ── */
@if($errors->has('cliente_id') || $errors->has('monto') || $errors->has('fecha'))
document.addEventListener('DOMContentLoaded', function () {
    new bootstrap.Modal(document.getElementById('modalNuevoSaldo')).show();
});
@endif

/* ── Modal 4: Anular saldo a favor ───────────────────────── */
let _anularSaldoId   = null;
let _anularModalInst = null;

function confirmarAnularSaldo(saldoId, descripcion, monto) {
    _anularSaldoId = saldoId;

    document.getElementById('anularSaldoDesc').textContent  = descripcion || '—';
    document.getElementById('anularSaldoMonto').textContent = 'S/ ' + parseFloat(monto).toFixed(2);
    document.getElementById('anularSaldoMotivo').value      = '';
    document.getElementById('anularSaldoConfirm').checked   = false;
    document.getElementById('btnConfirmarAnularSaldo').disabled = true;
    document.getElementById('anularSaldoError').classList.add('d-none');

    if (!_anularModalInst) {
        _anularModalInst = new bootstrap.Modal(document.getElementById('modalAnularSaldo'));
    }
    _anularModalInst.show();
}

document.getElementById('anularSaldoConfirm').addEventListener('change', function () {
    document.getElementById('btnConfirmarAnularSaldo').disabled = !this.checked;
});

document.getElementById('btnConfirmarAnularSaldo').addEventListener('click', async function () {
    if (!_anularSaldoId) return;

    const motivo  = document.getElementById('anularSaldoMotivo').value.trim();
    const errDiv  = document.getElementById('anularSaldoError');
    const btn     = this;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Anulando…';
    errDiv.classList.add('d-none');

    try {
        const form = new FormData();
        form.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}');
        if (motivo) form.append('motivo', motivo);

        const res  = await fetch(`/casadets/saldos-favor/${_anularSaldoId}/anular`, {
            method:  'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body:    form,
        });
        const data = await res.json().catch(() => ({}));

        if (!res.ok) throw new Error(data.message || 'No se pudo anular el saldo.');

        _anularModalInst.hide();
        window.location.reload();

    } catch (err) {
        errDiv.textContent = err.message;
        errDiv.classList.remove('d-none');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-x-circle me-1"></i>Anular saldo';
        document.getElementById('anularSaldoConfirm').checked = false;
    }
});

/* Modal 3: Convertir notas de credito */
const CLIENTES_NC = {!! $clientesJsonAc !!};

function ncEscape(value) {
    return String(value ?? '').replace(/[&<>"]/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
    }[char]));
}

function ncClienteOptions() {
    return '<option value="">Seleccionar cliente...</option>' + CLIENTES_NC.map(c => {
        const doc = c.doc ? ` - ${c.doc}` : '';
        return `<option value="${c.id}">${ncEscape(c.nombre + doc)}</option>`;
    }).join('');
}

function ncClienteCell(nc) {
    if (!nc.requiere_cliente) {
        const doc = nc.cliente_doc ? `<span class="text-muted ms-1">${ncEscape(nc.cliente_doc)}</span>` : '';
        return `<div class="fw-semibold small">${ncEscape(nc.cliente)}${doc}</div><span class="badge bg-success-subtle text-success border nc-status-pill">Listo para convertir</span>`;
    }

    return `<div class="d-flex flex-column flex-lg-row gap-2 align-items-lg-center">
        <select class="form-select form-select-sm nc-cliente-select" data-nc-select="${nc.id}">
            ${ncClienteOptions()}
        </select>
        <button type="button" class="btn btn-sm btn-outline-primary btn-asignar-nc" data-id="${nc.id}">
            <i class="bi bi-person-check me-1"></i>Asignar
        </button>
    </div>
    <span class="badge bg-warning-subtle text-warning border nc-status-pill mt-2">Falta cliente</span>`;
}

function renderNotasCredito(ncs) {
    if (!ncs.length) {
        return `<div class="text-center text-muted py-5">
            <i class="bi bi-check-circle text-success fs-1 d-block mb-2"></i>
            <div>No hay notas de credito pendientes de convertir.</div>
            <div class="small text-muted mt-1">Todas las NC disponibles ya fueron procesadas.</div>
        </div>`;
    }

    return `<div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Documento</th>
                    <th>Cliente</th>
                    <th>Fecha</th>
                    <th class="text-end">Monto</th>
                    <th class="text-end pe-3">Accion</th>
                </tr>
            </thead>
            <tbody>
                ${ncs.map(nc => `<tr class="nc-row" data-nc-row="${nc.id}">
                    <td class="ps-3">
                        <span class="badge bg-danger me-1">NC</span>
                        <span class="fw-semibold">${ncEscape(nc.numero)}</span>
                    </td>
                    <td class="small" data-nc-cliente-cell="${nc.id}">${ncClienteCell(nc)}</td>
                    <td class="small text-muted">${ncEscape(nc.fecha)}</td>
                    <td class="text-end fw-semibold text-danger">S/ ${Number(nc.monto).toFixed(2)}</td>
                    <td class="text-end pe-3">
                        <button type="button" class="btn btn-sm btn-warning text-dark btn-convertir-nc"
                                data-id="${nc.id}" data-monto="${Number(nc.monto).toFixed(2)}"
                                data-doc="${ncEscape(nc.numero)}" data-cliente="${ncEscape(nc.cliente || '')}"
                                ${nc.requiere_cliente ? 'disabled' : ''}>
                            <i class="bi bi-arrow-repeat me-1"></i>Convertir
                        </button>
                    </td>
                </tr>`).join('')}
            </tbody>
        </table>
    </div>`;
}

async function ncPost(url, form) {
    form.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}');

    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: form,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || 'No se pudo completar la accion.');
    return data;
}

document.getElementById('modalConvertirNC').addEventListener('shown.bs.modal', function () {
    const contenedor = document.getElementById('ncLista');
    if (!contenedor) return;

    contenedor.innerHTML = '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm me-2"></div>Cargando notas de credito...</div>';

    fetch('/casadets/saldos-favor/notas-credito.json', {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
        .then(r => r.json())
        .then(ncs => {
            contenedor.innerHTML = renderNotasCredito(ncs);

            contenedor.querySelectorAll('.btn-asignar-nc').forEach(btn => {
                btn.addEventListener('click', async function () {
                    const id = this.dataset.id;
                    const select = contenedor.querySelector(`[data-nc-select="${id}"]`);
                    const clienteId = select?.value;

                    if (!clienteId) {
                        select?.focus();
                        return;
                    }

                    this.disabled = true;
                    this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                    try {
                        const form = new FormData();
                        form.append('cliente_id', clienteId);
                        const data = await ncPost(`/casadets/saldos-favor/nc/${id}/cliente`, form);
                        const cell = contenedor.querySelector(`[data-nc-cliente-cell="${id}"]`);
                        const convertBtn = contenedor.querySelector(`.btn-convertir-nc[data-id="${id}"]`);
                        const doc = data.cliente_doc ? `<span class="text-muted ms-1">${ncEscape(data.cliente_doc)}</span>` : '';

                        cell.innerHTML = `<div class="fw-semibold small">${ncEscape(data.cliente)}${doc}</div><span class="badge bg-success-subtle text-success border nc-status-pill">Listo para convertir</span>`;
                        convertBtn.disabled = false;
                        convertBtn.dataset.cliente = data.cliente || '';
                    } catch (err) {
                        alert(err.message);
                        this.disabled = false;
                        this.innerHTML = '<i class="bi bi-person-check me-1"></i>Asignar';
                    }
                });
            });

            contenedor.querySelectorAll('.btn-convertir-nc').forEach(btn => {
                btn.addEventListener('click', async function () {
                    const id = this.dataset.id;
                    const monto = this.dataset.monto;
                    const doc = this.dataset.doc;
                    const cliente = this.dataset.cliente || 'cliente asignado';

                    if (!confirm(`Convertir "${doc}" de ${cliente} por S/ ${monto} a saldo a favor?\n\nEsta accion queda registrada y evita convertir la misma NC dos veces.`)) return;

                    this.disabled = true;
                    this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                    try {
                        await ncPost(`/casadets/saldos-favor/nc/${id}/convertir`, new FormData());
                        window.location.href = '/casadets/saldos-favor';
                    } catch (err) {
                        alert(err.message);
                        this.disabled = false;
                        this.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Convertir';
                    }
                });
            });
        })
        .catch(() => {
            contenedor.innerHTML = '<div class="alert alert-danger">Error al cargar notas de credito.</div>';
        });
});
</script>
@endsection
