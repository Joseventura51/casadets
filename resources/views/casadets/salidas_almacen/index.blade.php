@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-start align-items-md-center flex-wrap gap-2 mb-3">
    <div>
        <h3 class="mb-0"><i class="bi bi-box-arrow-right me-2 text-primary"></i>Salidas de almacén</h3>
        <p class="text-muted mb-0 small">Registro de salidas entre almacenes ya realizadas.</p>
    </div>
    @if(auth()->user()->puedeHacer('salidas-almacen.crear'))
        <a href="/casadets/salidas-almacen/importar" class="btn btn-outline-primary">
            <i class="bi bi-file-earmark-excel me-1"></i>Importar Excel
        </a>
        <a href="/casadets/salidas-almacen/create" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Nueva salida
        </a>
    @endif
</div>

<form method="GET" class="card p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
            <label class="form-label small mb-1">Documento</label>
            <input type="search" name="documento" value="{{ request('documento') }}" class="form-control form-control-sm" placeholder="STR-0007-306">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Desde</label>
            <input type="date" name="desde" value="{{ request('desde') }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Hasta</label>
            <input type="date" name="hasta" value="{{ request('hasta') }}" class="form-control form-control-sm">
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label small mb-1">Origen</label>
            <select name="origen_id" class="form-select form-select-sm">
                <option value="">Todos</option>
                @foreach($almacenes as $almacen)
                    <option value="{{ $almacen->id }}" @selected(request('origen_id') == $almacen->id)>{{ $almacen->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label small mb-1">Estado</label>
            <select name="estado" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="realizada" @selected(request('estado') === 'realizada')>Realizada</option>
                <option value="parcialmente_devuelta" @selected(request('estado') === 'parcialmente_devuelta')>Parcialmente devuelta</option>
                <option value="devuelta_completamente" @selected(request('estado') === 'devuelta_completamente')>Devuelta completamente</option>
                <option value="anulada" @selected(request('estado') === 'anulada')>Anulada</option>
            </select>
        </div>
        <div class="col-12 col-md-1 d-flex gap-2">
            <button class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-search"></i></button>
            <a href="/casadets/salidas-almacen" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
        </div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Documento</th>
                    <th>Fecha</th>
                    <th>Origen</th>
                    <th>Destino</th>
                    <th class="text-center">Líneas</th>
                    <th>Estado</th>
                    <th>Usuario</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($salidas as $salida)
                    @php
                        $estadoLabels = [
                            'realizada' => ['Realizada', 'success'],
                            'parcialmente_devuelta' => ['Parcialmente devuelta', 'warning'],
                            'devuelta_completamente' => ['Devuelta completamente', 'info'],
                            'anulada' => ['Anulada', 'secondary'],
                        ];
                        [$estadoLabel, $estadoColor] = $estadoLabels[$salida->estado] ?? [ucfirst($salida->estado), 'secondary'];
                    @endphp
                    <tr class="{{ $salida->estado === 'anulada' ? 'text-muted' : '' }}">
                        <td class="fw-semibold">{{ $salida->documento }}</td>
                        <td>{{ $salida->fecha?->format('d/m/Y') }}</td>
                        <td>{{ $salida->origen->nombre }}</td>
                        <td>{{ $salida->destino->nombre }}</td>
                        <td class="text-center">{{ $salida->detalles_count ?? $salida->detalles()->count() }}</td>
                        <td><span class="badge bg-{{ $estadoColor }}">{{ $estadoLabel }}</span></td>
                        <td class="small">{{ $salida->usuario?->name ?? '—' }}</td>
                        <td class="text-end">
                            <a href="/casadets/salidas-almacen/{{ $salida->id }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye me-1"></i>Ver
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No hay salidas registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $salidas->links('pagination::bootstrap-5') }}</div>
@endsection