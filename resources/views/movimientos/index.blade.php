@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h3 mb-1">Movimientos</h2>
            <p class="text-muted mb-0">Ingresos y salidas asociados a factura o proforma.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="/movimientos/create/ingreso" class="btn btn-success">+ Ingreso</a>
            <a href="/movimientos/create/salida" class="btn btn-danger">+ Salida</a>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Tipo</th>
                        <th>Categoría</th>
                        <th>Documento</th>
                        <th>Número</th>
                        <th>Monto</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($movimientos as $m)
                    <tr>
                        <td>{{ ucfirst($m->tipo) }}</td>
                        <td>{{ $m->categoria }}</td>
                        <td>{{ ucfirst($m->documento_tipo) }}</td>
                        <td>{{ $m->documento_numero }}</td>
                        <td>S/ {{ number_format($m->monto, 2) }}</td>
                        <td>{{ $m->fecha }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No hay movimientos registrados.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection