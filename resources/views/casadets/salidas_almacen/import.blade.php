@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Importar salidas desde Excel</h3>
        <p class="text-muted mb-0 small">Carga el reporte de transferencias valorizadas para registrar las salidas y descontar stock.</p>
    </div>
    <a href="/casadets/salidas-almacen" class="btn btn-outline-secondary btn-sm">Volver</a>
</div>

@if(session('error'))
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-header fw-semibold">Seleccionar archivo</div>
            <div class="card-body">
                <form action="/casadets/salidas-almacen/importar" method="POST" enctype="multipart/form-data">
                    @csrf
                    <label class="form-label">Archivo Excel (.xlsx o .xls)</label>
                    <input type="file" name="archivo" class="form-control" accept=".xlsx,.xls" required>
                    <div class="form-text mb-3">Se agrupan las filas por documento. Los documentos que ya existen se omiten para evitar duplicados.</div>
                    <button class="btn btn-success"><i class="bi bi-upload me-1"></i>Importar transferencias</button>
                    <a href="/casadets/salidas-almacen" class="btn btn-outline-secondary ms-1">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-5">
        <div class="card border-primary">
            <div class="card-header text-primary fw-semibold">Formato esperado</div>
            <div class="card-body small">
                <p class="mb-2">La primera hoja debe tener estos encabezados, en este orden:</p>
                <code class="d-block bg-light p-2 rounded">fecha, documento, emisor, estado, codigo_producto, descripcion_producto, medida, cantidad, precio, monto, lote, receptor</code>
                <hr>
                <ul class="mb-0 ps-3">
                    <li>El emisor y receptor deben coincidir con el código o nombre de un almacén activo.</li>
                    <li>El código de producto debe existir y estar activo.</li>
                    <li>Se valida el stock del almacén origen antes de confirmar.</li>
                    <li>Si una fila falla, no se registra ninguna salida del archivo.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection