@extends('layouts.app')

@section('content')
<div class="card">
    <div class="card-header bg-white">
        <h4 class="mb-0">Registrar venta</h4>
        <p class="text-muted mb-0 small">Una venta puede tener varios productos.</p>
    </div>
    <div class="card-body">
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form action="/casadets/ventas" method="POST" id="ventaForm">
            @csrf

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Vendedor</label>
                    <select name="vendedor_id" class="form-select" required>
                        <option value="">Seleccionar</option>
                        @foreach($vendedores as $v)
                            <option value="{{ $v->id }}" {{ old('vendedor_id') == $v->id ? 'selected' : '' }}>{{ $v->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Fecha</label>
                    <input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}" class="form-control" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Método de pago</label>
                    <select name="metodo_pago" class="form-select" required>
                        @foreach(['efectivo','tarjeta','yape','plin','transferencia'] as $m)
                            <option value="{{ $m }}" {{ old('metodo_pago', 'efectivo') == $m ? 'selected' : '' }}>{{ ucfirst($m) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Documento</label>
                    <select name="documento_tipo" class="form-select">
                        <option value="">Sin doc.</option>
                        @foreach(['boleta','factura','proforma'] as $d)
                            <option value="{{ $d }}" {{ old('documento_tipo') == $d ? 'selected' : '' }}>{{ ucfirst($d) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">N° de documento</label>
                    <input type="text" name="documento_numero" value="{{ old('documento_numero') }}" class="form-control">
                </div>

                <div class="col-md-9">
                    <label class="form-label">Observaciones</label>
                    <input type="text" name="observaciones" value="{{ old('observaciones') }}" class="form-control">
                </div>
            </div>

            <hr>
            <h6 class="mb-2">Productos</h6>

            <div class="table-responsive">
                <table class="table table-sm align-middle" id="tablaProductos">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 45%;">Producto / descripción</th>
                            <th style="width: 15%;">Cantidad</th>
                            <th style="width: 17%;">Precio unitario (S/)</th>
                            <th style="width: 17%;" class="text-end">Subtotal (S/)</th>
                            <th style="width: 6%;"></th>
                        </tr>
                    </thead>
                    <tbody id="productosBody">
                        <!-- Las filas se generan con JS -->
                    </tbody>
                    <tfoot>
                        <tr class="table-light">
                            <th colspan="3" class="text-end">TOTAL</th>
                            <th class="text-end fs-5" id="totalGeneral">S/ 0.00</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <button type="button" class="btn btn-outline-primary btn-sm mb-3" id="btnAgregarProducto">
                <i class="bi bi-plus-lg"></i> Agregar producto
            </button>

            <div class="d-flex gap-2 border-top pt-3">
                <button type="submit" class="btn btn-primary">Guardar venta</button>
                <a href="/casadets/ventas" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    let contador = 0;
    const body = document.getElementById('productosBody');
    const totalEl = document.getElementById('totalGeneral');

    function nuevaFila(producto = '', cantidad = 1, precio = '') {
        const i = contador++;
        const tr = document.createElement('tr');
        tr.dataset.idx = i;
        tr.innerHTML = `
            <td><input type="text" name="productos[${i}][producto]" class="form-control form-control-sm" value="${producto}" required></td>
            <td><input type="number" name="productos[${i}][cantidad]" class="form-control form-control-sm cantidad" step="0.01" min="0.01" value="${cantidad}" required></td>
            <td><input type="number" name="productos[${i}][precio_unitario]" class="form-control form-control-sm precio" step="0.01" min="0" value="${precio}" required></td>
            <td class="text-end subtotal">S/ 0.00</td>
            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger btn-eliminar"><i class="bi bi-trash"></i></button></td>
        `;
        body.appendChild(tr);
        recalcular();
    }

    function recalcular() {
        let total = 0;
        body.querySelectorAll('tr').forEach(tr => {
            const c = parseFloat(tr.querySelector('.cantidad').value) || 0;
            const p = parseFloat(tr.querySelector('.precio').value) || 0;
            const sub = c * p;
            tr.querySelector('.subtotal').textContent = 'S/ ' + sub.toFixed(2);
            total += sub;
        });
        totalEl.textContent = 'S/ ' + total.toFixed(2);
    }

    body.addEventListener('input', e => {
        if (e.target.classList.contains('cantidad') || e.target.classList.contains('precio')) {
            recalcular();
        }
    });

    body.addEventListener('click', e => {
        if (e.target.closest('.btn-eliminar')) {
            if (body.querySelectorAll('tr').length > 1) {
                e.target.closest('tr').remove();
                recalcular();
            } else {
                alert('Debe haber al menos un producto.');
            }
        }
    });

    document.getElementById('btnAgregarProducto').addEventListener('click', () => nuevaFila());

    // Empieza con una fila
    nuevaFila();
})();
</script>
@endsection
