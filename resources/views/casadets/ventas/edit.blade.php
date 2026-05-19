@extends('layouts.app')

@section('content')
@php $tipos = ['boleta','factura','proforma']; @endphp

<style>
.productos-tabla th { font-size:.78rem; text-transform:uppercase; letter-spacing:.03em; color:#6c757d; }
.subtotal-cell { font-size:.87rem; color:#495057; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0"><i class="bi bi-pencil me-2"></i>Editar venta #{{ $venta->id }}</h3>
        <p class="text-muted mb-0 small">{{ $venta->fecha->format('d/m/Y') }} · {{ $venta->vendedor->nombre ?? '—' }}</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/casadets/ventas/{{ $venta->id }}/pago" class="btn btn-outline-success btn-sm">
            <i class="bi bi-cash-stack me-1"></i>Verificar pago
        </a>
        <a href="/casadets/ventas/{{ $venta->id }}" class="btn btn-outline-secondary btn-sm">← Volver</a>
    </div>
</div>

@if($errors->any())
<div class="alert alert-danger mb-3">
    <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<form action="/casadets/ventas/{{ $venta->id }}" method="POST">
    @csrf @method('PUT')

    {{-- Meta de la venta --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-1"></i> Datos generales</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Vendedor</label>
                    <select name="vendedor_id" class="form-select" required>
                        @foreach($vendedores as $v)
                            <option value="{{ $v->id }}" {{ $v->id == old('vendedor_id', $venta->vendedor_id) ? 'selected' : '' }}>{{ $v->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha</label>
                    <input type="date" name="fecha" value="{{ old('fecha', $venta->fecha->format('Y-m-d')) }}" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tipo documento</label>
                    <select name="documento_tipo" class="form-select">
                        <option value="">—</option>
                        @foreach($tipos as $t)
                            <option value="{{ $t }}" {{ $t == old('documento_tipo', $venta->documento_tipo) ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Número documento</label>
                    <input type="text" name="documento_numero" value="{{ old('documento_numero', $venta->documento_numero) }}" class="form-control" placeholder="F002-953">
                </div>
                <div class="col-12">
                    <label class="form-label">Observaciones</label>
                    <textarea name="observaciones" class="form-control" rows="2">{{ old('observaciones', $venta->observaciones) }}</textarea>
                </div>
            </div>
        </div>
    </div>

    {{-- Productos editables --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="bi bi-box me-1"></i> Productos</span>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted small">Total:
                    <strong class="text-dark" id="totalProductosDisplay">
                        S/ {{ number_format($venta->total, 2) }}
                    </strong>
                </span>
                <button type="button" id="btnAgregarProducto" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-plus-lg"></i> Agregar producto
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 productos-tabla" id="productosTable">
                <thead class="table-light">
                    <tr>
                        <th>Producto</th>
                        <th class="text-end" style="width:100px;">Cantidad</th>
                        <th class="text-end" style="width:120px;">Precio unit.</th>
                        <th class="text-end" style="width:110px;">Subtotal</th>
                        <th style="width:44px;"></th>
                    </tr>
                </thead>
                <tbody id="productosBody">
                    @foreach($venta->detalles as $j => $d)
                    <tr class="producto-row">
                        <td>
                            <input type="text" name="productos[{{ $j }}][producto]"
                                value="{{ old("productos.$j.producto", $d->producto) }}"
                                class="form-control form-control-sm" required>
                        </td>
                        <td>
                            <input type="number" name="productos[{{ $j }}][cantidad]"
                                value="{{ old("productos.$j.cantidad", rtrim(rtrim(number_format($d->cantidad,2,'.',''  ),'0'),'.')) }}"
                                step="0.01" min="0.01"
                                class="form-control form-control-sm text-end cantidad-input" required>
                        </td>
                        <td>
                            <input type="number" name="productos[{{ $j }}][precio_unitario]"
                                value="{{ old("productos.$j.precio_unitario", number_format($d->precio_unitario,2,'.',''  )) }}"
                                step="0.01" min="0"
                                class="form-control form-control-sm text-end precio-input" required>
                        </td>
                        <td class="text-end subtotal-cell">S/ {{ number_format($d->subtotal, 2) }}</td>
                        <td class="text-center">
                            <button type="button" class="btn p-0 lh-1 text-danger border-0 bg-transparent btn-del-prod" style="font-size:1.1rem;">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-light d-flex justify-content-end gap-4 py-2">
            <span class="text-muted small">Total cobrado actual:
                <strong>S/ {{ number_format($venta->total_cobrado, 2) }}</strong>
                <span class="text-muted"> (se recalcula con el nuevo total de productos)</span>
            </span>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a href="/casadets/ventas/{{ $venta->id }}" class="btn btn-outline-secondary">Cancelar</a>
        <button class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i> Guardar cambios</button>
    </div>
</form>

<script>
let prodIdx = {{ $venta->detalles->count() }};

function recalcTotal() {
    let total = 0;
    document.querySelectorAll('.producto-row').forEach(row => {
        const c = parseFloat(row.querySelector('.cantidad-input').value) || 0;
        const p = parseFloat(row.querySelector('.precio-input').value)  || 0;
        const sub = c * p;
        row.querySelector('.subtotal-cell').textContent = 'S/ ' + sub.toFixed(2);
        total += sub;
    });
    document.getElementById('totalProductosDisplay').textContent = 'S/ ' + total.toFixed(2);
}

function reindexar() {
    document.querySelectorAll('.producto-row').forEach((row, i) => {
        row.querySelector('input[name*="[producto]"]').name        = `productos[${i}][producto]`;
        row.querySelector('input[name*="[cantidad]"]').name        = `productos[${i}][cantidad]`;
        row.querySelector('input[name*="[precio_unitario]"]').name = `productos[${i}][precio_unitario]`;
    });
    prodIdx = document.querySelectorAll('.producto-row').length;
}

document.getElementById('productosBody').addEventListener('input', e => {
    if (e.target.matches('.cantidad-input,.precio-input')) recalcTotal();
});

document.getElementById('productosBody').addEventListener('click', e => {
    const btn = e.target.closest('.btn-del-prod');
    if (!btn) return;
    if (document.querySelectorAll('.producto-row').length <= 1) {
        alert('Debe quedar al menos un producto.'); return;
    }
    btn.closest('tr').remove();
    reindexar(); recalcTotal();
});

document.getElementById('btnAgregarProducto').addEventListener('click', () => {
    const tbody = document.getElementById('productosBody');
    const tr = document.createElement('tr');
    tr.className = 'producto-row';
    tr.innerHTML = `
        <td><input type="text" name="productos[${prodIdx}][producto]" class="form-control form-control-sm" required placeholder="Nombre del producto"></td>
        <td><input type="number" name="productos[${prodIdx}][cantidad]" value="1" step="0.01" min="0.01" class="form-control form-control-sm text-end cantidad-input" required></td>
        <td><input type="number" name="productos[${prodIdx}][precio_unitario]" value="0" step="0.01" min="0" class="form-control form-control-sm text-end precio-input" required></td>
        <td class="text-end subtotal-cell">S/ 0.00</td>
        <td class="text-center"><button type="button" class="btn p-0 lh-1 text-danger border-0 bg-transparent btn-del-prod" style="font-size:1.1rem;"><i class="bi bi-x-circle"></i></button></td>`;
    tbody.appendChild(tr);
    prodIdx++;
    tr.querySelector('input').focus();
});

recalcTotal();
</script>
@endsection
