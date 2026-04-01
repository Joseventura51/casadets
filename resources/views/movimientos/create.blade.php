@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Registrar {{ ucfirst($tipo) }}</h2>

    <form action="/movimientos" method="POST">
        @csrf
        <input type="hidden" name="tipo" value="{{ $tipo }}">

        <div class="mb-3">
            <label>categoria</label>
            <input type="text" name="categoria" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Monto (S/)</label>
            <input type="number" name="monto" class="form-control" step="0.01" required>
        </div>

        <div class="mb-3">
            <label>Fecha</label>
            <input type="date" name="fecha" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Observaciones</label>
            <textarea name="observaciones" class="form-control"></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Guardar</button>
        <a href="/movimientos" class="btn btn-secondary">Cancelar</a>
    </form>
</div>
@endsection