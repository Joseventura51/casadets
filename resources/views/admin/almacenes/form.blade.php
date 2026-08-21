@extends('layouts.app')

@section('content')
<div class="mb-3"><a href="/admin/almacenes" class="small text-decoration-none">← Almacenes</a><h3 class="mt-1">{{ $almacen->exists ? 'Editar almacén' : 'Nuevo almacén' }}</h3></div>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ $almacen->exists ? '/admin/almacenes/'.$almacen->id : '/admin/almacenes' }}">
    @csrf
    @if($almacen->exists) @method('PUT') @endif
    <div class="card"><div class="card-body"><div class="row g-3">
        <div class="col-12 col-md-3"><label class="form-label">Código</label><input name="codigo" value="{{ old('codigo', $almacen->codigo) }}" class="form-control" required></div>
        <div class="col-12 col-md-5"><label class="form-label">Nombre</label><input name="nombre" value="{{ old('nombre', $almacen->nombre) }}" class="form-control" required></div>
        <div class="col-12 col-md-4"><label class="form-label">Empresa</label><input name="empresa" value="{{ old('empresa', $almacen->empresa ?: 'casadets') }}" class="form-control" required></div>
        <div class="col-12"><label class="form-label">Observaciones</label><textarea name="observaciones" class="form-control" rows="3">{{ old('observaciones', $almacen->observaciones) }}</textarea></div>
    </div></div></div>
    <div class="mt-3 d-flex gap-2"><button class="btn btn-primary">Guardar</button><a href="/admin/almacenes" class="btn btn-outline-secondary">Cancelar</a></div>
</form>
@endsection