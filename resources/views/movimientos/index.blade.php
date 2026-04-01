@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Movimientos</h2>
    <a href="/movimientos/create/ingreso" class="btn btn-success">+ Ingreso</a>
    <a href="/movimientos/create/salida" class="btn btn-danger">+ Salida</a>

    <table class="table mt-3">
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Categoria</th>
                <th>Monto</th>
                <th>Fecha</th>
            </tr>
        </thead>
        <tbody>
            @foreach($movimientos as $m)
            <tr>
                <td>{{ $m->tipo }}</td>
                <td>{{ $m->Categoria }}</td>
                <td>S/ {{ $m->monto }}</td>
                <td>{{ $m->fecha }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection