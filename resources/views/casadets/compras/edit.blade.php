@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Editar compra #{{ $compra->id }}</h3>
    <a href="/casadets/compras" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<form action="/casadets/compras/{{ $compra->id }}" method="POST">
    @csrf @method('PUT')
    @include('casadets.compras._form')
</form>
@endsection
