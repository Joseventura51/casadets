@extends('layouts.app')

@section('content')
@php $porVenta = $compra->detalles->groupBy('venta_id'); @endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-2">
        <h3 class="mb-0">Compra #{{ $compra->id }}</h3>
        @if($compra->es_supuesto)
            <span class="badge" style="background:#fef3c7;color:#92400e;font-size:.78rem;">
                <i class="bi bi-tag-fill me-1"></i> VALE SUPUESTO
            </span>
        @endif
    </div>
    <div class="d-flex gap-2">
        @if($compra->es_supuesto && !($compra->ajusteSupuesto?->compra_real_id))
            <a href="/casadets/compras/{{ $compra->id }}/reconciliar" class="btn btn-warning btn-sm">
                <i class="bi bi-arrow-left-right me-1"></i> Reconciliar precio real
            </a>
        @else
            <a href="/casadets/compras/{{ $compra->id }}/edit" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> Editar</a>
        @endif
        <a href="/casadets/compras" class="btn btn-outline-secondary btn-sm">← Volver</a>
    </div>
</div>

@if($compra->es_supuesto)
    @php $ajuste = $compra->ajusteSupuesto; @endphp
    @if($ajuste && $ajuste->compra_real_id)
        <div class="alert d-flex align-items-center gap-3 mb-3 py-2"
             style="background:#f0fdf4;border:1px solid #bbf7d0;">
            <i class="bi bi-check-circle-fill text-success fs-5 flex-shrink-0"></i>
            <div class="flex-grow-1">
                <strong>Vale reconciliado</strong> — Compra real <strong>#{{ $ajuste->compra_real_id }}</strong> registrada el {{ $ajuste->compraReal?->fecha?->format('d/m/Y') ?? '—' }}.
                @if($ajuste->diferencia_total != 0)
                    Diferencia:
                    <strong class="{{ $ajuste->diferencia_total > 0 ? 'text-danger' : 'text-success' }}">
                        {{ $ajuste->diferencia_total > 0 ? '+' : '' }}S/ {{ number_format($ajuste->diferencia_total, 2) }}
                    </strong>
                    ({{ $ajuste->diferencia_total > 0 ? 'real fue más caro' : 'real fue más barato' }})
                    — {{ $ajuste->aplicado ? 'Aplicado en cierre semanal' : 'Pendiente de aplicar en próximo cierre' }}.
                @else
                    Sin diferencia de precio.
                @endif
            </div>
            <a href="/casadets/compras/{{ $ajuste->compra_real_id }}" class="btn btn-sm btn-outline-success flex-shrink-0">
                Ver compra real
            </a>
        </div>
    @else
        <div class="alert d-flex align-items-center gap-3 mb-3 py-2"
             style="background:#fffbeb;border:1px solid #fde68a;">
            <i class="bi bi-clock-fill text-warning fs-5 flex-shrink-0"></i>
            <div class="flex-grow-1">
                <strong>Precio estimado — pendiente de reconciliar.</strong>
                La utilidad calculada con este vale es <em>aproximada</em>. Cuando el proveedor entregue la factura real, usa el botón para registrar el precio definitivo.
            </div>
            <a href="/casadets/compras/{{ $compra->id }}/reconciliar" class="btn btn-sm btn-warning flex-shrink-0">
                <i class="bi bi-arrow-left-right me-1"></i> Reconciliar
            </a>
        </div>
    @endif
