@extends('layouts.app')

@section('content')
<div class="container-fluid px-3 py-3">

    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h4 class="mb-0 fw-bold"><i class="bi bi-arrow-return-left me-2 text-danger"></i>Devoluciones y Anulaciones</h4>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show py-2"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show py-2"><i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="row g-3">

        {{-- Columna izquierda: buscar vale --}}
        <div class="col-md-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-search me-1"></i>Buscar Vale
                </div>
                <div class="card-body">
                    <form method="GET" action="/casadets/devoluciones">
                        <div class="input-group">
                            <input type="text"
                                   name="q"
                                   class="form-control"
                                   placeholder="N° documento, nombre o DNI del cliente"
                                   value="{{ request('q') }}"
                                   autofocus>
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </form>

                    @if(request()->filled('q'))
                        <div class="mt-3">
                            @if($ventas->isEmpty())
                                <p class="text-muted small text-center py-3">No se encontraron vales para "{{ request('q') }}".</p>
                            @else
                                <div class="list-group list-group-flush">
                                    @foreach($ventas as $v)
                                    <a href="/casadets/devoluciones/{{ $v->id }}"
                                       class="list-group-item list-group-item-action py-2 px-2">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="fw-semibold small">
                                                    {{ ucfirst($v->documento_tipo ?? 'Vale') }} {{ $v->documento_numero ?? '#'.$v->id }}
                                                    <span class="badge ms-1
                                                        {{ $v->estado === 'pagado' ? 'bg-success' : ($v->estado === 'parcial' ? 'bg-warning text-dark' : 'bg-secondary') }}"
                                                        style="font-size:.6rem;">
                                                        {{ ucfirst($v->estado) }}
                                                    </span>
                                                </div>
                                                <div class="text-muted" style="font-size:.75rem;">
                                                    {{ $v->cliente->nombre ?? 'Sin cliente' }}
                                                    &nbsp;·&nbsp;{{ $v->fecha->format('d/m/Y') }}
                                                </div>
                                                @if($v->detalles->count())
                                                <div class="text-muted" style="font-size:.7rem;">
                                                    {{ $v->detalles->first()->producto }}
                                                    @if($v->detalles->count() > 1) <span class="badge bg-light text-dark">+{{ $v->detalles->count() - 1 }}</span> @endif
                                                </div>
                                                @endif
                                            </div>
                                            <div class="text-end flex-shrink-0 ms-2">
                                                <div class="fw-bold text-primary small">S/ {{ number_format($v->total_a_cobrar, 2) }}</div>
                                                <i class="bi bi-chevron-right text-muted small"></i>
                                            </div>
                                        </div>
                                    </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Columna derecha: historial --}}
        <div class="col-md-7">

            {{-- Devoluciones recientes --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                    <span><i class="bi bi-clock-history me-1"></i>Devoluciones recientes</span>
                    @if($recientes->count() > 0)
                        <span class="badge bg-secondary">{{ $recientes->count() }}</span>
                    @endif
                </div>
                <div class="card-body p-0">
                    @if($recientes->isEmpty())
                        <p class="text-muted small text-center py-3 mb-0">Sin devoluciones registradas.</p>
                    @else
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0" style="font-size:.82rem;">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Vale</th>
                                    <th>Cliente</th>
                                    <th>Tipo</th>
                                    <th class="text-end">Devuelto</th>
                                    <th class="text-end">Saldo generado</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recientes as $dev)
                                <tr>
                                    <td class="text-muted">{{ $dev->fecha->format('d/m/Y') }}</td>
                                    <td>
                                        {{ ucfirst($dev->venta->documento_tipo ?? 'Vale') }}
                                        {{ $dev->venta->documento_numero ?? '#'.$dev->venta_id }}
                                    </td>
                                    <td>{{ $dev->venta->cliente->nombre ?? '—' }}</td>
                                    <td>
                                        @if($dev->tipo === 'total')
                                            <span class="badge bg-danger">Anulación</span>
                                        @else
                                            <span class="badge bg-warning text-dark">Parcial</span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-semibold text-danger">S/ {{ number_format($dev->monto_devuelto, 2) }}</td>
                                    <td class="text-end text-success">
                                        @if((float)$dev->saldo_generado > 0)
                                            S/ {{ number_format($dev->saldo_generado, 2) }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="/casadets/devoluciones/{{ $dev->venta_id }}" class="btn btn-outline-secondary btn-sm py-0">Ver</a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Vales anulados recientes --}}
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                    <span><i class="bi bi-x-circle me-1 text-danger"></i>Vales anulados recientes</span>
                    @if($anuladas->count() > 0)
                        <span class="badge bg-danger">{{ $anuladas->count() }}</span>
                    @endif
                </div>
                <div class="card-body p-0">
                    @if($anuladas->isEmpty())
                        <p class="text-muted small text-center py-3 mb-0">Sin vales anulados.</p>
                    @else
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0" style="font-size:.82rem;">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Documento</th>
                                    <th>Cliente</th>
                                    <th class="text-end">Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($anuladas as $v)
                                <tr>
                                    <td class="text-muted">{{ $v->fecha->format('d/m/Y') }}</td>
                                    <td>
                                        <span class="text-decoration-line-through text-muted">
                                            {{ ucfirst($v->documento_tipo ?? 'Vale') }} {{ $v->documento_numero ?? '#'.$v->id }}
                                        </span>
                                        <span class="badge bg-danger ms-1" style="font-size:.6rem;">Anulado</span>
                                    </td>
                                    <td>{{ $v->cliente->nombre ?? '—' }}</td>
                                    <td class="text-end text-muted text-decoration-line-through">S/ {{ number_format($v->total, 2) }}</td>
                                    <td>
                                        <a href="/casadets/ventas/{{ $v->id }}" class="btn btn-outline-secondary btn-sm py-0">Ver</a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
