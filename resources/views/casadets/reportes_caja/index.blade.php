@extends('layouts.app')

@section('title', 'Reportes de Caja')

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex align-items-center justify-content-between mb-4 gap-2 flex-wrap">
        <div>
            <h4 class="mb-0 fw-bold"><i class="bi bi-file-earmark-spreadsheet me-2 text-primary"></i>Reportes de Caja</h4>
            <small class="text-muted">Historial de reportes Excel generados al cerrar cada caja</small>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Filtros --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" action="/casadets/reportes-caja" class="row g-2 align-items-end">
                @if($cajasDisponibles->count() > 1)
                <div class="col-auto">
                    <label class="form-label small fw-semibold mb-1">Caja</label>
                    <select name="caja_id" class="form-select form-select-sm" style="min-width:160px;" onchange="this.form.submit()">
                        <option value="">Todas</option>
                        @foreach($cajasDisponibles as $c)
                        <option value="{{ $c->id }}" {{ ($cajaSeleccionada?->id == $c->id) ? 'selected' : '' }}>
                            {{ $c->codigo }} — {{ $c->nombre }}
                        </option>
                        @endforeach
                    </select>
                </div>
                @else
                    @if($cajaSeleccionada)
                    <input type="hidden" name="caja_id" value="{{ $cajaSeleccionada->id }}">
                    @endif
                @endif

                <div class="col-auto">
                    <label class="form-label small fw-semibold mb-1">Desde</label>
                    <input type="date" name="desde" class="form-control form-control-sm"
                           value="{{ request('desde') }}" style="width:150px;">
                </div>
                <div class="col-auto">
                    <label class="form-label small fw-semibold mb-1">Hasta</label>
                    <input type="date" name="hasta" class="form-control form-control-sm"
                           value="{{ request('hasta') }}" style="width:150px;">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-search me-1"></i>Filtrar
                    </button>
                    <a href="/casadets/reportes-caja{{ $cajaSeleccionada ? '?caja_id='.$cajaSeleccionada->id : '' }}"
                       class="btn btn-outline-secondary btn-sm ms-1">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if($reportes->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox display-4 d-block mb-3 opacity-25"></i>
                <p class="mb-1 fw-semibold">Sin reportes aún</p>
                <small>Los reportes se generan automáticamente al cerrar una caja.</small>
            </div>
            @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Fecha</th>
                            <th>Caja</th>
                            <th>Sesión ID</th>
                            <th>Apertura (S/)</th>
                            <th>Cierre (S/)</th>
                            <th>Diferencia (S/)</th>
                            <th>Generado</th>
                            <th>Archivo</th>
                            <th class="pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reportes as $r)
                        @php
                            $sesion    = $r->sesion;
                            $apertura  = (float) ($sesion?->monto_apertura ?? 0);
                            $cierre    = $sesion?->monto_cierre !== null ? (float) $sesion->monto_cierre : null;
                        @endphp
                        <tr>
                            <td class="ps-4 fw-semibold">{{ $r->fecha->format('d/m/Y') }}</td>
                            <td>
                                <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold">
                                    {{ $r->caja?->codigo ?? '—' }}
                                </span>
                                <small class="text-muted ms-1">{{ $r->caja?->nombre }}</small>
                            </td>
                            <td><span class="text-muted small">#{{ $r->caja_sesion_id }}</span></td>
                            <td>S/ {{ number_format($apertura, 2) }}</td>
                            <td>
                                @if($cierre !== null)
                                    S/ {{ number_format($cierre, 2) }}
                                @else
                                    <span class="text-muted small">N/D</span>
                                @endif
                            </td>
                            <td>
                                @if($cierre !== null)
                                    @php $dif = round($cierre - $apertura, 2); @endphp
                                    <span class="fw-semibold {{ $dif < 0 ? 'text-danger' : ($dif > 0 ? 'text-success' : 'text-muted') }}">
                                        {{ $dif >= 0 ? '+' : '' }}{{ number_format($dif, 2) }}
                                    </span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td>
                                <small class="text-muted">
                                    {{ $r->generado_at ? $r->generado_at->format('d/m/Y H:i') : '—' }}
                                </small>
                            </td>
                            <td>
                                @if($r->existeArchivo())
                                    <span class="badge bg-success bg-opacity-10 text-success">
                                        <i class="bi bi-check-circle me-1"></i>Disponible
                                    </span>
                                @else
                                    <span class="badge bg-warning bg-opacity-10 text-warning">
                                        <i class="bi bi-exclamation-triangle me-1"></i>No encontrado
                                    </span>
                                @endif
                            </td>
                            <td class="pe-4">
                                <div class="d-flex gap-1">
                                    @if($r->existeArchivo())
                                    <a href="/casadets/reportes-caja/{{ $r->id }}/descargar"
                                       class="btn btn-sm btn-success" title="Descargar Excel">
                                        <i class="bi bi-download me-1"></i>Excel
                                    </a>
                                    @endif

                                    @can('rol:reportes-caja')
                                    @endcan
                                    <form method="POST" action="/casadets/reportes-caja/{{ $r->id }}/regenerar"
                                          onsubmit="return confirm('¿Regenerar el reporte de esta sesión?')">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Regenerar">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($reportes->hasPages())
            <div class="d-flex justify-content-center py-3 border-top">
                {{ $reportes->links('pagination::bootstrap-5') }}
            </div>
            @endif
            @endif
        </div>
    </div>

</div>
@endsection
