@extends('layouts.app')

@section('content')
<div class="card">
    <div class="card-header bg-white">
        <h4 class="mb-0">Importar ventas desde Excel</h4>
        <p class="text-muted mb-0 small">Sube el archivo .xlsx exportado de tu sistema de comprobantes.</p>
    </div>
    <div class="card-body">
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="alert alert-info small">
            <strong><i class="bi bi-info-circle"></i> Cómo funciona:</strong>
            <ul class="mb-0 mt-1">
                <li>Solo se leen las columnas: <code>fecha_emisi, Doc, Serie, NroDocumen, Producto, Precio, Cantidad, Total</code> (las demás se ignoran).</li>
                <li>Las filas con el <strong>mismo Doc + Serie + Número</strong> se agrupan en <strong>una sola venta</strong> con varios productos.</li>
                <li><code>B</code> se interpreta como Boleta, <code>F</code> como Factura.</li>
                <li>El <strong>vendedor</strong> y el <strong>método de pago</strong> que selecciones se aplicarán a todas las ventas del archivo.</li>
            </ul>
        </div>

        <form action="/casadets/ventas/import" method="POST" enctype="multipart/form-data" class="row g-3">
            @csrf
            

            <div class="col-md-4">
                <label class="form-label">Archivo Excel (.xlsx, .xls, .csv)</label>
                <input type="file" name="archivo" accept=".xlsx,.xls,.csv" class="form-control" required>
            </div>

            <div class="col-12 d-flex gap-2 border-top pt-3">
                <button class="btn btn-primary">
                    <i class="bi bi-upload"></i> Importar
                </button>
                <a href="/casadets/ventas" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
<script></script>