@extends('layouts.app')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0"><i class="bi bi-file-earmark-spreadsheet text-success me-2"></i>Importar desde Excel</h3>
        <p class="text-muted mb-0 small">Sube el archivo .xlsx exportado de tu sistema de comprobantes.</p>
    </div>
    <a href="/casadets/ventas" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

@if($errors->any())
    <div class="alert alert-danger mb-3">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<form action="/casadets/ventas/import" method="POST" enctype="multipart/form-data">
    @csrf

    <div class="row g-3">

        {{-- Zona de subida --}}
        <div class="col-12">
            <div class="upload-zone" id="uploadZone">
                <input type="file" name="archivo" id="archivoInput" accept=".xlsx,.xls,.csv" required class="upload-input">
                <div class="upload-zone-content" id="uploadContent">
                    <i class="bi bi-cloud-upload upload-icon"></i>
                    <p class="fw-semibold mb-1 mt-2">Arrastra aquí tu archivo Excel</p>
                    <p class="text-muted small mb-3">o haz clic para seleccionarlo</p>
                    <span class="btn btn-outline-success btn-sm">
                        <i class="bi bi-folder2-open me-1"></i> Elegir archivo
                    </span>
                    <p class="text-muted small mt-2 mb-0">.xlsx · .xls · .csv</p>
                </div>
                <div class="upload-zone-selected d-none" id="uploadSelected">
                    <i class="bi bi-file-earmark-excel text-success" style="font-size:2rem;"></i>
                    <p class="fw-semibold mb-0 mt-2" id="fileName">—</p>
                    <p class="text-muted small mb-2" id="fileSize">—</p>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCambiarArchivo">
                        <i class="bi bi-arrow-repeat me-1"></i> Cambiar archivo
                    </button>
                </div>
            </div>
        </div>

        <input type="hidden" name="vendedor_id" value="{{ $vendedorDefault->id }}">
        <input type="hidden" name="metodo_pago" value="efectivo">

        {{-- Info columnas --}}
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-3">
                    <p class="fw-semibold mb-2 small"><i class="bi bi-info-circle text-info me-1"></i> Columnas reconocidas del Excel</p>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        @foreach(['fecha_emisi','Doc','Serie','NroDocumen','Producto','Precio','Cantidad','Total'] as $col)
                            <code class="badge bg-light text-dark border" style="font-size:.8rem;">{{ $col }}</code>
                        @endforeach
                    </div>
                    <ul class="mb-0 small text-muted">
                        <li>Filas con mismo <strong>Doc + Serie + Número</strong> se agrupan en <strong>una sola venta</strong>.</li>
                        <li><strong>B</strong> = Boleta · <strong>F</strong> = Factura · <strong>P</strong> = Proforma.</li>
                        <li>Las demás columnas del archivo se ignoran.</li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Botones --}}
        <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-success px-4" id="btnImportar" disabled>
                <i class="bi bi-upload me-1"></i> Vista previa
            </button>
            <a href="/casadets/ventas" class="btn btn-outline-secondary">Cancelar</a>
        </div>

    </div>
</form>

<style>
.upload-zone {
    border: 2px dashed #ced4da;
    border-radius: 12px;
    background: #f8fffe;
    text-align: center;
    padding: 2.5rem 1rem;
    position: relative;
    cursor: pointer;
    transition: border-color .2s, background .2s;
}
.upload-zone:hover, .upload-zone.drag-over {
    border-color: #198754;
    background: #f0fff4;
}
.upload-zone.has-file {
    border-color: #198754;
    background: #f0fff4;
}
.upload-input {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
    width: 100%;
    height: 100%;
    z-index: 2;
}
.upload-icon {
    font-size: 3rem;
    color: #adb5bd;
}
.upload-zone.has-file .upload-icon {
    color: #198754;
}
</style>

<script>
const input = document.getElementById('archivoInput');
const zone = document.getElementById('uploadZone');
const content = document.getElementById('uploadContent');
const selected = document.getElementById('uploadSelected');
const fileName = document.getElementById('fileName');
const fileSize = document.getElementById('fileSize');
const btnImportar = document.getElementById('btnImportar');
const btnCambiar = document.getElementById('btnCambiarArchivo');

function showFile(file) {
    if (!file) return;
    fileName.textContent = file.name;
    fileSize.textContent = (file.size / 1024).toFixed(1) + ' KB';
    content.classList.add('d-none');
    selected.classList.remove('d-none');
    zone.classList.add('has-file');
    btnImportar.disabled = false;
}

input.addEventListener('change', () => { if (input.files[0]) showFile(input.files[0]); });

btnCambiar.addEventListener('click', (e) => {
    e.stopPropagation();
    input.value = '';
    content.classList.remove('d-none');
    selected.classList.add('d-none');
    zone.classList.remove('has-file');
    btnImportar.disabled = true;
});

zone.addEventListener('dragover', (e) => { e.preventDefault(); zone.classList.add('drag-over'); });
zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
zone.addEventListener('drop', (e) => {
    e.preventDefault();
    zone.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file) {
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        showFile(file);
    }
});
</script>

@endsection
