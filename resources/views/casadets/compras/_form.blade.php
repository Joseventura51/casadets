@php
    $tipos = ['boleta','factura','guia','recibo','otro'];
    $detallesYa = $compra->detalles ?? collect();
    $cantidadesYa = $detallesYa->keyBy('id')->map(fn($d) => $d->pivot->cantidad ?? 1);
@endphp
@if($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-receipt me-1"></i> Datos de la compra</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Empresa / Proveedor *</label>
                <input type="text" name="empresa" value="{{ old('empresa', $compra->empresa ?? '') }}" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Fecha *</label>
                <input type="date" name="fecha" value="{{ old('fecha', $compra->fecha ? $compra->fecha->format('Y-m-d') : date('Y-m-d')) }}" class="form-control" required>
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
                <label class="form-label">Producto / Descripción general</label>
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
                    <button type="button" id="recalcularTotal" class="btn btn-link btn-sm p-0 ms-1">
                        <i class="bi bi-arrow-clockwise"></i> Recalcular
                    </button>
                </label>
                <input type="number" step="0.01" min="0" name="monto_total" id="monto_total" value="{{ old('monto_total', $compra->monto_total ?? 0) }}" class="form-control text-end fw-semibold" required>
                <small class="text-muted">Editable. Si lo cambias, no afecta al unitario.</small>
            </div>
            <div class="col-md-3">
                <label class="form-label">Sugerido (cant × unit.)</label>
                <div class="form-control bg-light text-end" id="sugerido">S/ 0.00</div>
            </div>
            <div class="col-12">
                <label class="form-label">Observaciones</label>
                <textarea name="observaciones" class="form-control" rows="2">{{ old('observaciones', $compra->observaciones ?? '') }}</textarea>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-link-45deg me-1"></i> Vincular a productos de facturas
        <small class="text-muted fw-normal ms-1">— opcional, para productos no propios</small>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Elige una factura para ver sus productos. Marca los que correspondan a esta compra e indica la cantidad comprada de cada uno.
        </p>

        <div class="row g-2 align-items-end mb-3">
            <div class="col-md-9">
                <label class="form-label small mb-1">Factura</label>
                <select id="facturaSelector" class="form-select">
                    <option value="">— Elige una factura —</option>
                    @foreach($facturas as $f)
                        <option value="{{ $f->id }}">
                            {{ $f->documento_numero }} · {{ $f->fecha->format('d/m/Y') }} · {{ $f->vendedor->nombre ?? 'Sin vendedor' }} · S/ {{ number_format($f->total_cobrado, 2) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <button type="button" id="btnCargarFactura" class="btn btn-outline-primary w-100">
                    <i class="bi bi-plus-lg"></i> Cargar productos
                </button>
            </div>
        </div>

        <div id="facturasContainer"></div>

        <div id="sinSeleccion" class="alert alert-light border text-center small mb-0">
            Sin productos vinculados. La compra se guardará sin vincular a ninguna venta.
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center">
    <a href="/casadets/compras" class="btn btn-outline-secondary">Cancelar</a>
    <button class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Guardar</button>
</div>

<script>
const facturasCargadas = new Set();
const container = document.getElementById('facturasContainer');
const sinSel = document.getElementById('sinSeleccion');
const selector = document.getElementById('facturaSelector');
const seleccionadosIniciales = @json(array_map('intval', $detallesSeleccionados ?? []));
const cantidadesIniciales = @json($cantidadesYa->toArray());
const facturasIniciales = @json(
    ($detallesYa->pluck('venta')->filter()->unique('id')->values()->map(fn($v)=>$v->id))->toArray()
);

// Montos/sugerido en datos de compra
const cant = document.getElementById('cantidad');
const unit = document.getElementById('monto_unitario');
const total = document.getElementById('monto_total');
const sug = document.getElementById('sugerido');
function calcSugerido() {
    const c = parseFloat(cant.value)||0, u = parseFloat(unit.value)||0;
    sug.textContent = 'S/ ' + (c*u).toFixed(2);
}
cant.addEventListener('input', calcSugerido);
unit.addEventListener('input', calcSugerido);
document.getElementById('recalcularTotal').addEventListener('click', () => {
    total.value = ((parseFloat(cant.value)||0)*(parseFloat(unit.value)||0)).toFixed(2);
});
calcSugerido();

// ── Vinculación ──────────────────────────────────────────────
function actualizarSinSel() {
    sinSel.style.display = container.querySelectorAll('input.detalle-check:checked').length ? 'none' : '';
}

async function cargarFactura(ventaId) {
    if (!ventaId || facturasCargadas.has(parseInt(ventaId))) return;
    try {
        const res = await fetch(`/casadets/ventas/${ventaId}/detalles.json`);
        if (!res.ok) throw new Error('Error al cargar');
        renderFactura(await res.json());
        facturasCargadas.add(parseInt(ventaId));
        const opt = selector.querySelector(`option[value="${ventaId}"]`);
        if (opt) opt.remove();
        selector.value = '';
        actualizarSinSel();
    } catch(e) { alert('No se pudo cargar la factura: '+e.message); }
}

function renderFactura(data) {
    const card = document.createElement('div');
    card.className = 'card mb-2 border-primary-subtle';
    card.dataset.ventaId = data.venta.id;
    card.innerHTML = `
        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
            <div>
                <strong>${data.venta.documento}</strong>
                <span class="text-muted small ms-2">${data.venta.fecha} · ${data.venta.vendedor}</span>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-link p-0 btn-marcar-todos">Marcar todos</button>
                <button type="button" class="btn btn-sm btn-outline-danger btn-quitar-factura"><i class="bi bi-x"></i></button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light"><tr>
                    <th style="width:40px;"></th>
                    <th>Producto</th>
                    <th class="text-end" style="width:80px;">Cant. venta</th>
                    <th class="text-end" style="width:90px;">Precio</th>
                    <th class="text-end" style="width:100px;">Cant. comprada *</th>
                </tr></thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="card-footer bg-transparent border-0 py-1">
            <small class="text-muted">* Cantidad de este producto que cubre esta compra (puede ser menor a la vendida).</small>
        </div>`;
    const tbody = card.querySelector('tbody');
    data.detalles.forEach(d => {
        const checked = seleccionadosIniciales.includes(d.id) ? 'checked' : '';
        const cantPivot = cantidadesIniciales[d.id] ?? 1;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="text-center">
                <input type="checkbox" name="detalles[]" value="${d.id}" class="form-check-input detalle-check" ${checked}>
            </td>
            <td>${escHtml(d.producto)}</td>
            <td class="text-end text-muted small">${d.cantidad}</td>
            <td class="text-end text-muted small">S/ ${d.precio_unitario.toFixed(2)}</td>
            <td class="text-end">
                <input type="number" name="detalles_cantidad[${d.id}]"
                    value="${checked ? cantPivot : 1}"
                    step="0.01" min="0.01" max="${d.cantidad}"
                    class="form-control form-control-sm text-end cantidad-comprada"
                    style="width:80px; display:inline-block;"
                    ${!checked ? 'disabled' : ''}>
            </td>`;
        tbody.appendChild(tr);
        // habilitar/deshabilitar input de cantidad según checkbox
        const cb = tr.querySelector('.detalle-check');
        const cantInput = tr.querySelector('.cantidad-comprada');
        cb.addEventListener('change', () => {
            cantInput.disabled = !cb.checked;
            if (!cb.checked) cantInput.value = 1;
            actualizarSinSel();
        });
    });
    card.querySelector('.btn-quitar-factura').addEventListener('click', () => {
        card.querySelectorAll('.detalle-check').forEach(c => { c.checked = false; });
        facturasCargadas.delete(parseInt(card.dataset.ventaId));
        card.remove(); actualizarSinSel();
    });
    card.querySelector('.btn-marcar-todos').addEventListener('click', () => {
        const all = card.querySelectorAll('.detalle-check');
        const algunoSinMarcar = Array.from(all).some(c => !c.checked);
        all.forEach(c => {
            c.checked = algunoSinMarcar;
            c.dispatchEvent(new Event('change'));
        });
    });
    container.appendChild(card);
}

function escHtml(s) {
    return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

document.getElementById('btnCargarFactura').addEventListener('click', () => cargarFactura(selector.value));
selector.addEventListener('change', () => { if (selector.value) cargarFactura(selector.value); });
facturasIniciales.forEach(id => cargarFactura(id));
</script>
