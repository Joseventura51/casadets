@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h3 class="mb-1">Importar cuentas por pagar</h3><p class="text-muted mb-0">Carga el reporte de comprobantes recibidos de SUNAT/OSE.</p></div>
    <a href="/zendy/letras" class="btn btn-outline-secondary">Volver</a>
</div>
<div class="card shadow-sm">
    <div class="card-body">
        <div class="alert alert-info"><i class="bi bi-info-circle me-1"></i>Las F001 se guardarán como facturas origen. El archivo no contiene pagos reales, por lo que las facturas a crédito ingresarán con saldo pendiente.</div>
        <form action="/zendy/letras/importar" method="POST" enctype="multipart/form-data">
            @csrf
            <label class="form-label fw-semibold">Archivo Excel</label>
            <input type="file" name="archivo" class="form-control" accept=".xlsx,.xls,.csv" required>
            <div class="form-text">Se aceptan las columnas Razón Social, RUC, N° Comprobante, F. Emisión, F. Vencimiento, Moneda, Total y Tipo de Pago.</div>
            <button class="btn btn-primary mt-4"><i class="bi bi-cloud-upload me-1"></i>Procesar e importar</button>
        </form>
    </div>
</div>
@endsection