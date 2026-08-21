@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0"><i class="bi bi-123 me-2"></i>{{ $series->count() }} Series de Documentos</h3>
        <p class="text-muted small mb-0">Correlativos por tipo de documento y caja</p>
    </div>
    <a href="/admin/series/create" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Nueva serie
    </a>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2">
    {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="card mb-3">
    <div class="card-body py-3">
        <form method="GET" action="/admin/series" class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label for="buscar" class="form-label small fw-semibold mb-1">Buscar</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" id="buscar" name="buscar" value="{{ $busqueda }}"
                           class="form-control" placeholder="Código o caja..." autocomplete="off">
                </div>
            </div>
            <div class="col-12 col-sm-4 col-md-2">
                <label for="tipo_documento" class="form-label small fw-semibold mb-1">Tipo</label>
                <select id="tipo_documento" name="tipo_documento" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="boleta" @selected($tipoDocumento === 'boleta')>Boleta</option>
                    <option value="factura" @selected($tipoDocumento === 'factura')>Factura</option>
                    <option value="proforma" @selected($tipoDocumento === 'proforma')>Proforma</option>
                    <option value="nota_credito" @selected($tipoDocumento === 'nota_credito')>Nota de crédito</option>
                    <option value="salida_almacen" @selected($tipoDocumento === 'salida_almacen')>Salida de almacén</option>
                </select>
            </div>
            <div class="col-12 col-sm-4 col-md-2">
                <label for="caja_id" class="form-label small fw-semibold mb-1">Caja</label>
                <select id="caja_id" name="caja_id" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach($cajas as $caja)
                        <option value="{{ $caja->id }}" @selected((string) $cajaId === (string) $caja->id)>
                            {{ $caja->codigo }} — {{ $caja->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-sm-4 col-md-2">
                <label for="estado" class="form-label small fw-semibold mb-1">Estado</label>
                <select id="estado" name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="activa" @selected($estado === 'activa')>Activas</option>
                    <option value="inactiva" @selected($estado === 'inactiva')>Inactivas</option>
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Filtrar
                </button>
                @if($busqueda !== '' || $tipoDocumento || $cajaId || $estado)
                    <a href="/admin/series" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="bi bi-x-lg"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Código</th>
                    <th>Tipo de documento</th>
                    <th>Caja / Almacén</th>
                    <th class="text-end">Correlativo actual</th>
                    <th class="text-center">Activa</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($series as $serie)
                <tr>
                    <td><code class="fw-semibold">{{ $serie->codigo }}</code></td>
                    <td>
                        @php
                            $iconos = [
                                'boleta'       => 'bi-receipt text-success',
                                'factura'      => 'bi-file-earmark-text text-primary',
                                'proforma'     => 'bi-file-earmark-minus text-warning',
                                'nota_credito' => 'bi-arrow-counterclockwise text-danger',
                                'salida_almacen' => 'bi-box-arrow-right text-warning',
                            ];
                        @endphp
                        <i class="bi {{ $iconos[$serie->tipo_documento] ?? 'bi-file' }} me-1"></i>
                        {{ ucfirst(str_replace('_', ' ', $serie->tipo_documento)) }}
                    </td>
                    <td>
                        @if($serie->caja)
                            <span class="badge bg-secondary">{{ $serie->caja->codigo }}</span>
                            <span class="text-muted small ms-1">{{ $serie->caja->nombre }}</span>
                        @else
                            <span class="text-muted small">Sin asignar</span>
                        @endif
                        @if($serie->almacen)
                            <div><span class="badge bg-info text-dark">{{ $serie->almacen->codigo }}</span> <span class="text-muted small">{{ $serie->almacen->nombre }}</span></div>
                        @endif
                    </td>
                    <td class="text-end font-monospace">{{ str_pad($serie->correlativo_actual, 8, '0', STR_PAD_LEFT) }}</td>
                    <td class="text-center">
                        @if($serie->activa)
                            <span class="badge bg-success">Activa</span>
                        @else
                            <span class="badge bg-secondary">Inactiva</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="/admin/series/{{ $serie->id }}/edit"
                           class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size:.75rem;">
                            <i class="bi bi-pencil"></i> Editar
                        </a>
                        <form action="/admin/series/{{ $serie->id }}" method="POST" class="d-inline"
                              onsubmit="return confirm('¿Eliminar serie {{ $serie->codigo }}? Esta acción no se puede deshacer.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-xs btn-outline-danger py-0 px-2" style="font-size:.75rem;">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Sin series registradas. <a href="/admin/series/create">Crear la primera</a></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
