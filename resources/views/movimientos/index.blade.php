@extends('layouts.app')

@section('content')
<style>
.mov-filter-card { border:0; box-shadow:0 1px 6px rgba(15,23,42,.08); }
.mov-filter-grid { display:grid; grid-template-columns:repeat(6,minmax(130px,1fr)); gap:.65rem; }
.mov-filter-grid .filter-wide { grid-column:span 2; }
.mov-filter-label { display:block; margin-bottom:.22rem; font-size:.68rem; font-weight:700; color:#6c757d; text-transform:uppercase; letter-spacing:.02em; }
.mov-filter-control { border-radius:7px; font-size:.82rem; }
.mov-stat-card { border:1px solid rgba(0,0,0,.07); border-radius:8px; box-shadow:0 1px 4px rgba(15,23,42,.04); }
.mov-table th { font-size:.72rem; text-transform:uppercase; letter-spacing:.02em; color:#6c757d; white-space:nowrap; }
.mov-table td { vertical-align:middle; }
.mov-table thead tr.align-top { display:none; }
.mov-row { transition:background .12s ease; }
.mov-row:hover { background:#f8fafc; }
@media (max-width: 992px) { .mov-filter-grid { grid-template-columns:repeat(3,minmax(0,1fr)); } }
@media (max-width: 576px) {
    .mov-filter-grid { grid-template-columns:1fr; }
    .mov-filter-grid .filter-wide { grid-column:auto; }
}
</style>
{{-- Encabezado: solo empresa + botones --}}
<form method="GET" id="formFiltros" data-dynamic-filter>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h2 class="h3 mb-1">Movimientos</h2>
        <p class="text-muted mb-0">Ledger de ingresos y salidas — fuente única de verdad financiera.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="/movimientos/create/ingreso" class="btn btn-success btn-sm">+ Ingreso</a>
        <a href="/movimientos/create/salida"  class="btn btn-danger btn-sm">+ Salida</a>
    </div>
</div>

{{-- Totales de la página actual (solo activos afectan balance) --}}
<div class="card mov-filter-card mb-3">
    <div class="card-body">
        <div class="mov-filter-grid">
            <div>
                <label class="mov-filter-label">Periodo</label>
                <select name="periodo" class="form-select form-select-sm mov-filter-control js-auto-filter">
                    <option value="hoy" {{ $periodo === 'hoy' ? 'selected' : '' }}>Hoy</option>
                    <option value="ayer" {{ $periodo === 'ayer' ? 'selected' : '' }}>Ayer</option>
                    <option value="semana" {{ $periodo === 'semana' ? 'selected' : '' }}>Esta semana</option>
                    <option value="mes" {{ $periodo === 'mes' ? 'selected' : '' }}>Este mes</option>
                    <option value="todo" {{ $periodo === 'todo' ? 'selected' : '' }}>Todo</option>
                    <option value="rango" {{ $periodo === 'rango' ? 'selected' : '' }}>Rango</option>
                </select>
            </div>
            <div class="periodo-rango {{ $periodo === 'rango' ? '' : 'd-none' }}">
                <label class="mov-filter-label">Desde</label>
                <input type="date" name="desde" value="{{ $desde }}" class="form-control form-control-sm mov-filter-control js-text-filter">
            </div>
            <div class="periodo-rango {{ $periodo === 'rango' ? '' : 'd-none' }}">
                <label class="mov-filter-label">Hasta</label>
                <input type="date" name="hasta" value="{{ $hasta }}" class="form-control form-control-sm mov-filter-control js-text-filter">
            </div>
            <div>
                <label class="mov-filter-label">Tipo</label>
                <select name="tipo" class="form-select form-select-sm mov-filter-control js-auto-filter">
                    <option value="">Todos</option>
                    <option value="ingreso" {{ request('tipo') === 'ingreso' ? 'selected' : '' }}>Ingreso</option>
                    <option value="salida" {{ request('tipo') === 'salida' ? 'selected' : '' }}>Salida</option>
                    <option value="contable" {{ request('tipo') === 'contable' ? 'selected' : '' }}>Contable</option>
                </select>
            </div>
            <div>
                <label class="mov-filter-label">Subtipo</label>
                <select name="subtipo" class="form-select form-select-sm mov-filter-control js-auto-filter">
                    <option value="">Todos</option>
                    <option value="pago_venta" {{ request('subtipo') === 'pago_venta' ? 'selected' : '' }}>Pago venta</option>
                    <option value="compra" {{ request('subtipo') === 'compra' ? 'selected' : '' }}>Compra</option>
                    <option value="saldo_favor_usado" {{ request('subtipo') === 'saldo_favor_usado' ? 'selected' : '' }}>Saldo favor</option>
                    <option value="manual" {{ request('subtipo') === 'manual' ? 'selected' : '' }}>Manual</option>
                    <option value="anulacion" {{ request('subtipo') === 'anulacion' ? 'selected' : '' }}>Anulación</option>
                </select>
            </div>
            <div>
                <label class="mov-filter-label">Categoría</label>
                <select name="categoria" class="form-select form-select-sm mov-filter-control js-auto-filter">
                    <option value="">Todas</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat }}" {{ request('categoria') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mov-filter-label">Empresa</label>
                <select name="empresa" class="form-select form-select-sm mov-filter-control js-auto-filter">
                    <option value="">Todas</option>
                    <option value="casadets" {{ request('empresa') === 'casadets' ? 'selected' : '' }}>CASADETS</option>
                    <option value="zendy" {{ request('empresa') === 'zendy' ? 'selected' : '' }}>ZENDY</option>
                </select>
            </div>
            <div>
                <label class="mov-filter-label">Método</label>
                <select name="metodo_pago" class="form-select form-select-sm mov-filter-control js-auto-filter">
                    <option value="">Todos</option>
                    <option value="efectivo" {{ request('metodo_pago') === 'efectivo' ? 'selected' : '' }}>Efectivo</option>
                    <option value="yape" {{ request('metodo_pago') === 'yape' ? 'selected' : '' }}>Yape</option>
                    <option value="plin" {{ request('metodo_pago') === 'plin' ? 'selected' : '' }}>Plin</option>
                    <option value="deposito" {{ request('metodo_pago') === 'deposito' ? 'selected' : '' }}>Depósito</option>
                    <option value="transferencia" {{ request('metodo_pago') === 'transferencia' ? 'selected' : '' }}>Transferencia</option>
                </select>
            </div>
            <div>
                <label class="mov-filter-label">Estado</label>
                <select name="estado" class="form-select form-select-sm mov-filter-control js-auto-filter">
                    <option value="">Todos</option>
                    <option value="activo" {{ request('estado') === 'activo' ? 'selected' : '' }}>Activo</option>
                    <option value="anulado" {{ request('estado') === 'anulado' ? 'selected' : '' }}>Anulado</option>
                </select>
            </div>
            <div class="filter-wide">
                <label class="mov-filter-label">Cliente</label>
                <input type="text" name="cliente" value="{{ request('cliente') }}" class="form-control form-control-sm mov-filter-control js-text-filter" placeholder="Buscar cliente...">
            </div>
            <div class="filter-wide">
                <label class="mov-filter-label">Documento</label>
                <input type="text" name="documento" value="{{ request('documento') }}" class="form-control form-control-sm mov-filter-control js-text-filter" placeholder="Buscar documento...">
            </div>
            <div class="d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filtrar</button>
                <a href="/movimientos" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </div>
    </div>
</div>

@if($movimientos->count())
<div class="row g-2 mb-3">
    <div class="col-md-3">
        <div class="card mov-stat-card border-success border-opacity-25">
            <div class="card-body py-2">
                <div class="small text-muted">Ingresos activos (pág.)</div>
                <div class="fw-bold text-success">S/ {{ number_format($totales['ingresos'], 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card mov-stat-card border-danger border-opacity-25">
            <div class="card-body py-2">
                <div class="small text-muted">Salidas activas (pág.)</div>
                <div class="fw-bold text-danger">S/ {{ number_format($totales['salidas'], 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card mov-stat-card {{ $totales['balance'] >= 0 ? 'border-primary' : 'border-warning' }} border-opacity-25">
            <div class="card-body py-2">
                <div class="small text-muted">Balance (pág.)</div>
                <div class="fw-bold {{ $totales['balance'] >= 0 ? 'text-primary' : 'text-warning' }}">
                    S/ {{ number_format($totales['balance'], 2) }}
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card mov-stat-card border-secondary border-opacity-25">
            <div class="card-body py-2">
                <div class="small text-muted">Registros</div>
                <div class="fw-bold text-secondary">{{ $movimientos->total() }} total</div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Tabla ledger con filas expandibles --}}
<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table mb-0 align-middle mov-table" id="tablaMovimientos">
            <thead class="table-light">
                {{-- Fila de títulos --}}
                <tr>
                    <th style="width:2rem;"></th>
                    <th>Tipo</th>
                    <th>Categoría</th>
                    <th>Empresa</th>
                    <th>Método</th>
                    <th>Cliente</th>
                    <th>Documento</th>
                    <th>Estado</th>
                    <th class="text-end">Monto</th>
                    <th>Fecha</th>
                </tr>
                {{-- Fila de filtros por columna --}}
                <tr class="align-top">
                    <td class="p-1">
                        <a href="/movimientos?empresa={{ request('empresa') }}" class="btn btn-outline-secondary btn-sm px-1 py-0" title="Limpiar filtros">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    </td>
                    {{-- Tipo + Subtipo --}}
                    <td class="p-1">
                        <select name="tipo" class="form-select form-select-sm mb-1" onchange="this.form.submit()">
                            <option value="">Todos</option>
                            <option value="ingreso"  {{ request('tipo') === 'ingreso'  ? 'selected' : '' }}>Ingreso</option>
                            <option value="salida"   {{ request('tipo') === 'salida'   ? 'selected' : '' }}>Salida</option>
                        </select>
                        <select name="subtipo" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Subtipo...</option>
                            <option value="pago_venta"        {{ request('subtipo') === 'pago_venta'        ? 'selected' : '' }}>Pago venta</option>
                            <option value="compra"            {{ request('subtipo') === 'compra'            ? 'selected' : '' }}>Compra</option>
                            <option value="saldo_favor_usado" {{ request('subtipo') === 'saldo_favor_usado' ? 'selected' : '' }}>Saldo favor</option>
                            <option value="manual"            {{ request('subtipo') === 'manual'            ? 'selected' : '' }}>Manual</option>
                            <option value="anulacion"         {{ request('subtipo') === 'anulacion'         ? 'selected' : '' }}>Anulación</option>
                        </select>
                    </td>
                    {{-- Categoría --}}
                    <td class="p-1">
                        <input type="text" name="categoria" value="{{ request('categoria') }}"
                               class="form-control form-control-sm"
                               placeholder="Buscar...">
                    </td>
                    {{-- Empresa --}}
                    <td class="p-1">
                        <select name="empresa" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Todas</option>
                            <option value="casadets" {{ request('empresa') === 'casadets' ? 'selected' : '' }}>CASADETS</option>
                            <option value="zendy"    {{ request('empresa') === 'zendy'    ? 'selected' : '' }}>ZENDY</option>
                        </select>
                    </td>
                    {{-- Método --}}
                    <td class="p-1">
                        <select name="metodo_pago" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Todos</option>
                            <option value="efectivo"      {{ request('metodo_pago') === 'efectivo'      ? 'selected' : '' }}>Efectivo</option>
                            <option value="yape"          {{ request('metodo_pago') === 'yape'          ? 'selected' : '' }}>Yape</option>
                            <option value="plin"          {{ request('metodo_pago') === 'plin'          ? 'selected' : '' }}>Plin</option>
                            <option value="deposito"      {{ request('metodo_pago') === 'deposito'      ? 'selected' : '' }}>Depósito</option>
                            <option value="transferencia" {{ request('metodo_pago') === 'transferencia' ? 'selected' : '' }}>Transferencia</option>
                        </select>
                    </td>
                    {{-- Cliente --}}
                    <td class="p-1">
                        <input type="text" name="cliente" value="{{ request('cliente') }}"
                               class="form-control form-control-sm"
                               placeholder="Buscar...">
                    </td>
                    {{-- Documento --}}
                    <td class="p-1">
                        <input type="text" name="documento" value="{{ request('documento') }}"
                               class="form-control form-control-sm"
                               placeholder="Buscar...">
                    </td>
                    {{-- Estado --}}
                    <td class="p-1">
                        <select name="estado" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Todos</option>
                            <option value="activo"  {{ request('estado') === 'activo'  ? 'selected' : '' }}>Activo</option>
                            <option value="anulado" {{ request('estado') === 'anulado' ? 'selected' : '' }}>Anulado</option>
                        </select>
                    </td>
                    {{-- Monto (vacío) --}}
                    <td class="p-1"></td>
                    {{-- Fecha: Desde / Hasta --}}
                    <td class="p-1">
                        <input type="date" name="desde" value="{{ $desde }}"
                               class="form-control form-control-sm mb-1" title="Desde">
                        <input type="date" name="hasta" value="{{ $hasta }}"
                               class="form-control form-control-sm" title="Hasta">
                    </td>
                </tr>
            </thead>
            <tbody>
                @forelse($movimientos as $m)

                {{-- Fila principal: click para expandir --}}
                <tr class="mov-row {{ $m->estado === 'anulado' ? 'text-muted' : '' }}"
                    data-bs-toggle="collapse"
                    data-bs-target="#det-{{ $m->id }}"
                    data-cliente="{{ strtolower($m->cliente->nombre ?? '') }}"
                    data-documento="{{ strtolower(($m->documento_tipo ?? '') . ' ' . ($m->documento_numero ?? '')) }}"
                    data-categoria="{{ strtolower($m->categoria ?? '') }}"
                    style="cursor:pointer;"
                    aria-expanded="false">
                    <td class="text-center text-muted">
                        <i class="bi bi-chevron-right toggle-icon small"></i>
                    </td>
                    {{-- Tipo --}}
                    <td>
                        @if($m->tipo === 'ingreso')
                            <span class="badge bg-success {{ $m->estado === 'anulado' ? 'opacity-50' : '' }}">Ingreso</span>
                        @elseif($m->tipo === 'salida')
                            <span class="badge bg-danger {{ $m->estado === 'anulado' ? 'opacity-50' : '' }}">Salida</span>
                        @elseif($m->tipo === 'contable')
                            <span class="badge bg-secondary opacity-50">Contable</span>
                        @else
                            <span class="badge bg-light text-dark border">{{ ucfirst($m->tipo) }}</span>
                        @endif
                        @if($m->subtipo === 'pago_venta')
                            <br><span class="badge bg-light text-secondary" style="font-size:.62rem;">pago venta</span>
                        @elseif($m->subtipo === 'saldo_favor_usado')
                            <br><span class="badge bg-light text-info" style="font-size:.62rem;">saldo favor</span>
                        @elseif($m->subtipo === 'compra')
                            <br><span class="badge bg-light text-warning" style="font-size:.62rem;">compra</span>
                        @elseif($m->subtipo === 'manual')
                            <br><span class="badge bg-light text-secondary" style="font-size:.62rem;">manual</span>
                        @endif
                    </td>
                    {{-- Categoría --}}
                    <td>{{ $m->categoria }}</td>
                    {{-- Empresa --}}
                    <td>
                        @if($m->empresa)
                            <span class="badge bg-light text-dark border" style="font-size:.65rem;">{{ strtoupper($m->empresa) }}</span>
                        @endif
                    </td>
                    {{-- Método de pago --}}
                    <td>
                        @php
                            $mpIconos = [
                                'efectivo'      => ['bi-cash',   'text-warning'],
                                'yape'          => ['bi-phone',  'text-success'],
                                'plin'          => ['bi-phone',  'text-info'],
                                'deposito'      => ['bi-bank',   'text-primary'],
                                'transferencia' => ['bi-bank',   'text-primary'],
                            ];
                            $mpVal  = $m->metodo_pago ?? null;
                            $mpIcon = $mpIconos[$mpVal] ?? null;
                        @endphp
                        @if($mpVal && $mpIcon)
                            <span class="small {{ $mpIcon[1] }}">
                                <i class="bi {{ $mpIcon[0] }} me-1"></i>{{ ucfirst($mpVal) }}
                            </span>
                        @elseif($mpVal)
                            <span class="small text-muted">{{ ucfirst($mpVal) }}</span>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    {{-- Cliente --}}
                    <td class="small text-muted">{{ $m->cliente->nombre ?? '—' }}</td>
                    {{-- Documento: para pagos automáticos muestra las ventas del pago --}}
                    <td class="small">
                        @if($m->documento_tipo)
                            <span class="text-muted">{{ ucfirst($m->documento_tipo) }} {{ $m->documento_numero }}</span>
                        @elseif($m->referencia_tipo === 'pago' && $m->pago && $m->pago->detalles->count())
                            @foreach($m->pago->detalles->take(3) as $dpf)
                                @php
                                    $v = $dpf->venta;
                                    $label = trim(ucfirst($v?->documento_tipo ?? 'Venta').' '.($v?->documento_numero ?? '#'.($dpf->venta_id ?? '')));
                                @endphp
                                <span class="d-block text-muted">{{ $label }}</span>
                            @endforeach
                            @if($m->pago->detalles->count() > 3)
                                <span class="text-muted" style="font-size:.7rem;">+{{ $m->pago->detalles->count() - 3 }} más</span>
                            @endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    {{-- Estado --}}
                    <td>
                        @if($m->estado === 'anulado')
                            <span class="badge bg-secondary" style="font-size:.65rem;">Anulado</span>
                        @else
                            <span class="badge bg-success bg-opacity-10 text-success" style="font-size:.65rem;">Activo</span>
                        @endif
                    </td>
                    {{-- Monto --}}
                    <td class="text-end fw-semibold {{ $m->estado === 'anulado' ? 'text-decoration-line-through text-muted' : ($m->tipo === 'ingreso' ? 'text-success' : ($m->tipo === 'salida' ? 'text-danger' : 'text-secondary')) }}">
                        @if($m->tipo === 'ingreso' && $m->estado !== 'anulado') + @elseif($m->tipo === 'salida' && $m->estado !== 'anulado') − @endif
                        S/ {{ number_format($m->monto, 2) }}
                    </td>
                    {{-- Fecha --}}
                    <td class="small text-muted">{{ $m->fecha->format('d/m/Y') }}</td>
                </tr>

                {{-- Fila de detalle expandible --}}
                <tr class="collapse-row">
                    <td colspan="10" class="p-0 border-0">
                        <div class="collapse" id="det-{{ $m->id }}">
                            <div class="px-4 py-3 bg-light border-bottom">
                                <div class="row g-3">

                                    {{-- Columna 1: Información básica --}}
                                    <div class="col-md-3">
                                        <h6 class="text-uppercase text-muted small mb-2">Información</h6>
                                        <dl class="row small mb-0">
                                            <dt class="col-5 text-muted fw-normal">Tipo</dt>
                                            <dd class="col-7 mb-1">{{ ucfirst($m->tipo) }}</dd>

                                            @if($m->subtipo)
                                            <dt class="col-5 text-muted fw-normal">Subtipo</dt>
                                            <dd class="col-7 mb-1">{{ str_replace('_', ' ', $m->subtipo) }}</dd>
                                            @endif

                                            <dt class="col-5 text-muted fw-normal">Estado</dt>
                                            <dd class="col-7 mb-1">
                                                <span class="badge {{ $m->estado === 'anulado' ? 'bg-secondary' : 'bg-success' }}">
                                                    {{ ucfirst($m->estado ?? 'activo') }}
                                                </span>
                                            </dd>

                                            <dt class="col-5 text-muted fw-normal">Empresa</dt>
                                            <dd class="col-7 mb-1">{{ strtoupper($m->empresa ?? 'casadets') }}</dd>

                                            <dt class="col-5 text-muted fw-normal">Origen</dt>
                                            <dd class="col-7 mb-1">
                                                <span class="badge {{ ($m->origen ?? 'manual') === 'auto' ? 'bg-info text-dark' : 'bg-secondary' }}" style="font-size:.65rem;">
                                                    {{ ($m->origen ?? 'manual') === 'auto' ? 'automático' : 'manual' }}
                                                </span>
                                            </dd>

                                            <dt class="col-5 text-muted fw-normal">Fecha</dt>
                                            <dd class="col-7 mb-1">{{ $m->fecha->format('d/m/Y') }}</dd>

                                            <dt class="col-5 text-muted fw-normal">Monto</dt>
                                            <dd class="col-7 mb-1 fw-semibold">S/ {{ number_format($m->monto, 2) }}</dd>

                                            @if($m->referencia_id)
                                            <dt class="col-5 text-muted fw-normal">Ref.</dt>
                                            <dd class="col-7 mb-1 text-muted">
                                                {{ ucfirst($m->referencia_tipo ?? '') }} #{{ $m->referencia_id }}
                                            </dd>
                                            @endif

                                            @if($m->observaciones)
                                            <dt class="col-5 text-muted fw-normal">Nota</dt>
                                            <dd class="col-7 mb-0 text-muted">{{ $m->observaciones }}</dd>
                                            @endif
                                        </dl>
                                    </div>

                                    {{-- Columna 2: Cliente --}}
                                    @if($m->cliente)
                                    <div class="col-md-2">
                                        <h6 class="text-uppercase text-muted small mb-2">Cliente</h6>
                                        <p class="small mb-0 fw-semibold">{{ $m->cliente->nombre }}</p>
                                        @if($m->cliente->documento)
                                            <p class="small text-muted mb-0">{{ $m->cliente->documento }}</p>
                                        @endif
                                    </div>
                                    @endif

                                    {{-- Columna 3: Métodos de pago (solo si viene de CobranzaService) --}}
                                    @if($m->pago && $m->referencia_tipo === 'pago' && $m->pago->metodos->count())
                                    <div class="col-md-3">
                                        <h6 class="text-uppercase text-muted small mb-2">Métodos de pago</h6>
                                        <table class="table table-xs table-sm mb-0" style="font-size:.85rem;">
                                            <tbody>
                                                @foreach($m->pago->metodos as $pm)
                                                <tr>
                                                    <td class="py-1 ps-0 border-0">
                                                        <span class="badge bg-success bg-opacity-10 text-success">
                                                            {{ ucfirst($pm->metodo) }}
                                                        </span>
                                                    </td>
                                                    <td class="py-1 pe-0 border-0 text-end fw-semibold">
                                                        S/ {{ number_format($pm->monto, 2) }}
                                                    </td>
                                                </tr>
                                                @endforeach
                                                @if($m->pago->metodos->count() > 1)
                                                <tr class="border-top">
                                                    <td class="py-1 ps-0 text-muted small">Total</td>
                                                    <td class="py-1 pe-0 text-end fw-bold">
                                                        S/ {{ number_format($m->pago->metodos->sum('monto'), 2) }}
                                                    </td>
                                                </tr>
                                                @endif
                                            </tbody>
                                        </table>
                                    </div>
                                    @endif

                                    {{-- Columna 4: Ventas aplicadas (solo para pago_venta) --}}
                                    @if($m->pago && $m->referencia_tipo === 'pago' && $m->pago->detalles->count())
                                    <div class="col-md-4">
                                        <h6 class="text-uppercase text-muted small mb-2">Ventas aplicadas</h6>
                                        @foreach($m->pago->detalles as $dpf)
                                        @php
                                            $venta = $dpf->venta;
                                            $docLabel = trim(ucfirst($venta?->documento_tipo ?? 'Venta') . ' ' . ($venta?->documento_numero ?? '#'.($dpf->venta_id)));
                                            $saldoPendiente = max(0, (float)($venta?->total ?? 0) - (float)($venta?->pagado ?? 0));
                                        @endphp
                                        <div class="border rounded p-2 mb-1" style="font-size:.85rem;">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <span class="fw-semibold">{{ $docLabel }}</span>
                                                <span class="text-success fw-bold ms-2">
                                                    S/ {{ number_format($dpf->monto_aplicado, 2) }}
                                                </span>
                                            </div>
                                            <div class="text-muted mt-1">
                                                Total: <strong>S/ {{ number_format($venta?->total ?? 0, 2) }}</strong>
                                                &nbsp;·&nbsp;
                                                Saldo: <strong class="{{ $saldoPendiente > 0 ? 'text-warning' : 'text-success' }}">
                                                    S/ {{ number_format($saldoPendiente, 2) }}
                                                </strong>
                                            </div>
                                            @if($venta)
                                            <div class="mt-1">
                                                <a href="/casadets/ventas/{{ $venta->id }}" class="small text-muted">
                                                    Ver venta →
                                                </a>
                                            </div>
                                            @endif
                                        </div>
                                        @endforeach
                                    </div>
                                    @endif

                                </div>{{-- /row --}}

                                {{-- Botón anular (solo activos) --}}
                                @if($m->estado === 'activo')
                                <div class="border-top mt-3 pt-3 d-flex align-items-center gap-2">
                                    <button type="button"
                                            class="btn btn-outline-danger btn-sm"
                                            onclick="abrirModalAnularMov({{ $m->id }}, '{{ number_format($m->monto, 2) }}', '{{ addslashes($m->categoria) }}', {{ $m->referencia_tipo === 'pago' ? 'true' : 'false' }})">
                                        <i class="bi bi-x-circle me-1"></i>Anular Movimiento
                                    </button>
                                    @if($m->referencia_tipo === 'pago')
                                        <span class="text-muted small">
                                            <i class="bi bi-info-circle me-1"></i>
                                            Anular este movimiento también marcará el pago como inválido y revertirá el estado del vale.
                                        </span>
                                    @else
                                        <span class="text-muted small">
                                            <i class="bi bi-info-circle me-1"></i>
                                            Se creará una contrapartida contable para anular el efecto financiero.
                                        </span>
                                    @endif
                                </div>
                                @endif

                            </div>
                        </div>
                    </td>
                </tr>

                @empty
                <tr>
                    <td colspan="10" class="text-center text-muted py-5">
                        No hay movimientos registrados.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($movimientos->hasPages())
<div class="d-flex justify-content-center mt-3">
    {{ $movimientos->links() }}
</div>
@endif

</form>

{{-- Alertas de sesión --}}
@if(session('success'))
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:1100;">
    <div class="alert alert-success alert-dismissible fade show shadow-sm py-2" role="alert">
        <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>
@endif
@if(session('info'))
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:1100;">
    <div class="alert alert-info alert-dismissible fade show shadow-sm py-2" role="alert">
        <i class="bi bi-info-circle me-1"></i>{{ session('info') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>
@endif
@if(session('error'))
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:1100;">
    <div class="alert alert-danger alert-dismissible fade show shadow-sm py-2" role="alert">
        <i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>
@endif

{{-- Modal confirmación anulación de movimiento --}}
<div class="modal fade" id="modalAnularMov" tabindex="-1" aria-labelledby="modalAnularMovLabel">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title text-danger" id="modalAnularMovLabel">
                    <i class="bi bi-x-circle me-2"></i>Anular Movimiento
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAnularMov" method="POST" action="">
                @csrf
                <div class="modal-body">
                    <div class="bg-light rounded p-3 mb-3 small">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted" id="anularMovCategoria">—</span>
                            <strong class="text-danger" id="anularMovMonto">S/ 0.00</strong>
                        </div>
                    </div>

                    {{-- Aviso especial para movimientos de pago --}}
                    <div id="avisoPago" class="alert alert-warning py-2 small d-none">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <strong>Este movimiento está vinculado a un pago de venta.</strong><br>
                        Al anularlo:
                        <ul class="mb-0 mt-1 ps-3">
                            <li>El pago quedará marcado como <strong>inválido</strong></li>
                            <li>El vale volverá a estado <strong>Pendiente</strong></li>
                            <li>Se registrará en el módulo de <strong>Devoluciones</strong></li>
                        </ul>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Motivo de anulación <span class="text-muted fw-normal">(opcional)</span></label>
                        <input type="text" name="motivo" class="form-control form-control-sm"
                               placeholder="Ej: Error de registro, pago duplicado...">
                    </div>

                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="confirmarAnularMov">
                        <label class="form-check-label small" for="confirmarAnularMov">
                            Entiendo que esta acción <strong>no se puede deshacer</strong>.
                        </label>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger" id="btnAnularMovConfirm" disabled>
                        <i class="bi bi-x-circle me-1"></i>Anular Movimiento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.mov-table thead tr.align-top [name]').forEach(el => {
    el.disabled = true;
});

const formFiltros = document.getElementById('formFiltros');
const periodoSelect = formFiltros?.querySelector('select[name="periodo"]');
const rangoCampos = document.querySelectorAll('.periodo-rango');
let filtroTimer = null;

function toggleRangoPeriodo() {
    const esRango = periodoSelect?.value === 'rango';
    rangoCampos.forEach(el => el.classList.toggle('d-none', !esRango));
}

function enviarFiltros(delay = 0) {
    clearTimeout(filtroTimer);
    filtroTimer = setTimeout(() => formFiltros?.requestSubmit(), delay);
}

document.querySelectorAll('.js-auto-filter').forEach(el => {
    el.addEventListener('change', () => {
        toggleRangoPeriodo();
        enviarFiltros(0);
    });
});

document.querySelectorAll('.js-text-filter').forEach(el => {
    el.addEventListener('input', () => enviarFiltros(450));
    el.addEventListener('change', () => enviarFiltros(0));
});

toggleRangoPeriodo();

document.querySelectorAll('.mov-row').forEach(function(row) {
    const targetId  = row.getAttribute('data-bs-target');
    const collapseEl = document.querySelector(targetId);
    if (!collapseEl) return;

    collapseEl.addEventListener('show.bs.collapse', function() {
        row.querySelector('.toggle-icon')?.classList.replace('bi-chevron-right', 'bi-chevron-down');
        row.classList.add('table-active');
    });
    collapseEl.addEventListener('hide.bs.collapse', function() {
        row.querySelector('.toggle-icon')?.classList.replace('bi-chevron-down', 'bi-chevron-right');
        row.classList.remove('table-active');
    });
});

/* ── Filtrado instantáneo en columnas de texto ── */
(function () {
    function norm(s) { return (s||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,''); }

    function aplicarTexto() {
        // Lee los inputs de texto tanto del panel de filtros superior como de la fila de columnas
        const tCliente   = norm(document.querySelector('input[name="cliente"]')?.value  || '');
        const tDocumento = norm(document.querySelector('input[name="documento"]')?.value || '');
        const tCategoria = norm(document.querySelector('input[name="categoria"]')?.value  || '');

        document.querySelectorAll('tr.mov-row[data-cliente]').forEach(tr => {
            const ok =
                (!tCliente   || norm(tr.dataset.cliente).includes(tCliente))     &&
                (!tDocumento || norm(tr.dataset.documento).includes(tDocumento)) &&
                (!tCategoria || norm(tr.dataset.categoria).includes(tCategoria));
            tr.style.display = ok ? '' : 'none';
            // También ocultar la fila de detalle colapsable
            const target = tr.dataset.bsTarget;
            if (target) {
                const det = document.querySelector(target);
                if (det) det.style.display = ok ? '' : 'none';
            }
        });
    }

    // Enlazar todos los inputs de texto de filtros (panel superior y columnas de tabla)
    document.querySelectorAll('input[name="cliente"], input[name="documento"], input[name="categoria"]')
        .forEach(el => el.addEventListener('input', aplicarTexto));
})();

/* ── Modal de anulación de movimiento ── */
function abrirModalAnularMov(id, monto, categoria, esPago) {
    document.getElementById('anularMovMonto').textContent    = 'S/ ' + monto;
    document.getElementById('anularMovCategoria').textContent = categoria;
    document.getElementById('formAnularMov').action          = '/movimientos/' + id + '/anular';

    var avisoPago = document.getElementById('avisoPago');
    if (esPago) {
        avisoPago.classList.remove('d-none');
    } else {
        avisoPago.classList.add('d-none');
    }

    // Reset estado del modal
    document.getElementById('confirmarAnularMov').checked   = false;
    document.getElementById('btnAnularMovConfirm').disabled = true;
    document.querySelector('#formAnularMov input[name="motivo"]').value = '';

    new bootstrap.Modal(document.getElementById('modalAnularMov')).show();
}

document.getElementById('confirmarAnularMov')?.addEventListener('change', function () {
    document.getElementById('btnAnularMovConfirm').disabled = !this.checked;
});
</script>
@endsection
