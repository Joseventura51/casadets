@extends("layouts.app")

@section("content")

<div class="container mt-4">

    <h4>Registrar {{ ucfirst($tipo) }}</h4>

    <form action="/movimientos" method="POST">
        @csrf

        <input type="hidden" name="tipo" value="{{ $tipo }}">

        <div class="mb-3">
            <label>Descripción</label>
            <input type="text" name="descripcion" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Monto</label>
            <input type="number" step="0.01" name="monto" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Fecha</label>
            <input type="date" name="fecha" class="form-control" required>
        </div>

        <button class="btn btn-primary">Guardar</button>

    </form>

</div>

@endsection