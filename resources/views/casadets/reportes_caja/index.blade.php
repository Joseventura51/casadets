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
                            <th class="text-end">Ventas cobradas</th>
                            <th class="text-end">Otros ingresos</th>
                            <th class="text-end">Salidas</th>
                            <th class="text-end">Balance</th>
                            <th class="text-end">Ef. esperado</th>
                            <th class="text-end">Ef. declarado</th>
                            <th class="text-end">Diferencia ef.</th>
                            <th>Archivo</th>
                            <th class="pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reportes as $r)
                        @php
                            $sesion           = $r->sesion;
                            $cierreDeclarado  = $sesion?->monto_cierre !== null ? (float) $sesion->monto_cierre : null;
                            $efectivoEsperado = $r->efectivo_esperado !== null ? (float) $r->efectivo_esperado : null;
                            $difEfectivo      = ($cierreDeclarado !== null && $efectivoEsperado !== null)
                                                ? round($cierreDeclarado - $efectivoEsperado, 2)
                                                : null;
                        @endphp
                        <tr>
                            <td class="ps-4 fw-semibold">
                                {{ $r->fecha->format('d/m/Y') }}
                                <br><small class="text-muted fw-normal">Sesión #{{ $r->caja_sesion_id }}</small>
                            </td>
                            <td>
                                <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold">
                                    {{ $r->caja?->codigo ?? '—' }}
                                </span>
                                <small class="text-muted d-block">{{ $r->caja?->nombre }}</small>
                            </td>
                            <td class="text-end">
                                @if($r->total_cobradas !== null)
                                    <span class="text-success fw-semibold">S/ {{ number_format($r->total_cobradas, 2) }}</span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($r->total_otros !== null)
                                    S/ {{ number_format($r->total_otros, 2) }}
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($r->total_salidas !== null)
                                    <span class="text-danger">S/ {{ number_format($r->total_salidas, 2) }}</span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($r->balance !== null)
                                    <span class="fw-bold {{ $r->balance >= 0 ? 'text-success' : 'text-danger' }}">
                                        S/ {{ number_format($r->balance, 2) }}
                                    </span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($efectivoEsperado !== null)
                                    S/ {{ number_format($efectivoEsperado, 2) }}
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($cierreDeclarado !== null)
                                    S/ {{ number_format($cierreDeclarado, 2) }}
                                @else
                                    <span class="text-muted small">N/D</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($difEfectivo !== null)
                                    <span class="fw-semibold {{ $difEfectivo < 0 ? 'text-danger' : ($difEfectivo > 0 ? 'text-success' : 'text-muted') }}">
                                        {{ $difEfectivo >= 0 ? '+' : '' }}S/ {{ number_format($difEfectivo, 2) }}
                                    </span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
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
                                <small class="text-muted d-block" style="font-size:.7rem;">
                                    {{ $r->generado_at ? $r->generado_at->format('d/m/Y H:i') : '' }}
                                </small>
                            </td>
                            <td class="pe-4">
                                <div class="d-flex gap-1 align-items-center">
                                    @if($r->existeArchivo())
                                    <a href="/casadets/reportes-caja/{{ $r->id }}/descargar"
                                       class="btn btn-sm btn-success" title="Descargar Excel">
                                        <i class="bi bi-download me-1"></i>Excel
                                    </a>
                                    @endif

                                    @if($r->cerrado)
                                        <span class="badge bg-secondary bg-opacity-15 text-secondary border border-secondary border-opacity-25 px-2 py-1"
                                              title="Cerrado el {{ $r->cerrado_at?->format('d/m/Y H:i') }}">
                                            <i class="bi bi-lock-fill me-1"></i>Cerrado
                                        </span>
                                    @else
                                        <form method="POST" action="/casadets/reportes-caja/{{ $r->id }}/regenerar"
                                              onsubmit="return confirm('¿Regenerar el reporte de esta sesión?')">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Regenerar reporte">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="/casadets/reportes-caja/{{ $r->id }}/cerrar"
                                              onsubmit="return confirm('¿Cerrar este reporte? Ya no podrá regenerarse.')">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Cerrar reporte">
                                                <i class="bi bi-lock"></i>
                                            </button>
                                        </form>
                                    @endif
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
