@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Editar cliente</h3>
    <a href="/casadets/clientes" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<form action="/casadets/clientes/{{ $cliente->id }}" method="POST">
    @csrf @method('PUT')
    @include('casadets.clientes._form')
</form>
@endsection
