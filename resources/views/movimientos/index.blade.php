@extends("layouts.app")

@section("content")

<div class="container mt-4">

    <h4>Movimientos</h4>

    <a href="/movimientos/create/ingreso" class="btn btn-success">+ Ingreso</a>
    <a href="/movimientos/create/egreso" class="btn btn-danger">+ Egreso</a>

    <table class="table mt-3">
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Descripción</th>
                <th>Monto</th>
                <th>Fecha</th>
            </tr>
        </thead>

        <tbody>
            @foreach($movimientos as $m)
            <tr>
                <td>{{ $m->tipo }}</td>
                <td>{{ $m->descripcion }}</td>
                <td>S/ {{ $m->monto }}</td>
                <td>{{ $m->fecha }}</td>
            </tr>
            @endforeach
        </tbody>

    </table>

</div>

@endsection