@endif

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card kpi-card"><small class="text-muted">Fecha</small><h6 class="mb-0">{{ $compra->fecha->format('d/m/Y') }}</h6></div></div>
    <div class="col-md-3"><div class="card kpi-card"><small class="text-muted">Empresa</small><h6 class="mb-0">{{ $compra->empresa }}</h6></div></div>
    <div class="col-md-2"><div class="card kpi-card"><small class="text-muted">Documento</small><h6 class="mb-0">{{ $compra->documento_tipo ? ucfirst($compra->documento_tipo) : '—' }} {{ $compra->documento_numero }}</h6></div></div>
    <div class="col-md-2">
        <div class="card kpi-card">
            <small class="text-muted">Método de pago</small>
            <h6 class="mb-0">
                @if($compra->metodo_pago === 'efectivo')
                    <span class="badge bg-warning text-dark"><i class="bi bi-cash me-1"></i>Efectivo</span>
                @elseif($compra->metodo_pago === 'transferencia')
                    <span class="badge bg-primary"><i class="bi bi-bank me-1"></i>Transferencia</span>
                @else
                    <span class="text-muted">—</span>
                @endif
            </h6>
        </div>
    </div>
    <div class="col-md-2"><div class="card kpi-card"><small class="text-muted">Total</small><h6 class="mb-0 text-primary">S/ {{ number_format($compra->monto_total, 2) }}</h6></div></div>
</div>

