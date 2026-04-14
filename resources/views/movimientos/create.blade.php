@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
            <h2 class="h4 mb-0">Registrar {{ ucfirst($tipo) }}</h2>
            <p class="text-muted mb-0">El movimiento debe estar vinculado a factura o proforma.</p>
        </div>
        <div class="card-body">
            <form action="/movimientos" method="POST" class="row g-3">
                @csrf
                <input type="hidden" name="tipo" value="{{ $tipo }}">

                <div class="col-md-6">
                    <label class="form-label">Categoría</label>
                    <input type="text" name="categoria" class="form-control" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Documento</label>
                    <select name="documento_tipo" class="form-select" required>
                        <option value="">Seleccionar</option>
                        <option value="factura">Factura</option>
                        <option value="proforma">Proforma</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Número de documento</label>
                    <input type="text" name="documento_numero" class="form-control" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Monto (S/)</label>
                    <input type="number" name="monto" class="form-control" step="0.01" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Fecha</label>
                    <input type="date" name="fecha" class="form-control" required>
                </div>

                <div class="col-12">
                    <label class="form-label">Observaciones</label>
                    <textarea name="observaciones" class="form-control" rows="3"></textarea>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <a href="/movimientos" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection