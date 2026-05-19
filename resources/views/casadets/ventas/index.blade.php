@extends('layouts.app')

@section('content')
<style>
.fila-pagado  { background: #d1e7dd !important; }
.fila-anulado { background: #f8d7da !important; opacity:.85; }
.select-estado { font-size:.78rem; padding:.2rem .5rem; border-radius:20px; font-weight:600; cursor:pointer; border:1.5px solid; appearance:none; -webkit-appearance:none; text-align:center; min-width:110px; }
.select-estado.est-pendiente { border-color:#adb5bd; background:#f8f9fa; color:#495057; }
.select-estado.est-pagado    { border-color:#198754; background:#d1e7dd; color:#155724; }
.select-estado.est-anulado   { border-color:#dc3545; background:#f8d7da; color:#842029; }
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
                    <th style="width:125px;">Estado</th>
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
                        <form action="/casadets/ventas/{{ $v->id }}/estado" method="POST">
                            @csrf
                            <select name="estado"
                                class="select-estado est-{{ $estado }}"
                                onchange="this.form.submit()">
                                <option value="pendiente" {{ $estado==='pendiente'?'selected':'' }}>⏳ Pendiente</option>
                                <option value="pagado"    {{ $estado==='pagado'   ?'selected':'' }}>✓ Pagado</option>
                                <option value="anulado"   {{ $estado==='anulado'  ?'selected':'' }}>✕ Anulado</option>
                            </select>
                        </form>
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

<script>
// Actualizar color del select al cambiar valor
document.querySelectorAll('.select-estado').forEach(sel => {
    sel.addEventListener('change', function() {
        this.className = 'select-estado est-' + this.value;
    });
});
</script>
@endsection
