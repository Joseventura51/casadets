@php
    $tipos = ['boleta','factura','guia','recibo','otro'];
    $detallesYa    = $compra->detalles ?? collect();
    $cantidadesYa  = $detallesYa->keyBy('id')->map(fn($d) => $d->pivot->cantidad ?? 1);
    $lineasYa      = $compra->exists ? ($compra->lineas ?? collect()) : collect();
    $lineasJsonData = $lineasYa->map(fn($l) => [
        'producto'       => $l->producto ?? '',
        'cantidad'       => (float) $l->cantidad,
        'monto_unitario' => (float) $l->monto_unitario,
        'monto_total'    => (float) $l->monto_total,
    ]);
@endphp
@if($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-receipt me-1"></i> Datos de la compra</div>
    <div class="card-body">
        <div class="row g-3 mb-3">
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
                <label class="form-label">Observaciones</label>
                <textarea name="observaciones" class="form-control" rows="2">{{ old('observaciones', $compra->observaciones ?? '') }}</textarea>
            </div>
        </div>

        {{-- Tabla de productos --}}
        <div class="border rounded overflow-hidden">
            <div class="bg-light px-3 py-2 fw-semibold d-flex justify-content-between align-items-center border-bottom">
                <span><i class="bi bi-list-ul me-1"></i> Productos comprados</span>
                <button type="button" id="btnAgregarLinea" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-plus-lg me-1"></i> Agregar producto
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Producto / Descripción</th>
                            <th class="text-end" style="width:95px;">Cantidad</th>
                            <th class="text-end" style="width:105px;">P. unitario</th>
                            <th class="text-end" style="width:115px;">Total línea</th>
                            <th style="width:38px;"></th>
                        </tr>
                    </thead>
                    <tbody id="lineasBody"></tbody>
                </table>
            </div>
            <div class="px-3 py-2 bg-light border-top d-flex justify-content-end align-items-center gap-3">
                <span class="text-muted small">Total general</span>
                <span id="totalGeneral" class="fs-5 fw-bold text-primary">S/ 0.00</span>
                <input type="hidden" name="monto_total" id="monto_total" value="0">
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-link-45deg me-1"></i> Vincular a productos de una venta
        <small class="text-muted fw-normal ms-1">— opcional, para productos no propios</small>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Busca por número de documento, vendedor o nombre de producto. Marca los ítems que correspondan a esta compra.
        </p>

        <div class="mb-3">
            <label class="form-label small mb-1">Buscar por documento, vendedor o producto</label>
            <div class="position-relative">
                <span class="position-absolute top-50 translate-middle-y ms-2 text-muted" style="left:4px;pointer-events:none;">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text" id="ventaBuscador" class="form-control ps-4"
                    placeholder="Ej: F001-123, Juan, ceramico…" autocomplete="off">
                <div id="ventaDropdown"
                    style="display:none; position:absolute; top:100%; left:0; right:0; z-index:1050;
                           max-height:300px; overflow-y:auto; border:1px solid #dee2e6;
                           border-radius:0 0 .375rem .375rem; background:#fff; box-shadow:0 4px 12px rgba(0,0,0,.1);">
                </div>
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
// ── Helpers ────────────────────────────────────────────────────
function escHtml(s) {
    return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

// ── Líneas de productos ────────────────────────────────────────
const lineasIniciales = @json($lineasJsonData);
const lineasBody      = document.getElementById('lineasBody');
const totalGeneralEl  = document.getElementById('totalGeneral');
const montoTotalInput = document.getElementById('monto_total');
let lineaIdx = 0;

function actualizarTotalGeneral() {
    let sum = 0;
    lineasBody.querySelectorAll('.linea-total').forEach(i => sum += parseFloat(i.value) || 0);
    totalGeneralEl.textContent = 'S/ ' + sum.toFixed(2);
    montoTotalInput.value = sum.toFixed(2);
}

function agregarLinea(prod = '', cant = 1, unit = 0, tot = null) {
    const idx  = lineaIdx++;
    const calc = tot !== null ? parseFloat(tot).toFixed(2) : (cant * unit).toFixed(2);
    const tr   = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <input type="text" name="lineas[${idx}][producto]" value="${escHtml(prod)}"
                class="form-control form-control-sm" placeholder="Producto o descripción">
        </td>
        <td>
            <input type="number" name="lineas[${idx}][cantidad]" value="${cant}"
                step="0.01" min="0" class="form-control form-control-sm text-end linea-cant">
        </td>
        <td>
            <input type="number" name="lineas[${idx}][monto_unitario]" value="${unit}"
                step="0.01" min="0" class="form-control form-control-sm text-end linea-unit">
        </td>
        <td>
            <input type="number" name="lineas[${idx}][monto_total]" value="${calc}"
                step="0.01" min="0" class="form-control form-control-sm text-end linea-total fw-semibold">
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger btn-q-linea px-1">
                <i class="bi bi-x"></i>
            </button>
        </td>`;
    const cantI = tr.querySelector('.linea-cant');
    const unitI = tr.querySelector('.linea-unit');
    const totI  = tr.querySelector('.linea-total');
    const recalc = () => {
        totI.value = ((parseFloat(cantI.value) || 0) * (parseFloat(unitI.value) || 0)).toFixed(2);
        actualizarTotalGeneral();
    };
    cantI.addEventListener('input', recalc);
    unitI.addEventListener('input', recalc);
    totI.addEventListener('input', actualizarTotalGeneral);
    tr.querySelector('.btn-q-linea').addEventListener('click', () => {
        tr.remove();
        actualizarTotalGeneral();
    });
    lineasBody.appendChild(tr);
    actualizarTotalGeneral();
}

document.getElementById('btnAgregarLinea').addEventListener('click', () => agregarLinea());

if (lineasIniciales.length > 0) {
    lineasIniciales.forEach(l => agregarLinea(l.producto, l.cantidad, l.monto_unitario, l.monto_total));
} else {
    agregarLinea();
}

// ── Vinculación con ventas ─────────────────────────────────────
const facturasCargadas = new Set();
const container   = document.getElementById('facturasContainer');
const sinSel      = document.getElementById('sinSeleccion');
const seleccionadosIniciales = @json(array_map('intval', $detallesSeleccionados ?? []));
const cantidadesIniciales    = @json($cantidadesYa->toArray());
const facturasIniciales      = @json(
    ($detallesYa->pluck('venta')->filter()->unique('id')->values()->map(fn($v)=>$v->id))->toArray()
);

@php
$ventasJson = $facturas->map(fn($f) => [
    'id'            => $f->id,
    'tipo'          => ucfirst($f->documento_tipo ?? ''),
    'numero'        => $f->documento_numero ?? '',
    'fecha'         => $f->fecha->format('Y-m-d'),
    'fecha_display' => $f->fecha->format('d/m/Y'),
    'vendedor'      => $f->vendedor->nombre ?? 'Sin vendedor',
    'total'         => number_format($f->total_cobrado, 2),
    'productos'     => $f->detalles->pluck('producto')->filter()->implode(' '),
]);
@endphp
const todasLasVentas = @json($ventasJson);

const buscador = document.getElementById('ventaBuscador');
const dropdown = document.getElementById('ventaDropdown');

function filtrarVentas() {
    const texto = buscador.value.toLowerCase().trim();
    return todasLasVentas.filter(v =>
        (!texto || (v.tipo + ' ' + v.numero + ' ' + v.vendedor + ' ' + v.productos).toLowerCase().includes(texto)) &&
        !facturasCargadas.has(v.id)
    );
}

function mostrarDropdown() {
    const res = filtrarVentas();
    dropdown.innerHTML = '';
    if (res.length === 0) {
        dropdown.innerHTML = '<div style="padding:.5rem .9rem;color:#6c757d;font-size:.85rem;">Sin resultados</div>';
    } else {
        res.forEach(v => {
            const item = document.createElement('div');
            item.style.cssText = 'padding:.45rem .9rem;cursor:pointer;font-size:.85rem;border-bottom:1px solid #f1f3f5;';
            item.innerHTML = `<span class="badge bg-secondary me-1" style="font-size:.7rem;">${escHtml(v.tipo)}</span>`
                + `<strong>${escHtml(v.numero)}</strong>`
                + ` <span class="text-muted small">· ${v.fecha_display} · ${escHtml(v.vendedor)} · S/ ${v.total}</span>`;
            if (v.productos) {
                item.innerHTML += `<div style="font-size:.78rem;color:#888;margin-top:1px;">${escHtml(v.productos.slice(0, 80))}</div>`;
            }
            item.addEventListener('mouseover', () => item.style.background = '#f0f4ff');
            item.addEventListener('mouseout',  () => item.style.background = '');
            item.addEventListener('mousedown', e => {
                e.preventDefault();
                buscador.value = '';
                dropdown.style.display = 'none';
                cargarFactura(v.id);
            });
            dropdown.appendChild(item);
        });
    }
    dropdown.style.display = '';
}

buscador.addEventListener('focus', mostrarDropdown);
buscador.addEventListener('input', mostrarDropdown);
buscador.addEventListener('blur',  () => setTimeout(() => { dropdown.style.display = 'none'; buscador.value = ''; }, 200));

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
        actualizarSinSel();
    } catch(e) { alert('No se pudo cargar: ' + e.message); }
}

function renderFactura(data) {
    const card = document.createElement('div');
    card.className = 'card mb-2 border-primary-subtle';
    card.dataset.ventaId = data.venta.id;
    card.innerHTML = `
        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
            <div>
                <strong>${escHtml(data.venta.documento)}</strong>
                <span class="text-muted small ms-2">${data.venta.fecha} · ${escHtml(data.venta.vendedor)}</span>
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
        const checked   = seleccionadosIniciales.includes(d.id) ? 'checked' : '';
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
                    style="width:80px;display:inline-block;"
                    ${!checked ? 'disabled' : ''}>
            </td>`;
        tbody.appendChild(tr);
        const cb        = tr.querySelector('.detalle-check');
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
        card.remove();
        actualizarSinSel();
    });
    card.querySelector('.btn-marcar-todos').addEventListener('click', () => {
        const all = card.querySelectorAll('.detalle-check');
        const algunoSinMarcar = Array.from(all).some(c => !c.checked);
        all.forEach(c => { c.checked = algunoSinMarcar; c.dispatchEvent(new Event('change')); });
    });
    container.appendChild(card);
}

facturasIniciales.forEach(id => cargarFactura(id));
</script>
