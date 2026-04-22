@php
    $tipos = ['boleta','factura','guia','recibo','otro'];
@endphp
@if($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<div class="card">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Empresa / Proveedor *</label>
                <input type="text" name="empresa" value="{{ old('empresa', $compra->empresa ?? '') }}" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Fecha *</label>
                <input type="date" name="fecha" value="{{ old('fecha', isset($compra) ? $compra->fecha->format('Y-m-d') : date('Y-m-d')) }}" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Tipo documento</label>
                <select name="documento_tipo" class="form-select">
                    <option value="">—</option>
                    @foreach($tipos as $t)
                        <option value="{{ $t }}" {{ $t == old('documento_tipo', $compra->documento_tipo ?? '') ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Número documento</label>
                <input type="text" name="documento_numero" value="{{ old('documento_numero', $compra->documento_numero ?? '') }}" class="form-control" placeholder="Ej. F001-123">
            </div>
            <div class="col-md-8">
                <label class="form-label">Producto / Descripción</label>
                <input type="text" name="producto" value="{{ old('producto', $compra->producto ?? '') }}" class="form-control" placeholder="Ej. CERAMICO 60x60">
            </div>

            <div class="col-md-3">
                <label class="form-label">Cantidad *</label>
                <input type="number" step="0.01" min="0" name="cantidad" id="cantidad" value="{{ old('cantidad', $compra->cantidad ?? 1) }}" class="form-control text-end" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Monto unitario *</label>
                <input type="number" step="0.01" min="0" name="monto_unitario" id="monto_unitario" value="{{ old('monto_unitario', $compra->monto_unitario ?? 0) }}" class="form-control text-end" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">
                    Monto total *
                    <button type="button" id="recalcularTotal" class="btn btn-link btn-sm p-0 ms-1" title="Recalcular cantidad × unitario">
                        <i class="bi bi-arrow-clockwise"></i> Recalcular
                    </button>
                </label>
                <input type="number" step="0.01" min="0" name="monto_total" id="monto_total" value="{{ old('monto_total', $compra->monto_total ?? 0) }}" class="form-control text-end fw-semibold" required>
                <small class="text-muted">Editable manualmente. Si lo cambias, no afecta al unitario.</small>
            </div>
            <div class="col-md-3">
                <label class="form-label">Sugerido (cant × unit.)</label>
                <div class="form-control bg-light text-end" id="sugerido">S/ 0.00</div>
            </div>

            <div class="col-12">
                <label class="form-label">Vincular a ventas <small class="text-muted">(opcional, para productos no propios)</small></label>
                <select name="ventas[]" class="form-select" multiple size="6">
                    @foreach($ventas as $v)
                        <option value="{{ $v->id }}" {{ in_array($v->id, old('ventas', $vinculadas ?? [])) ? 'selected' : '' }}>
                            {{ $v->fecha->format('d/m/Y') }} — {{ $v->vendedor->nombre ?? 'Sin vendedor' }}
                            @if($v->documento_numero) | {{ ucfirst($v->documento_tipo) }} {{ $v->documento_numero }}@endif
                            | S/ {{ number_format($v->total_cobrado, 2) }}
                        </option>
                    @endforeach
                </select>
                <small class="text-muted">Mantén Ctrl (o Cmd) presionado para seleccionar varias.</small>
            </div>

            <div class="col-12">
                <label class="form-label">Observaciones</label>
                <textarea name="observaciones" class="form-control" rows="2">{{ old('observaciones', $compra->observaciones ?? '') }}</textarea>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between">
        <a href="/casadets/compras" class="btn btn-outline-secondary">Cancelar</a>
        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar</button>
    </div>
</div>

<script>
const cant = document.getElementById('cantidad');
const unit = document.getElementById('monto_unitario');
const total = document.getElementById('monto_total');
const sug = document.getElementById('sugerido');

function calcSugerido() {
    const c = parseFloat(cant.value) || 0;
    const u = parseFloat(unit.value) || 0;
    sug.textContent = 'S/ ' + (c * u).toFixed(2);
}
cant.addEventListener('input', calcSugerido);
unit.addEventListener('input', calcSugerido);
document.getElementById('recalcularTotal').addEventListener('click', () => {
    const c = parseFloat(cant.value) || 0;
    const u = parseFloat(unit.value) || 0;
    total.value = (c * u).toFixed(2);
});
calcSugerido();
</script>
