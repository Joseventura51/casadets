@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">Compras</h3>
        <p class="text-muted mb-0">Compras a proveedores. Pueden vincularse a ventas.</p>
    </div>
    <a href="/casadets/compras/create" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Nueva compra
    </a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1">Empresa / Proveedor</label>
                <input type="text" name="empresa" value="{{ request('empresa') }}" class="form-control form-control-sm" placeholder="Buscar...">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Desde</label>
                <input type="date" name="desde" value="{{ request('desde') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Hasta</label>
                <input type="date" name="hasta" value="{{ request('hasta') }}" class="form-control form-control-sm">
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
                    <th class="text-end">Total</th>
                    <th>Venta vinculada</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($compras as $c)
                <tr>
                    <td>{{ $c->fecha->format('d/m/Y') }}</td>
                    <td>{{ $c->empresa }}</td>
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
                    <td class="text-end">
                        <a href="/casadets/compras/{{ $c->id }}" class="btn btn-sm btn-outline-secondary">Ver</a>
                        <a href="/casadets/compras/{{ $c->id }}/edit" class="btn btn-sm btn-outline-primary">Editar</a>
                        <form action="/casadets/compras/{{ $c->id }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar compra?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No hay compras registradas.</td></tr>
                @endforelse
            </tbody>
            @if($compras->count())
            <tfoot>
                <tr class="table-light">
                    <th colspan="4" class="text-end">Total (esta página)</th>
                    <th class="text-end">S/ {{ number_format($compras->getCollection()->sum('monto_total'), 2) }}</th>
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
@endsection
