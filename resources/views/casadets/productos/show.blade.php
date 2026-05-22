@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">
            {{ $producto->nombre }}
            @if(!$producto->activo)
                <span class="badge bg-secondary ms-2" style="font-size:.7rem;">Inactivo</span>
            @endif
        </h3>
        <p class="text-muted mb-0">
            @if($producto->codigo)
                <span class="me-2"><i class="bi bi-upc me-1"></i>{{ $producto->codigo }}</span>
            @endif
            <span class="badge bg-light text-dark border" style="font-size:.72rem;">{{ strtoupper($producto->empresa ?? 'casadets') }}</span>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="/casadets/productos/{{ $producto->id }}/edit" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-pencil me-1"></i>Editar
        </a>
        <a href="/casadets/productos" class="btn btn-sm btn-outline-secondary">← Productos</a>
    </div>
</div>

{{-- KPI Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card kpi-card {{ $producto->stock_bajo ? 'border-danger' : '' }}">
            <div class="text-muted small">Stock actual</div>
            <h4 class="mb-0 {{ $producto->stock_bajo ? 'text-danger' : 'text-primary' }}">
                {{ number_format($producto->stock_actual, 2) }}
                @if($producto->stock_bajo)
                    <i class="bi bi-exclamation-triangle-fill ms-1" style="font-size:.8rem;"></i>
                @endif
            </h4>
            <small class="text-muted">unidades</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card">
            <div class="text-muted small">Precio venta</div>
            <h4 class="mb-0 text-success">S/ {{ number_format($producto->precio_venta, 2) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card">
            <div class="text-muted small">Precio costo</div>
            <h4 class="mb-0 text-danger">S/ {{ number_format($producto->precio_costo, 2) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card">
            <div class="text-muted small">Margen estimado</div>
            @if($producto->margen !== null)
                <h4 class="mb-0">
                    <span class="badge {{ $producto->margen >= 20 ? 'bg-success' : ($producto->margen >= 0 ? 'bg-warning text-dark' : 'bg-danger') }}" style="font-size:1rem;">
                        {{ number_format($producto->margen, 1) }}%
                    </span>
                </h4>
            @else
                <h4 class="mb-0 text-muted">—</h4>
            @endif
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    {{-- Ajuste de stock --}}
    <div class="col-md-5" id="ajuste">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-sliders me-1"></i> Ajuste de stock
            </div>
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success py-2 small mb-3">{{ session('success') }}</div>
                @endif

                <form action="/casadets/productos/{{ $producto->id }}/ajuste" method="POST" class="row g-2">
                    @csrf

                    <div class="col-12">
                        <label class="form-label small">Tipo de ajuste <span class="text-danger">*</span></label>
                        <select name="tipo" class="form-select form-select-sm" required>
                            <option value="entrada">Entrada (sumar al stock)</option>
                            <option value="salida">Salida (restar del stock)</option>
                            <option value="ajuste_absoluto">Ajuste absoluto (fijar stock exacto)</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label small">Cantidad <span class="text-danger">*</span></label>
                        <input type="number" name="cantidad" class="form-control form-control-sm"
                               step="0.01" min="0.01" required placeholder="ej: 10">
                    </div>

                    <div class="col-12">
                        <label class="form-label small">Observación</label>
                        <input type="text" name="observaciones" class="form-control form-control-sm"
                               placeholder="ej: Conteo físico, merma, etc.">
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-sm btn-primary w-100">
                            <i class="bi bi-check-lg me-1"></i>Aplicar ajuste
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Resumen ventas y compras --}}
    <div class="col-md-7">
        <div class="row g-3 h-100">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-semibold">
                        <i class="bi bi-cart3 me-1 text-primary"></i> Últimas ventas
                        <small class="text-muted fw-normal ms-1">(10 más recientes)</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Documento</th>
                                    <th class="text-end">Cant.</th>
                                    <th class="text-end">Precio</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($ultimasVentas as $dv)
                                <tr>
                                    <td class="small text-muted">{{ optional($dv->venta)->fecha?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="small">
                                        @if($dv->venta)
                                            <a href="/casadets/ventas/{{ $dv->venta->id }}" class="text-decoration-none">
                                                {{ ucfirst($dv->venta->documento_tipo ?? 'Venta') }} {{ $dv->venta->documento_numero ?? '#'.$dv->venta->id }}
                                            </a>
                                        @else —
                                        @endif
                                    </td>
                                    <td class="text-end">{{ number_format($dv->cantidad, 2) }}</td>
                                    <td class="text-end">S/ {{ number_format($dv->precio_unitario, 2) }}</td>
                                    <td>
                                        @if(optional($dv->venta)->estado === 'pagado')
                                            <span class="badge bg-success">Pagado</span>
                                        @elseif(optional($dv->venta)->estado === 'anulado')
                                            <span class="badge bg-danger">Anulado</span>
                                        @else
                                            <span class="badge bg-warning text-dark">Pendiente</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="5" class="text-center text-muted py-3">Sin ventas registradas</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-semibold">
                        <i class="bi bi-bag me-1 text-warning"></i> Últimas compras
                        <small class="text-muted fw-normal ms-1">(10 más recientes)</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Empresa</th>
                                    <th class="text-end">Cant.</th>
                                    <th class="text-end">Costo unit.</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($ultimasCompras as $lc)
                                <tr>
                                    <td class="small text-muted">{{ optional($lc->compra)->fecha?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="small">
                                        @if($lc->compra)
                                            <a href="/casadets/compras/{{ $lc->compra->id }}" class="text-decoration-none">
                                                {{ $lc->compra->empresa }}
                                            </a>
                                        @else —
                                        @endif
                                    </td>
                                    <td class="text-end">{{ number_format($lc->cantidad, 2) }}</td>
                                    <td class="text-end">S/ {{ number_format($lc->monto_unitario ?? 0, 2) }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-center text-muted py-3">Sin compras registradas</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Kardex completo --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold">
            <i class="bi bi-journal-text me-1"></i> Kardex — historial de movimientos
        </span>
        <span class="badge bg-secondary">{{ $kardex->count() }} movimientos</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Referencia</th>
                    <th class="text-end">Cantidad</th>
                    <th class="text-end">Precio unit.</th>
                    <th class="text-end">Saldo</th>
                    <th>Observación</th>
                </tr>
            </thead>
            <tbody>
                @forelse($kardex as $k)
                <tr>
                    <td class="small text-muted">{{ $k->fecha ? \Carbon\Carbon::parse($k->fecha)->format('d/m/Y') : '—' }}</td>
                    <td>
                        @if($k->tipo === 'entrada')
                            <span class="badge bg-success">Entrada</span>
                        @elseif($k->tipo === 'salida')
                            <span class="badge bg-danger">Salida</span>
                        @else
                            <span class="badge bg-secondary">{{ ucfirst($k->tipo) }}</span>
                        @endif
                    </td>
                    <td class="small text-muted">
                        @if($k->referencia_tipo === 'venta')
                            <a href="/casadets/ventas/{{ $k->referencia_id }}" class="text-decoration-none">
                                Venta #{{ $k->referencia_id }}
                            </a>
                        @elseif($k->referencia_tipo === 'compra')
                            <a href="/casadets/compras/{{ $k->referencia_id }}" class="text-decoration-none">
                                Compra #{{ $k->referencia_id }}
                            </a>
                        @elseif($k->referencia_tipo === 'ajuste')
                            <span class="text-muted">Ajuste manual</span>
                        @else
                            {{ $k->referencia_tipo ?? '—' }}
                        @endif
                    </td>
                    <td class="text-end fw-semibold {{ $k->tipo === 'entrada' ? 'text-success' : 'text-danger' }}">
                        {{ $k->tipo === 'entrada' ? '+' : '−' }}{{ number_format($k->cantidad, 2) }}
                    </td>
                    <td class="text-end text-muted">
                        @php $pu = $k->precio_unitario ?? $k->costo_unitario ?? null; @endphp
                        {{ $pu !== null ? 'S/ '.number_format($pu, 2) : '—' }}
                    </td>
                    <td class="text-end fw-bold {{ $k->saldo_acumulado < 0 ? 'text-danger' : '' }}">
                        {{ number_format($k->saldo_acumulado, 2) }}
                    </td>
                    <td class="small text-muted">{{ $k->observaciones ?? '' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        Sin movimientos de stock.
                        <a href="#ajuste">Ingresa el stock inicial →</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
