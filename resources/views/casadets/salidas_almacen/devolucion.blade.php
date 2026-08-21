@extends('layouts.app')

@section('content')
<div class="mb-3">
    <a href="/casadets/salidas-almacen/{{ $salida->id }}" class="small text-decoration-none">← {{ $salida->documento }}</a>
    <h3 class="mt-1 mb-0">Registrar devolución</h3>
    <p class="text-muted small mb-0">{{ $salida->origen->nombre }} ← {{ $salida->destino->nombre }}</p>
</div>
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<form method="POST" action="/casadets/salidas-almacen/{{ $salida->id }}/devolucion">
    @csrf
    <div class="card mb-3"><div class="card-body"><div class="row g-3">
        <div class="col-12 col-md-3"><label class="form-label">Documento de devolución</label><input name="documento" class="form-control" value="{{ old('documento', 'DEV-'.$salida->documento) }}" required></div>
        <div class="col-12 col-md-2"><label class="form-label">Fecha</label><input type="date" name="fecha" class="form-control" value="{{ old('fecha', today()->toDateString()) }}" required></div>
        <div class="col-12 col-md-7"><label class="form-label">Motivo</label><input name="motivo" class="form-control" value="{{ old('motivo') }}" placeholder="Opcional"></div>
    </div></div></div>
    <div class="card mb-3"><div class="card-header">Cantidades disponibles para devolver</div>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead class="table-light"><tr><th>Producto</th><th class="text-end">Salió</th><th class="text-end">Ya devuelto</th><th class="text-end">Disponible</th><th style="width:160px;">Devolver</th></tr></thead>
            <tbody>
            @foreach($salida->detalles as $detalle)
                @php $ya = (float) ($devueltos[$detalle->id] ?? 0); $disponible = max(0, (float)$detalle->cantidad - $ya); @endphp
                <tr>
                    <td>{{ $detalle->descripcion_original ?? $detalle->producto->nombre }}</td>
                    <td class="text-end">{{ number_format($detalle->cantidad, 2) }}</td>
                    <td class="text-end text-muted">{{ number_format($ya, 2) }}</td>
                    <td class="text-end fw-semibold">{{ number_format($disponible, 2) }}</td>
                    <td><input type="number" name="cantidades[{{ $detalle->id }}]" class="form-control form-control-sm" step="0.01" min="0" max="{{ $disponible }}" value="0" @disabled($disponible <= 0)></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </div>
    <div class="d-flex flex-column flex-sm-row gap-2"><button class="btn btn-warning"><i class="bi bi-check-lg me-1"></i>Registrar devolución</button><a href="/casadets/salidas-almacen/{{ $salida->id }}" class="btn btn-outline-secondary">Cancelar</a></div>
</form>
@endsection