@if($compra->observaciones)
<div class="alert alert-light border mb-3"><small class="text-muted">Observaciones:</small> {{ $compra->observaciones }}</div>
@endif

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-list-ul me-1"></i> Productos comprados</div>
    @if($compra->lineas->count())
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Producto / Descripción</th>
                    <th class="text-end" style="width:100px;">Cantidad</th>
                    <th class="text-end" style="width:110px;">P. Unitario</th>
                    <th class="text-end" style="width:120px;">Total línea</th>
                </tr>
            </thead>
            <tbody>
                @foreach($compra->lineas as $l)
                <tr>
                    <td>{{ $l->producto ?? '—' }}</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format($l->cantidad, 2), '0'), '.') }}</td>
                    <td class="text-end">S/ {{ number_format($l->monto_unitario, 2) }}</td>
                    <td class="text-end fw-semibold">S/ {{ number_format($l->monto_total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="3" class="text-end">Total general</th>
                    <th class="text-end text-primary fs-6">S/ {{ number_format($compra->monto_total, 2) }}</th>
                </tr>
            </tfoot>
        </table>
    </div>
    @else
    <div class="card-body text-center text-muted py-3">Sin líneas de producto registradas.</div>
    @endif
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-link-45deg me-1 text-warning"></i> Productos vinculados de ventas
    </div>
    @if($porVenta->count())
        @foreach($porVenta as $ventaId => $detalles)
            @php $venta = $detalles->first()->venta; @endphp
            <div class="border-bottom">
                <div class="px-3 py-2 bg-light d-flex justify-content-between align-items-center">
                    <div>
                        <strong>{{ $venta->documento_tipo ? ucfirst($venta->documento_tipo) : '' }} {{ $venta->documento_numero }}</strong>
                        <span class="text-muted small ms-2">{{ $venta->fecha->format('d/m/Y') }} · {{ $venta->vendedor->nombre ?? '—' }}</span>
                    </div>
                    <a href="/casadets/ventas/{{ $venta->id }}" class="btn btn-sm btn-outline-secondary">Ver venta</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Producto</th>
                                <th class="text-end">Cant. venta</th>
                                <th class="text-end text-warning-emphasis">Cant. comprada</th>
                                <th class="text-end">Precio venta</th>
                                <th class="text-end">Subtotal venta</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($detalles as $d)
                            <tr>
                                <td>{{ $d->producto }}</td>
                                <td class="text-end text-muted">{{ rtrim(rtrim(number_format($d->cantidad, 2), '0'), '.') }}</td>
                                <td class="text-end">
                                    <span class="badge bg-warning text-dark">
                                        {{ rtrim(rtrim(number_format($d->pivot->cantidad ?? 1, 2), '0'), '.') }}
                                    </span>
                                </td>
                                <td class="text-end">S/ {{ number_format($d->precio_unitario, 2) }}</td>
                                <td class="text-end">S/ {{ number_format($d->subtotal, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    @else
        <div class="card-body text-center text-muted py-4">
            <i class="bi bi-info-circle me-1"></i> Esta compra no tiene productos vinculados a ninguna venta.
        </div>
    @endif
</div>

{{-- AUDITORÍA DE ASIGNACIONES --}}
<div class="card border-0 shadow-sm mt-2">
    <div class="card-header bg-white d-flex align-items-center justify-content-between">
        <span class="fw-semibold">
            <i class="bi bi-shield-lock me-1 text-secondary"></i> Auditoría de asignaciones
        </span>
        <span class="badge bg-secondary rounded-pill">{{ $compra->auditorias->count() }} eventos</span>
    </div>
    @if($compra->auditorias->count())
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle" style="font-size:.82rem;">
            <thead class="table-light">
                <tr>
                    <th style="width:130px;">Fecha / Hora</th>
                    <th style="width:90px;">Acción</th>
                    <th>Producto</th>
                    <th class="text-end" style="width:110px;">Cantidad</th>
                    <th class="text-end" style="width:120px;">Costo unit.</th>
                    <th class="text-end" style="width:120px;">Costo total</th>
                    <th style="width:130px;">Usuario</th>
                    <th style="width:110px;">IP</th>
                </tr>
            </thead>
            <tbody>
                @foreach($compra->auditorias as $aud)
                <tr>
                    <td class="text-muted">{{ $aud->created_at->format('d/m/Y H:i:s') }}</td>
                    <td>
                        <span class="badge bg-{{ $aud->accionBadge() }}">
                            {{ $aud->accionLabel() }}
                        </span>
                    </td>
                    <td>{{ $aud->producto_nombre ?? '—' }}</td>
                    <td class="text-end">
                        @if($aud->accion === 'actualizar')
                            <span class="text-muted text-decoration-line-through me-1">
                                {{ number_format($aud->cantidad_anterior, 2) }}
                            </span>
                            {{ number_format($aud->cantidad_nueva, 2) }}
                        @elseif($aud->accion === 'crear')
                            {{ number_format($aud->cantidad_nueva, 2) }}
                        @else
                            <span class="text-danger">{{ number_format($aud->cantidad_anterior, 2) }}</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @if($aud->accion === 'actualizar' && $aud->costo_unitario_anterior != $aud->costo_unitario_nuevo)
                            <span class="text-muted text-decoration-line-through me-1">
                                S/ {{ number_format($aud->costo_unitario_anterior ?? 0, 4) }}
                            </span>
                            S/ {{ number_format($aud->costo_unitario_nuevo ?? 0, 4) }}
                        @else
                            @php $cu = $aud->costo_unitario_nuevo ?? $aud->costo_unitario_anterior; @endphp
                            {{ $cu !== null ? 'S/ ' . number_format($cu, 4) : '—' }}
                        @endif
                    </td>
                    <td class="text-end">
                        @if($aud->accion === 'actualizar' && $aud->costo_total_anterior != $aud->costo_total_nuevo)
                            <span class="text-muted text-decoration-line-through me-1">
                                S/ {{ number_format($aud->costo_total_anterior ?? 0, 2) }}
                            </span>
                            S/ {{ number_format($aud->costo_total_nuevo ?? 0, 2) }}
                        @else
                            @php $ct = $aud->costo_total_nuevo ?? $aud->costo_total_anterior; @endphp
                            {{ $ct !== null ? 'S/ ' . number_format($ct, 2) : '—' }}
                        @endif
                    </td>
                    <td class="text-muted">{{ $aud->usuario?->nombre ?? $aud->usuario?->name ?? '—' }}</td>
                    <td class="text-muted" style="font-size:.75rem;">{{ $aud->ip ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="card-body text-center text-muted py-3">
        <i class="bi bi-clock-history me-1"></i> Sin eventos de auditoría registrados aún.
    </div>
    @endif
</div>
@endsection
