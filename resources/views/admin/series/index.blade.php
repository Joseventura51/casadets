@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0"><i class="bi bi-123 me-2"></i>Series de Documentos</h3>
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

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Código</th>
                    <th>Tipo de documento</th>
                    <th>Caja</th>
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
