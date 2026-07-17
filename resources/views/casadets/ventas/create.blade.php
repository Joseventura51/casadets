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

            {{-- ── IGV hidden fields ─────────────────────────────── --}}
            <input type="hidden" name="igv_incluido" id="igvIncluidoHidden" value="1">
            <input type="hidden" name="igv_porcentaje" id="igvPorcentajeHidden" value="18">

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

                <div class="col-md-4">
                    <label class="form-label">Cliente <small class="text-muted">(opcional)</small></label>
                    <input type="text" id="clienteSearch" class="form-control"
                           list="listClientes"
                           placeholder="Escribe el nombre del cliente…"
                           autocomplete="off"
                           value="{{ old('_cliente_nombre', '') }}">
                    <datalist id="listClientes">
                        @foreach($clientes as $c)
                            <option value="{{ $c->nombre }}{{ $c->documento ? ' (' . $c->documento . ')' : '' }}">
                        @endforeach
                    </datalist>
                    <input type="hidden" name="cliente_id" id="clienteIdHidden" value="{{ old('cliente_id') }}">
                    <div class="form-text">Escribe para buscar, o déjalo vacío si no hay cliente.</div>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Fecha</label>
                    <input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}" class="form-control" required>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Método de pago</label>
                    <select name="metodo_pago" class="form-select">
                        <option value="" {{ old('metodo_pago') === '' ? 'selected' : '' }}>— Pagar después (crédito) —</option>
                        @foreach(['efectivo','tarjeta','yape','plin','transferencia'] as $m)
                            <option value="{{ $m }}" {{ old('metodo_pago', 'efectivo') == $m ? 'selected' : '' }}>{{ ucfirst($m) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Documento</label>
                    <select name="documento_tipo" id="docTipo" class="form-select">
                        <option value="">Sin doc.</option>
                        @if($series->isNotEmpty())
                            @foreach($series as $tipo => $serie)
                                <option value="{{ $tipo }}"
                                        data-preview="{{ $serie->codigo }}-{{ str_pad($serie->correlativo_actual + 1, 8, '0', STR_PAD_LEFT) }}"
                                        data-electronico="{{ in_array($tipo, ['factura','boleta']) ? '1' : '0' }}"
                                        {{ old('documento_tipo') == $tipo ? 'selected' : '' }}>
                                    {{ ucfirst($tipo) }} ({{ $serie->codigo }})
                                </option>
                            @endforeach
                        @else
                            @foreach(['boleta','factura','proforma'] as $d)
                                <option value="{{ $d }}"
                                        data-electronico="{{ in_array($d, ['factura','boleta']) ? '1' : '0' }}"
                                        {{ old('documento_tipo') == $d ? 'selected' : '' }}>{{ ucfirst($d) }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">N° de documento</label>
                    @if($series->isNotEmpty())
                        <input type="text" id="docNumero" class="form-control text-muted"
                               readonly placeholder="Se genera automáticamente">
                        <div class="form-text">Se asigna desde la serie de la caja.</div>
                    @else
                        <input type="text" name="documento_numero" value="{{ old('documento_numero') }}" class="form-control">
                    @endif
                </div>

                <div class="col-md-5">
                    <label class="form-label">Observaciones</label>
                    <input type="text" name="observaciones" value="{{ old('observaciones') }}" class="form-control">
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input type="hidden" name="es_referencia_fiscal" value="0">
                        <input class="form-check-input" type="checkbox" name="es_referencia_fiscal"
                               id="esRefFiscal" value="1"
                               {{ old('es_referencia_fiscal') ? 'checked' : '' }}>
                        <label class="form-check-label small" for="esRefFiscal">
                            Referencia fiscal
                            <i class="bi bi-info-circle text-muted ms-1"
                               title="Marcar si este documento es solo un comprobante fiscal (factura/boleta) que cubre proformas ya cobradas. No generará deuda de cobranza."></i>
                        </label>
                    </div>
                </div>
            </div>

            {{-- ── Aviso auto-emisión electrónica ──────────────────── --}}
            <div id="avisoNubefact" class="alert alert-info d-flex gap-2 align-items-center py-2 mb-2" style="display:none!important;">
                <i class="bi bi-broadcast fs-5"></i>
                <span class="small"><strong>Emisión electrónica automática:</strong> al guardar, se enviará a SUNAT vía Nubefact y se asignará el número de la serie automáticamente.</span>
            </div>

            {{-- ── Configuración IGV ───────────────────────────────── --}}
            <div class="card border-0 bg-light mb-3 px-3 py-2" id="igvConfigCard">
                <div class="d-flex align-items-center gap-4 flex-wrap">
                    <span class="fw-semibold small text-uppercase text-muted" style="letter-spacing:.04em;">
                        <i class="bi bi-percent me-1"></i>Configuración IGV
                    </span>
                    <div class="d-flex align-items-center gap-2">
                        <label class="form-check form-switch mb-0 d-flex align-items-center gap-2 cursor-pointer">
                            <input class="form-check-input" type="checkbox" id="igvSwitch" role="switch" checked style="cursor:pointer;">
                            <span class="form-check-label small" id="igvSwitchLabel">Los precios <strong>incluyen</strong> IGV (18%)</span>
                        </label>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label class="form-label small mb-0 text-muted">Tasa IGV:</label>
                        <div class="input-group input-group-sm" style="width:100px;">
                            <input type="number" id="igvTasa" class="form-control form-control-sm text-end"
                                   value="18" min="0" max="100" step="0.01">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <small class="text-muted" id="igvExplica">El precio ingresado ya contiene el IGV. Se desglosará al emitir.</small>
                </div>
            </div>

            <hr>
            <h6 class="mb-2">Productos</h6>

            <div class="table-responsive">
                <table class="table table-sm align-middle" id="tablaProductos">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 35%;">Producto / descripción</th>
                            <th style="width: 12%;">Unidad</th>
                            <th style="width: 12%;">Cantidad</th>
                            <th style="width: 15%;">Precio (S/)</th>
                            <th style="width: 14%;" class="text-end" id="thPrecioLabel">Subtotal (S/)</th>
                            <th style="width: 5%;"></th>
                        </tr>
                    </thead>
                    <tbody id="productosBody">
                    </tbody>
                    <tfoot>
                        <tr class="table-light">
                            <th colspan="4" class="text-end">SUBTOTAL (sin IGV)</th>
                            <th class="text-end" id="subtotalSinIgv" style="display:none;">S/ 0.00</th>
                            <th></th>
                        </tr>
                        <tr class="table-light" id="igvRow" style="display:none;">
                            <th colspan="4" class="text-end text-muted small">IGV (<span id="igvPctLabel">18</span>%)</th>
                            <th class="text-end text-muted small" id="igvMonto">S/ 0.00</th>
                            <th></th>
                        </tr>
                        <tr class="table-light">
                            <th colspan="4" class="text-end">TOTAL</th>
                            <th class="text-end fs-5" id="totalGeneral">S/ 0.00</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <button type="button" class="btn btn-outline-primary btn-sm mb-3" id="btnAgregarProducto">
                <i class="bi bi-plus-lg"></i> Agregar producto
            </button>

            {{-- ── Ajuste Manual de Cobro ─────────────────────────────── --}}
            <div class="card border-0 bg-light mb-3" id="ajusteCard">
                <div class="card-body py-3">
                    <h6 class="text-muted small mb-3 text-uppercase fw-semibold" style="letter-spacing:.04em;">
                        <i class="bi bi-sliders me-1"></i>Ajuste Manual de Cobro
                    </h6>
                    <div class="row align-items-start g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Total Original <small>(calculado)</small></label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">S/</span>
                                <input type="text" id="totalOriginalDisplay"
                                       class="form-control form-control-sm bg-white text-end fw-semibold" readonly value="0.00">
                            </div>
                            <div class="form-text">Suma exacta de los productos.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold" for="totalACobrar">
                                Total a Cobrar
                                <i class="bi bi-pencil-square text-primary ms-1"
                                   title="Puedes ajustar el importe a cobrar. La diferencia queda registrada para auditoría."></i>
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">S/</span>
                                <input type="number" id="totalACobrar" name="total_cobrar"
                                       class="form-control form-control-sm text-end"
                                       step="0.01" min="0" value="0.00">
                            </div>
                            <div class="form-text">Edita si el importe cobrado es distinto.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Ajuste registrado</label>
                            <div class="pt-1" id="ajusteDisplay" style="font-size:.9rem; min-height:2rem;">
                                <span class="text-muted">Sin ajuste</span>
                            </div>
                            <div class="form-text">Diferencia para auditoría interna.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 border-top pt-3">
                <button type="submit" class="btn btn-primary">Guardar venta</button>
                <a href="/casadets/ventas" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    const UNIDADES = @json(\App\Models\VentaDetalle::UNIDADES_MEDIDA);
    let contador = 0;
    const body = document.getElementById('productosBody');
    const totalEl = document.getElementById('totalGeneral');

    // IGV state
    let igvIncluido = true;
    let igvPct = 18;

    function igvFactor() { return igvPct / 100; }

    function nuevaFila(producto = '', unidad = 'NIU', cantidad = 1, precio = '') {
        const i = contador++;
        const optionsHtml = Object.entries(UNIDADES)
            .map(([k,v]) => `<option value="${k}" ${k === unidad ? 'selected' : ''}>${v}</option>`)
            .join('');
        const tr = document.createElement('tr');
        tr.dataset.idx = i;
        tr.innerHTML = `
            <td><input type="text" name="productos[${i}][producto]" class="form-control form-control-sm" value="${producto}" required></td>
            <td>
                <select name="productos[${i}][unidad_medida]" class="form-select form-select-sm unidad">
                    ${optionsHtml}
                </select>
            </td>
            <td><input type="number" name="productos[${i}][cantidad]" class="form-control form-control-sm cantidad" step="0.01" min="0.01" value="${cantidad}" required></td>
            <td><input type="number" name="productos[${i}][precio_unitario]" class="form-control form-control-sm precio" step="0.01" min="0" value="${precio}" required></td>
            <td class="text-end subtotal fw-semibold">S/ 0.00</td>
            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger btn-eliminar"><i class="bi bi-trash"></i></button></td>
        `;
        body.appendChild(tr);
        recalcular();
    }

    function actualizarAjuste() {
        const totalOrig = parseFloat(document.getElementById('totalOriginalDisplay').value) || 0;
        const totalCob  = parseFloat(document.getElementById('totalACobrar').value);
        const ajuste = isNaN(totalCob) ? 0 : Math.round((totalCob - totalOrig) * 100) / 100;
        const el = document.getElementById('ajusteDisplay');
        if (Math.abs(ajuste) < 0.005) {
            el.innerHTML = '<span class="text-muted">Sin ajuste</span>';
        } else if (ajuste < 0) {
            el.innerHTML = '<span class="text-danger fw-semibold">S/ ' + ajuste.toFixed(2) + '</span>';
        } else {
            el.innerHTML = '<span class="text-success fw-semibold">+S/ ' + ajuste.toFixed(2) + '</span>';
        }
    }

    function recalcular() {
        let totalConIgv = 0;
        const factor = 1 + igvFactor();

        body.querySelectorAll('tr').forEach(tr => {
            const c = parseFloat(tr.querySelector('.cantidad').value) || 0;
            const p = parseFloat(tr.querySelector('.precio').value) || 0;
            const sub = c * p;
            tr.querySelector('.subtotal').textContent = 'S/ ' + sub.toFixed(2);
            totalConIgv += sub;
        });

        let baseGravada, igvMonto;
        if (igvIncluido) {
            baseGravada = totalConIgv / factor;
            igvMonto    = totalConIgv - baseGravada;
        } else {
            baseGravada = totalConIgv;
            igvMonto    = totalConIgv * igvFactor();
            totalConIgv = baseGravada + igvMonto;
        }

        totalEl.textContent = 'S/ ' + totalConIgv.toFixed(2);

        const sinIgvEl = document.getElementById('subtotalSinIgv');
        const igvRow   = document.getElementById('igvRow');
        const igvMontoEl = document.getElementById('igvMonto');
        sinIgvEl.textContent = 'S/ ' + baseGravada.toFixed(2);
        igvMontoEl.textContent = 'S/ ' + igvMonto.toFixed(2);

        document.getElementById('totalOriginalDisplay').value = totalConIgv.toFixed(2);
        document.getElementById('totalACobrar').value = totalConIgv.toFixed(2);
        actualizarAjuste();
    }

    function actualizarIgvUI() {
        const sinIgvEl = document.getElementById('subtotalSinIgv');
        const igvRow   = document.getElementById('igvRow');
        document.getElementById('igvPctLabel').textContent = igvPct;
        document.getElementById('igvPorcentajeHidden').value = igvPct;
        document.getElementById('igvIncluidoHidden').value   = igvIncluido ? '1' : '0';

        if (igvIncluido || !igvIncluido) {
            sinIgvEl.closest('tr').style.display = '';
            igvRow.style.display = '';
        }

        const label = document.getElementById('igvSwitchLabel');
        const explica = document.getElementById('igvExplica');
        if (igvIncluido) {
            label.innerHTML = 'Los precios <strong>incluyen</strong> IGV (' + igvPct + '%)';
            explica.textContent = 'El precio ingresado ya contiene el IGV. Se desglosará al emitir.';
        } else {
            label.innerHTML = 'Los precios <strong>no incluyen</strong> IGV (' + igvPct + '%)';
            explica.textContent = 'Al emitir se agregará el IGV al precio base ingresado.';
        }
        recalcular();
    }

    // IGV Switch
    document.getElementById('igvSwitch').addEventListener('change', function() {
        igvIncluido = this.checked;
        actualizarIgvUI();
    });
    document.getElementById('igvTasa').addEventListener('input', function() {
        igvPct = parseFloat(this.value) || 18;
        actualizarIgvUI();
    });

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
    document.getElementById('totalACobrar').addEventListener('input', actualizarAjuste);

    nuevaFila();
    actualizarIgvUI();

    // ── Autocompletado de cliente ──────────────────────────────────
    const _clientesData = @json($clientes->map(fn($c) => [
        'id'    => $c->id,
        'label' => $c->nombre . ($c->documento ? ' (' . $c->documento . ')' : ''),
    ])->values());
    const clienteMap = {};
    _clientesData.forEach(c => { clienteMap[c.label] = c.id; });

    const searchEl  = document.getElementById('clienteSearch');
    const hiddenEl  = document.getElementById('clienteIdHidden');

    searchEl.addEventListener('input', function () {
        const val = this.value.trim();
        hiddenEl.value = clienteMap[val] !== undefined ? clienteMap[val] : '';
    });
    searchEl.addEventListener('blur', function () {
        if (!this.value.trim()) hiddenEl.value = '';
    });
})();

// Vista previa del número de documento según la serie de la caja
(function () {
    const tipoSel  = document.getElementById('docTipo');
    const numPrev  = document.getElementById('docNumero');
    if (!tipoSel || !numPrev) return;

    function actualizarPreview() {
        const opt = tipoSel.options[tipoSel.selectedIndex];
        const preview = opt?.dataset?.preview || '';
        numPrev.placeholder = preview ? 'Siguiente: ' + preview : 'Se genera automáticamente';
    }

    tipoSel.addEventListener('change', actualizarPreview);
    actualizarPreview();
})();

// ── Aviso auto-emisión Nubefact ────────────────────────────────────
(function () {
    const tipoSel = document.getElementById('docTipo');
    const aviso   = document.getElementById('avisoNubefact');
    if (!tipoSel || !aviso) return;

    function toggleAviso() {
        const opt = tipoSel.options[tipoSel.selectedIndex];
        if (opt?.dataset?.electronico === '1') {
            aviso.style.removeProperty('display');
        } else {
            aviso.style.display = 'none';
        }
    }

    tipoSel.addEventListener('change', toggleAviso);
    toggleAviso();
})();
</script>
@endsection
