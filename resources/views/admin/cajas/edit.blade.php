@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="bi bi-cash-register me-2"></i>Editar Caja — {{ $caja->codigo }}</h3>
    <a href="/admin/cajas" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                @if($errors->any())
                <div class="alert alert-danger py-2 mb-3">
                    <ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
                @endif

                <form action="/admin/cajas/{{ $caja->id }}" method="POST">
                    @csrf @method('PUT')
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Código</label>
                        <input type="text" name="codigo" value="{{ old('codigo', $caja->codigo) }}"
                               class="form-control" required style="text-transform:uppercase;" maxlength="20">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre</label>
                        <input type="text" name="nombre" value="{{ old('nombre', $caja->nombre) }}"
                               class="form-control" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Empresa</label>
                        <select name="empresa" class="form-select" required>
                            <option value="casadets" {{ $caja->empresa === 'casadets' ? 'selected' : '' }}>CASADETS</option>
                            <option value="zendy"    {{ $caja->empresa === 'zendy'    ? 'selected' : '' }}>ZENDY</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="activa" id="activa" value="1"
                                   {{ $caja->activa ? 'checked' : '' }}>
                            <label class="form-check-label" for="activa">Activa</label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Guardar cambios
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Series asignadas</span>
                <a href="/admin/series/create?caja_id={{ $caja->id }}" class="btn btn-sm btn-outline-primary">+ Nueva serie</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Serie</th><th>Tipo</th><th class="text-end">Correlativo</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse($caja->series as $s)
                        <tr>
                            <td><code>{{ $s->codigo }}</code></td>
                            <td class="small text-muted">{{ ucfirst(str_replace('_',' ',$s->tipo_documento)) }}</td>
                            <td class="text-end small">{{ str_pad($s->correlativo_actual, 8, '0', STR_PAD_LEFT) }}</td>
                            <td><a href="/admin/series/{{ $s->id }}/edit" class="btn btn-xs btn-outline-secondary py-0 px-1" style="font-size:.72rem;">Editar</a></td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-3 small">Sin series. <a href="/admin/series/create?caja_id={{ $caja->id }}">Agregar una</a></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
