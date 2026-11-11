@extends('layouts.app')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h3 class="mb-1">Despachar venta</h3>
        <div class="text-muted">{{ $venta->documento_numero ?: 'Venta #' . $venta->id }}</div>
    </div>
    <a href="/casadets/despachos" class="btn btn-outline-secondary">Volver al listado</a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">
        <strong>No se pudo registrar el despacho.</strong>
        <ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card card-body h-100"><small class="text-muted">Cliente</small><strong>{{ $venta->cliente?->nombre ?? 'Cliente varios' }}</strong></div></div>
    <div class="col-md-2"><div class="card card-body h-100"><small class="text-muted">Vendedor</small><strong>{{ $venta->vendedor?->nombre ?? '—' }}</strong></div></div>
    <div class="col-md-2"><div class="card card-body h-100"><small class="text-muted">Fecha de venta</small><strong>{{ $venta->fecha?->format('d/m/Y') ?? '—' }}</strong></div></div>
    <div class="col-md-2"><div class="card card-body h-100"><small class="text-muted">Pago</small><strong>{{ ucfirst($resumen['estado_pago']) }}</strong></div></div>
    <div class="col-md-3"><div class="card card-body h-100"><small class="text-muted">Total</small><strong>S/ {{ number_format((float) $resumen['ingreso_neto'], 2) }}</strong></div></div>
</div>

@if((float) $venta->ajuste !== 0.0)
    <div class="alert alert-warning">
        La venta tiene un ajuste general. Se puede registrar el despacho, pero la comisión no se liberará automáticamente hasta que el ajuste pueda atribuirse a sus productos.
    </div>
@endif

<div class="card mb-4">
    <div class="card-header fw-semibold">Cantidades por entregar</div>
    <form method="POST" action="/casadets/despachos/{{ $venta->id }}">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Producto</th>
                        <th class="text-end">Vendido</th>
                        <th class="text-end">Devuelto</th>
                        <th class="text-end">Despachado</th>
                        <th class="text-end">Pendiente</th>
                        <th style="width:150px">Entrega actual</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lineas as $linea)
                        @php($detalle = $linea['detalle'])
                        <tr>
                            <td>
                                <strong>{{ $detalle->producto }}</strong>
                                @if($detalle->codigo)<div class="small text-muted">{{ $detalle->codigo }}</div>@endif
                            </td>
                            <td class="text-end">{{ $linea['cantidad_vendida'] }}</td>
                            <td class="text-end">{{ $linea['cantidad_devuelta'] }}</td>
                            <td class="text-end">{{ $linea['cantidad_despachada'] }}</td>
                            <td class="text-end fw-semibold">{{ $linea['cantidad_pendiente'] }}</td>
                            <td>
                                <input class="form-control form-control-sm text-end"
                                    type="number" step="0.01" min="0" max="{{ $linea['cantidad_pendiente'] }}"
                                    name="cantidades[{{ $detalle->id }}]" value="0">
                            </td>
                        </tr>
                        @if(count($linea['asignaciones_costo']))
                            <tr class="table-light">
                                <td colspan="6">
                                    <div class="small fw-semibold mb-2">Asignaciones históricas de costo</div>
                                    <div class="row g-2">
                                        @foreach($linea['asignaciones_costo'] as $asignacion)
                                            <div class="col-lg-6">
                                                <label class="form-label small mb-1">
                                                    Compra #{{ $asignacion['id'] }} —
                                                    {{ $asignacion['costo_unitario'] === null ? 'costo no asignado' : 'S/ ' . $asignacion['costo_unitario'] }}
                                                    — disponible {{ $asignacion['cantidad_disponible'] }}
                                                </label>
                                                <input class="form-control form-control-sm" type="number" step="0.01" min="0"
                                                    max="{{ $asignacion['cantidad_disponible'] }}"
                                                    name="costos[{{ $detalle->id }}][{{ $asignacion['id'] }}]" value="0">
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="small text-muted mt-2">Indica cuántas unidades entregadas corresponden a cada costo. Lo no asignado quedará pendiente de comisión.</div>
                                </td>
                            </tr>
                        @else
                            <tr class="table-light">
                                <td colspan="6" class="small text-muted">
                                    Sin asignación histórica de costo. El despacho se podrá registrar, pero todavía no habilita comisión.
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body border-top">
            <label class="form-label">Observaciones</label>
            <textarea class="form-control mb-3" name="observaciones" rows="2" maxlength="2000" placeholder="Opcional"></textarea>
            @if(auth()->user()->puedeHacer('despachos.registrar'))
                <button class="btn btn-primary" type="submit"><i class="bi bi-truck me-1"></i>Registrar despacho</button>
            @endif
        </div>
    </form>
</div>

<div class="card mb-4">
    <div class="card-header fw-semibold">Historial de despachos</div>
    <div class="card-body">
        @forelse($historial as $despacho)
            <div class="border rounded p-3 mb-2">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <a href="/casadets/despachos/historial/{{ $despacho->id }}" class="fw-semibold">
                        Despacho #{{ $despacho->id }} — {{ $despacho->fecha_hora->format('d/m/Y H:i') }}
                    </a>
                    <span class="text-muted small">Responsable: {{ $despacho->user?->name ?? 'Usuario eliminado' }}</span>
                </div>
                <ul class="mb-1 mt-2">
                    @foreach($despacho->detalles as $detalle)
                        <li>{{ $detalle->ventaDetalle?->producto ?? 'Producto' }}: {{ number_format((float) $detalle->cantidad, 2) }}</li>
                    @endforeach
                </ul>
                @if($despacho->observaciones)<div class="small text-muted">{{ $despacho->observaciones }}</div>@endif
            </div>
        @empty
            <p class="text-muted mb-0">Todavía no hay entregas registradas.</p>
        @endforelse
    </div>
</div>
@endsection
