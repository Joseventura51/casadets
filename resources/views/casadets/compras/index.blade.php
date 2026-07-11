@extends('layouts.app')

@section('content')
@if(!$cajaAbierta)
<div class="alert alert-warning d-flex align-items-center gap-2 mb-3 py-2">
    <i class="bi bi-lock-fill fs-5 flex-shrink-0"></i>
    <div class="flex-grow-1">
        <strong>Caja cerrada.</strong> No puedes registrar ni modificar compras hasta que se abra la caja del día.
    </div>
    <a href="/casadets/caja" class="btn btn-sm btn-warning flex-shrink-0">
        <i class="bi bi-box-arrow-in-right me-1"></i>Ir a Caja
    </a>
</div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">Compras</h3>
        <p class="text-muted mb-0">Compras a proveedores. Pueden vincularse a ventas.</p>
    </div>
    @if($cajaAbierta)
    <a href="/casadets/compras/create" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Nueva compra
    </a>
    @else
    <button class="btn btn-primary" disabled title="Abre la caja primero">
        <i class="bi bi-lock me-1"></i> Nueva compra
    </button>
    @endif
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end" data-dynamic-filter data-default-today>
            <div class="col-md-4">
                <label class="form-label small mb-1">Empresa / Proveedor</label>
                <input type="text" name="empresa" value="{{ request('empresa') }}" class="form-control form-control-sm" placeholder="Buscar...">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Desde</label>
                <input type="date" name="desde" value="{{ $desde }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Hasta</label>
                <input type="date" name="hasta" value="{{ $hasta }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2 d-flex flex-column gap-1 pt-1">
                <div class="form-check form-check-sm mb-0">
                    <input class="form-check-input" type="checkbox" name="solo_supuestos" id="soloSupuestos" value="1" {{ $soloSupuestos ? 'checked' : '' }}>
                    <label class="form-check-label small" for="soloSupuestos">Solo vales supuestos</label>
                </div>
                <div class="form-check form-check-sm mb-0">
                    <input class="form-check-input" type="checkbox" name="sin_reconciliar" id="sinReconciliar" value="1" {{ $sinReconciliar ? 'checked' : '' }}>
                    <label class="form-check-label small" for="sinReconciliar">Sin reconciliar</label>
                </div>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary">Filtrar</button>
                <a href="/casadets/compras" class="btn btn-sm btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Empresa</th>
                    <th>Documento</th>
                    <th>Productos</th>
                    <th>Pago</th>
                    <th class="text-end">Total</th>
                    <th>Venta vinculada</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($compras as $c)
                <tr data-buscar="{{ strtolower($c->empresa . ' ' . $c->documento_numero . ' ' . $c->documento_tipo) }}">
                    <td>{{ $c->fecha->format('d/m/Y') }}</td>
                    <td>
                        {{ $c->empresa }}
                        @if($c->es_supuesto)
                            <span class="badge ms-1" style="background:#fef3c7;color:#92400e;font-size:.66rem;vertical-align:middle;">
                                <i class="bi bi-tag-fill me-1"></i>SUPUESTO
                            </span>
                        @endif
                    </td>
                    <td class="text-muted small">{{ $c->documento_tipo ? ucfirst($c->documento_tipo) : '' }} {{ $c->documento_numero }}</td>
                    <td style="max-width:220px;">
                        @if($c->lineas->count())
                            @php
                                $primera = $c->lineas->first();
                                $resto   = $c->lineas->count() - 1;
                                $titulo  = $c->lineas->map(fn($l) => $l->producto.' × '.rtrim(rtrim(number_format($l->cantidad,2),'0'),'.'))->join("\n");
                            @endphp
                            <span class="small d-block text-truncate" title="{{ $titulo }}" data-bs-toggle="tooltip" data-bs-placement="top" style="white-space:nowrap;">
                                {{ $primera->producto ?? '—' }}
                                <span class="text-muted">× {{ rtrim(rtrim(number_format($primera->cantidad,2),'0'),'.') }}</span>
                            </span>
                            @if($resto > 0)
                                <small class="text-muted">+ {{ $resto }} más</small>
                            @endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($c->metodo_pago === 'efectivo')
                            <span class="badge bg-warning text-dark" style="font-size:.72rem;"><i class="bi bi-cash me-1"></i>Efectivo</span>
                        @elseif($c->metodo_pago === 'transferencia')
                            <span class="badge bg-primary" style="font-size:.72rem;"><i class="bi bi-bank me-1"></i>Transferencia</span>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td class="text-end fw-semibold">S/ {{ number_format($c->monto_total, 2) }}</td>
                    <td style="max-width:180px;">
                        @php
                            $ventasVinculadas = $c->detalles->pluck('venta')->filter()->unique('id')->values();
                            $primeraVenta     = $ventasVinculadas->first();
                            $restoVentas      = $ventasVinculadas->count() - 1;
                            $tituloVentas     = $ventasVinculadas->map(fn($v) => ucfirst($v->documento_tipo ?? '').' '.$v->documento_numero)->join("\n");
                        @endphp
                        @if($primeraVenta)
                            <a href="/casadets/ventas/{{ $primeraVenta->id }}"
                               class="badge bg-light text-dark border text-decoration-none"
                               style="font-size:.75rem; white-space:nowrap;">
                                {{ ucfirst($primeraVenta->documento_tipo ?? '') }} {{ $primeraVenta->documento_numero }}
                            </a>
                            @if($restoVentas > 0)
                                <span class="badge bg-secondary ms-1" style="font-size:.7rem; cursor:default;"
                                      data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $tituloVentas }}">
                                    +{{ $restoVentas }} más
                                </span>
                            @endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($c->es_supuesto)
                            @php $ajuste = $c->ajusteSupuesto; @endphp
                            @if($ajuste && $ajuste->compra_real_id)
                                <span class="badge" style="background:#dcfce7;color:#166534;font-size:.7rem;">
                                    <i class="bi bi-check-circle me-1"></i>Reconciliado
                                </span>
                                @if($ajuste->diferencia_total != 0)
                                    <span class="badge ms-1 {{ $ajuste->diferencia_total > 0 ? 'bg-danger' : 'bg-success' }}" style="font-size:.7rem;">
                                        {{ $ajuste->diferencia_total > 0 ? '+' : '' }}{{ number_format($ajuste->diferencia_total, 2) }}
                                    </span>
                                @endif
                            @else
                                <span class="badge" style="background:#fef9c3;color:#92400e;font-size:.7rem;">
                                    <i class="bi bi-clock me-1"></i>Pendiente
                                </span>
                            @endif
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="/casadets/compras/{{ $c->id }}" class="btn btn-sm btn-outline-secondary">Ver</a>
                        @if($c->es_supuesto && !($c->ajusteSupuesto?->compra_real_id))
                            <a href="/casadets/compras/{{ $c->id }}/reconciliar" class="btn btn-sm btn-warning">
                                <i class="bi bi-arrow-left-right me-1"></i>Reconciliar
                            </a>
                        @else
                            <a href="/casadets/compras/{{ $c->id }}/edit" class="btn btn-sm btn-outline-primary">Editar</a>
                        @endif
                        <form action="/casadets/compras/{{ $c->id }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar compra?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No hay compras registradas.</td></tr>
                @endforelse
            </tbody>
            @if($compras->count())
            <tfoot>
                <tr class="table-light">
                    <th colspan="5" class="text-end">
                        Total del período
                        @if($compras->hasPages()) <span class="fw-normal text-muted" style="font-size:.78rem;">(todas las páginas)</span> @endif
                    </th>
                    <th class="text-end">S/ {{ number_format($totalFiltrado, 2) }}</th>
                    <th colspan="2"></th>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

@if($compras->hasPages())
<div class="d-flex justify-content-center mt-3">
    {{ $compras->links() }}
</div>
@endif

<script>
(function () {
    function norm(s) { return (s||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,''); }
    const inp = document.querySelector('input[name="empresa"]');
    if (!inp) return;
    inp.addEventListener('input', function () {
        const t = norm(this.value.trim());
        document.querySelectorAll('tbody tr[data-buscar]').forEach(tr => {
            tr.style.display = (!t || norm(tr.dataset.buscar).includes(t)) ? '' : 'none';
        });
    });
})();
</script>
@endsection
