@extends('layouts.app')

@section('content')
<style>
.fila-pagado  { background: #d1e7dd !important; }
.fila-anulado { background: #f8d7da !important; opacity:.85; }
.estado-badge { font-size:.72rem; padding:.2rem .55rem; border-radius:20px; font-weight:600; }
.estado-pagado  { background:#198754; color:#fff; }
.estado-pendiente { background:#dee2e6; color:#495057; }
.estado-anulado { background:#dc3545; color:#fff; }
.btn-estado { font-size:.72rem; padding:.15rem .45rem; border-radius:20px; cursor:pointer; border:1px solid; white-space:nowrap; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">Ventas</h3>
        <p class="text-muted mb-0">Registro de ventas por vendedor.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/casadets/ventas/import" class="btn btn-outline-success">
            <i class="bi bi-file-earmark-spreadsheet"></i> Importar Excel
        </a>
        <a href="/casadets/ventas/create" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Nueva venta
        </a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-1">Vendedor</label>
                <select name="vendedor_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($vendedores as $v)
                        <option value="{{ $v->id }}" {{ request('vendedor_id') == $v->id ? 'selected' : '' }}>{{ $v->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Tipo</label>
                <select name="tipo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach(['factura'=>'Factura','boleta'=>'Boleta','proforma'=>'Proforma'] as $k=>$lbl)
                        <option value="{{ $k }}" {{ request('tipo')==$k ? 'selected' : '' }}>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach(['pendiente'=>'Pendiente','pagado'=>'Pagado','anulado'=>'Anulado'] as $k=>$lbl)
                        <option value="{{ $k }}" {{ request('estado')==$k ? 'selected' : '' }}>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Desde</label>
                <input type="date" name="desde" value="{{ request('desde') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Hasta</label>
                <input type="date" name="hasta" value="{{ request('hasta') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary">Filtrar</button>
                <a href="/casadets/ventas" class="btn btn-sm btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Estado</th>
                    <th>Fecha</th>
                    <th>Vendedor</th>
                    <th>Productos</th>
                    <th>Pago</th>
                    <th>Documento</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ventas as $v)
                @php
                    $metodosArr = array_filter(explode(',', $v->metodo_pago ?? ''));
                    $estado = $v->estado ?? 'pendiente';
                    $filaClase = $estado === 'pagado' ? 'fila-pagado' : ($estado === 'anulado' ? 'fila-anulado' : '');
                @endphp
                <tr class="{{ $filaClase }}">
                    <td>
                        {{-- Badge de estado actual --}}
                        <span class="estado-badge estado-{{ $estado }}">
                            @if($estado === 'pagado') <i class="bi bi-check-circle-fill me-1"></i>Pagado
                            @elseif($estado === 'anulado') <i class="bi bi-x-circle-fill me-1"></i>Anulado
                            @else <i class="bi bi-clock me-1"></i>Pendiente
                            @endif
                        </span>
                        {{-- Botones de cambio de estado --}}
                        <div class="d-flex gap-1 mt-1 flex-wrap">
                            @if($estado !== 'pagado')
                            <form action="/casadets/ventas/{{ $v->id }}/estado" method="POST" class="d-inline">
                                @csrf
                                <input type="hidden" name="estado" value="pagado">
                                <button class="btn-estado border-success text-success bg-transparent">✓ Pagado</button>
                            </form>
                            @endif
                            @if($estado !== 'pendiente')
                            <form action="/casadets/ventas/{{ $v->id }}/estado" method="POST" class="d-inline">
                                @csrf
                                <input type="hidden" name="estado" value="pendiente">
                                <button class="btn-estado border-secondary text-secondary bg-transparent">⏳ Pendiente</button>
                            </form>
                            @endif
                            @if($estado !== 'anulado')
                            <form action="/casadets/ventas/{{ $v->id }}/estado" method="POST" class="d-inline">
                                @csrf
                                <input type="hidden" name="estado" value="anulado">
                                <button class="btn-estado border-danger text-danger bg-transparent">✕ Anular</button>
                            </form>
                            @endif
                        </div>
                    </td>
                    <td>{{ $v->fecha->format('d/m/Y') }}</td>
                    <td>{{ $v->vendedor->nombre ?? '—' }}</td>
                    <td>
                        @if($v->detalles->count() == 1)
                            {{ $v->detalles->first()->producto }}
                        @else
                            <span class="badge bg-info text-dark">{{ $v->detalles->count() }} productos</span>
                            <small class="text-muted d-block">{{ $v->detalles->pluck('producto')->take(2)->implode(', ') }}{{ $v->detalles->count() > 2 ? '…' : '' }}</small>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            @forelse($metodosArr as $m)
                                <span class="badge bg-light text-dark border">{{ ucfirst(trim($m)) }}</span>
                            @empty
                                <span class="text-muted">—</span>
                            @endforelse
                        </div>
                    </td>
                    <td>
                        @if($v->documento_tipo)
                            {{ ucfirst($v->documento_tipo) }} {{ $v->documento_numero }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-end fw-semibold">
                        S/ {{ number_format($v->total_cobrado, 2) }}
                        @if($v->ajuste != 0)
                            <br><small class="{{ $v->ajuste > 0 ? 'text-success' : 'text-danger' }}">
                                ({{ $v->ajuste > 0 ? '+' : '' }}{{ number_format($v->ajuste, 2) }})
                            </small>
                        @endif
                    </td>
                    <td class="text-end">
                        <div class="d-flex justify-content-end gap-1 flex-wrap">
                            <a href="/casadets/ventas/{{ $v->id }}" class="btn btn-sm btn-outline-secondary">Ver</a>
                            <a href="/casadets/ventas/{{ $v->id }}/edit" class="btn btn-sm btn-outline-primary">Editar</a>
                            <a href="/casadets/ventas/{{ $v->id }}/pago" class="btn btn-sm btn-outline-success" title="Verificar pago">
                                <i class="bi bi-cash-stack"></i>
                            </a>
                            <form action="/casadets/ventas/{{ $v->id }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar venta?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No hay ventas registradas.</td></tr>
                @endforelse
            </tbody>
            @if($ventas->count())
            <tfoot>
                <tr class="table-light">
                    <th colspan="6" class="text-end">Total cobrado</th>
                    <th class="text-end">S/ {{ number_format($ventas->sum(fn($v) => $v->total_cobrado), 2) }}</th>
                    <th></th>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
