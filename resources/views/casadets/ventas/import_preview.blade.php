@extends('layouts.app')

@section('content')
@php
    $metodos = ['efectivo','tarjeta','yape','plin','transferencia'];
@endphp
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">Vista previa de importación</h3>
        <p class="text-muted mb-0">Revisa, edita y confirma. Detectadas: <strong id="contadorVentas">{{ count($grupos) }}</strong> venta(s).</p>
    </div>
    <a href="/casadets/ventas/import" class="btn btn-outline-secondary btn-sm">← Cancelar</a>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

@if(!empty($duplicadosExistentes))
    <div class="alert alert-danger">
        <strong>Facturas ya registradas en el sistema:</strong> {{ implode(', ', $duplicadosExistentes) }}.
        Si las dejas, la importación será rechazada. Elimina o cambia el número de esas ventas.
    </div>
@endif

<div class="alert alert-warning small mb-3">
    <i class="bi bi-pencil-square"></i>
    Puedes <strong>cambiar el vendedor</strong>, <strong>el método de pago</strong>, <strong>editar productos</strong>, <strong>eliminar productos o ventas completas</strong>, y <strong>ajustar el total cobrado</strong>.
</div>

<form action="/casadets/ventas/import/confirm" method="POST" id="formImport">
    @csrf

    <div class="d-flex justify-content-end mb-3">
        <button type="submit" class="btn btn-success">
            <i class="bi bi-check-lg"></i> Confirmar e importar todo
        </button>
    </div>

    <div id="ventasContainer">
        @foreach($grupos as $i => $g)
        @php
            $numFmt = trim(($g['serie'] ?? '') . '-' . ($g['numero'] ?? ''), '-');
            $esDup = in_array($numFmt, $duplicadosExistentes);
        @endphp
        <div class="card mb-3 venta-card {{ $esDup ? 'border-danger' : '' }}" data-idx="{{ $i }}">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <div>
                    <strong>Venta #{{ $i + 1 }}</strong>
                    <span class="text-muted ms-2">{{ \Carbon\Carbon::parse($g['fecha'])->format('d/m/Y') }}</span>
                    @if($numFmt)
                        <span class="badge {{ strtoupper($g['doc']) == 'B' ? 'bg-secondary' : 'bg-primary' }} ms-2">
                            {{ $numFmt }}
                        </span>
                    @endif
                    @if($esDup)
                        <span class="badge bg-danger ms-2"><i class="bi bi-exclamation-triangle"></i> Factura duplicada</span>
                    @endif
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-venta" title="Eliminar esta venta">
                    <i class="bi bi-trash"></i> Eliminar venta
                </button>
            </div>

            <div class="card-body">
                <input type="hidden" name="ventas[{{ $i }}][fecha]" value="{{ $g['fecha'] }}">
                <input type="hidden" name="ventas[{{ $i }}][doc]" value="{{ $g['doc'] }}">
                <input type="hidden" name="ventas[{{ $i }}][serie]" value="{{ $g['serie'] }}">
                <input type="hidden" name="ventas[{{ $i }}][numero]" value="{{ $g['numero'] }}">

                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Vendedor</label>
                        <select name="ventas[{{ $i }}][vendedor_id]" class="form-select form-select-sm" required>
                            @foreach($vendedores as $v)
                                <option value="{{ $v->id }}" {{ $v->id == $vendedor_id_default ? 'selected' : '' }}>{{ $v->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1">Método pago</label>
                        <select name="ventas[{{ $i }}][metodo_pago]" class="form-select form-select-sm" required>
                            @foreach($metodos as $m)
                                <option value="{{ $m }}" {{ $m == $metodo_pago_default ? 'selected' : '' }}>{{ ucfirst($m) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1">Total real</label>
                        <div class="form-control form-control-sm bg-light text-end fw-semibold total-real-display">
                            S/ {{ number_format($g['total'], 2) }}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Total cobrado</label>
                        <input type="number" name="ventas[{{ $i }}][total_cobrado]"
                            value="{{ number_format($g['total'], 2, '.', '') }}"
                            step="0.01" min="0"
                            class="form-control form-control-sm text-end fw-semibold total-cobrado" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1">Diferencia</label>
                        <div class="form-control form-control-sm text-end fw-semibold diferencia bg-light">S/ 0.00</div>
                    </div>
                </div>

                <table class="table table-sm align-middle mb-0 productos-tabla">
                    <thead class="table-light">
                        <tr>
                            <th>Producto</th>
                            <th style="width:90px;" class="text-end">Cantidad</th>
                            <th style="width:120px;" class="text-end">Precio unit.</th>
                            <th style="width:120px;" class="text-end">Subtotal</th>
                            <th style="width:50px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($g['detalles'] as $j => $d)
                        <tr class="producto-row">
                            <td>
                                <input type="text" name="ventas[{{ $i }}][detalles][{{ $j }}][producto]"
                                    value="{{ $d['producto'] }}" class="form-control form-control-sm" required>
                            </td>
                            <td>
                                <input type="number" name="ventas[{{ $i }}][detalles][{{ $j }}][cantidad]"
                                    value="{{ rtrim(rtrim(number_format($d['cantidad'], 2, '.', ''), '0'), '.') }}"
                                    step="0.01" min="0"
                                    class="form-control form-control-sm text-end cantidad-input" required>
                            </td>
                            <td>
                                <input type="number" name="ventas[{{ $i }}][detalles][{{ $j }}][precio_unitario]"
                                    value="{{ number_format($d['precio_unitario'], 2, '.', '') }}"
                                    step="0.01" min="0"
                                    class="form-control form-control-sm text-end precio-input" required>
                            </td>
                            <td class="text-end subtotal-display">S/ {{ number_format($d['subtotal'], 2) }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-producto" title="Eliminar producto">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endforeach
    </div>

    <div id="sinVentas" class="alert alert-secondary text-center" style="display:none;">
        No quedan ventas para importar. <a href="/casadets/ventas/import">Volver</a>.
    </div>

    <div class="d-flex justify-content-between align-items-center mt-3">
        <a href="/casadets/ventas/import" class="btn btn-outline-secondary">← Volver</a>
        <button type="submit" class="btn btn-success">
            <i class="bi bi-check-lg"></i> Confirmar e importar todo
        </button>
    </div>
</form>

<script>
function recalcVenta(card) {
    let totalReal = 0;
    card.querySelectorAll('.producto-row').forEach(row => {
        const c = parseFloat(row.querySelector('.cantidad-input').value) || 0;
        const p = parseFloat(row.querySelector('.precio-input').value) || 0;
        const sub = c * p;
        row.querySelector('.subtotal-display').textContent = 'S/ ' + sub.toFixed(2);
        totalReal += sub;
    });
    card.querySelector('.total-real-display').textContent = 'S/ ' + totalReal.toFixed(2);
    card.dataset.totalReal = totalReal;
    recalcDiferencia(card);
}
function recalcDiferencia(card) {
    const real = parseFloat(card.dataset.totalReal) || 0;
    const cob = parseFloat(card.querySelector('.total-cobrado').value) || 0;
    const d = cob - real;
    const dif = card.querySelector('.diferencia');
    let txt = 'S/ ' + d.toFixed(2);
    if (d > 0.005) { dif.className = 'form-control form-control-sm text-end fw-semibold diferencia bg-light text-success'; txt = '+' + txt; }
    else if (d < -0.005) { dif.className = 'form-control form-control-sm text-end fw-semibold diferencia bg-light text-danger'; }
    else { dif.className = 'form-control form-control-sm text-end fw-semibold diferencia bg-light text-muted'; }
    dif.textContent = txt;
}
function actualizarContador() {
    const n = document.querySelectorAll('.venta-card').length;
    document.getElementById('contadorVentas').textContent = n;
    document.getElementById('sinVentas').style.display = n === 0 ? '' : 'none';
}
document.querySelectorAll('.venta-card').forEach(card => {
    recalcVenta(card);
    card.addEventListener('input', e => {
        if (e.target.matches('.cantidad-input, .precio-input')) recalcVenta(card);
        else if (e.target.matches('.total-cobrado')) recalcDiferencia(card);
    });
    card.querySelector('.btn-eliminar-venta').addEventListener('click', () => {
        if (confirm('¿Eliminar esta venta completa? No se importará.')) { card.remove(); actualizarContador(); }
    });
    card.querySelectorAll('.btn-eliminar-producto').forEach(btn => {
        btn.addEventListener('click', () => {
            const filas = card.querySelectorAll('.producto-row');
            if (filas.length <= 1) { alert('No puedes eliminar el último producto. Elimina la venta completa.'); return; }
            btn.closest('tr').remove(); recalcVenta(card);
        });
    });
});
document.getElementById('formImport').addEventListener('submit', e => {
    if (document.querySelectorAll('.venta-card').length === 0) { e.preventDefault(); alert('No hay ventas para importar.'); }
});
</script>
@endsection
