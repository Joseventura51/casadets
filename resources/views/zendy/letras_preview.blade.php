@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h3 class="mb-1">Análisis del reporte</h3><p class="text-muted mb-0">Selecciona las empresas que deseas añadir al sistema.</p></div>
    <a href="/zendy/letras/importar" class="btn btn-outline-secondary">Cambiar archivo</a>
</div>
<form action="/zendy/letras/importar/confirmar" method="POST">
    @csrf
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">{{ $empresas->count() }} empresas encontradas</span>
            <div>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="seleccionarTodas(true)">Seleccionar todas</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="seleccionarTodas(false)">Ninguna</button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th style="width:45px"></th><th>Empresa / RUC</th><th class="text-end">Documentos</th><th class="text-end">F001</th><th class="text-end">Total soles</th></tr></thead>
                <tbody>
                @foreach($empresas as $empresa)
                    <tr>
                        <td><input class="empresa-check form-check-input" type="checkbox" name="empresas[]" value="{{ $empresa['clave'] }}" checked></td>
                        <td><div class="fw-semibold">{{ $empresa['razon_social'] }}</div><small class="text-muted">{{ $empresa['ruc'] ?: 'Sin RUC' }}</small></td>
                        <td class="text-end">{{ number_format($empresa['documentos']) }}</td>
                        <td class="text-end"><span class="badge bg-success">{{ number_format($empresa['f001']) }}</span></td>
                        <td class="text-end fw-semibold">S/ {{ number_format($empresa['total'], 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot class="table-light"><tr><th colspan="2">Total del archivo</th><th class="text-end">{{ number_format($empresas->sum('documentos')) }}</th><th class="text-end">{{ number_format($empresas->sum('f001')) }}</th><th class="text-end">S/ {{ number_format($empresas->sum('total'), 2) }}</th></tr></tfoot>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-muted">Solo se importarán las empresas marcadas. Los documentos ya existentes se omitirán.</small>
            <button class="btn btn-success" onclick="return confirmarImportacion()"><i class="bi bi-check2-circle me-1"></i>Importar seleccionadas</button>
        </div>
    </div>
</form>
<script>
function seleccionarTodas(valor) { document.querySelectorAll('.empresa-check').forEach(c => c.checked = valor); }
function confirmarImportacion() {
    const total = document.querySelectorAll('.empresa-check:checked').length;
    if (!total) { alert('Selecciona al menos una empresa.'); return false; }
    return confirm('Se importarán ' + total + ' empresa(s) seleccionada(s). ¿Continuar?');
}
</script>
@endsection