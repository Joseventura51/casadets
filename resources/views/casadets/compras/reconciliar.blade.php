@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">Reconciliar vale supuesto #{{ $compra->id }}</h3>
        <p class="text-muted mb-0 small">Ingresa los precios reales de la factura del proveedor.</p>
    </div>
    <a href="/casadets/compras/{{ $compra->id }}" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<div class="alert d-flex align-items-start gap-2 mb-3 py-2" style="background:#fffbeb;border:1px solid #fde68a;">
    <i class="bi bi-info-circle-fill text-warning flex-shrink-0 mt-1"></i>
    <div>
        <strong>¿Cómo funciona?</strong> Ingresa el precio <em>real</em> que cobró el proveedor por cada producto. Se creará una compra real vinculada a este vale y la diferencia (real − estimado) se acumulará para el próximo cierre de utilidad.
        <br><small class="text-muted">No se generan movimientos de stock duplicados — los productos ya están en inventario desde el vale.</small>
    </div>
</div>

<form action="/casadets/compras/{{ $compra->id }}/reconciliar" method="POST">
    @csrf

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-receipt me-1"></i> Datos de la factura real</div>
        <div class="card-body">
            @if($errors->any())
            <div class="alert alert-danger mb-3">
                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
            @endif
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Empresa / Proveedor *</label>
                    <input type="text" name="empresa" value="{{ old('empresa', $compra->empresa) }}" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha factura *</label>
                    <input type="date" name="fecha" value="{{ old('fecha', date('Y-m-d')) }}" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tipo doc.</label>
                    <select name="documento_tipo" class="form-select">
                        <option value="">—</option>
                        @foreach(['boleta','factura','guia','recibo','otro'] as $t)
                            <option value="{{ $t }}" {{ old('documento_tipo') === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Nro. documento</label>
                    <input type="text" name="documento_numero" value="{{ old('documento_numero') }}" class="form-control" placeholder="F001-123">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Método de pago</label>
                    <select name="metodo_pago" class="form-select">
                        <option value="">— Sin especificar —</option>
                        <option value="efectivo"      {{ old('metodo_pago') === 'efectivo'      ? 'selected' : '' }}>Efectivo</option>
                        <option value="transferencia" {{ old('metodo_pago') === 'transferencia' ? 'selected' : '' }}>Transferencia</option>
                    </select>
                </div>
                <div class="col-md-9">
                    <label class="form-label">Observaciones</label>
                    <input type="text" name="observaciones" value="{{ old('observaciones') }}" class="form-control" placeholder="Opcional">
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-list-ul me-1"></i> Precios por producto
            <small class="text-muted fw-normal ms-2">— ingresa el precio real cobrado por el proveedor</small>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Producto / Descripción</th>
                        <th class="text-end" style="width:100px;">Cantidad</th>
                        <th class="text-end" style="width:115px;">P. estimado</th>
                        <th class="text-end" style="width:125px;">P. real *</th>
                        <th class="text-end" style="width:115px;">Total real</th>
                        <th class="text-end" style="width:115px;">Diferencia</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($compra->lineas as $linea)
                    <tr>
                        <td>{{ $linea->producto ?? '—' }}</td>
                        <td class="text-end text-muted">
                            {{ rtrim(rtrim(number_format($linea->cantidad, 2), '0'), '.') }}
                        </td>
                        <td class="text-end text-muted">
                            S/ {{ number_format($linea->monto_unitario, 2) }}
                        </td>
                        <td class="text-end">
                            <input type="number"
                                name="precios_reales[{{ $linea->id }}]"
                                value="{{ old("precios_reales.{$linea->id}", number_format($linea->monto_unitario, 2, '.', '')) }}"
                                step="0.01" min="0"
                                class="form-control form-control-sm text-end precio-real"
                                data-cantidad="{{ $linea->cantidad }}"
                                data-estimado="{{ $linea->monto_unitario }}"
                                data-total-estimado="{{ $linea->monto_total }}"
                                required>
                        </td>
                        <td class="text-end fw-semibold total-real-cell">
                            S/ {{ number_format($linea->monto_total, 2) }}
                        </td>
                        <td class="text-end diferencia-cell fw-semibold">
                            S/ 0.00
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="2" class="text-end">Totales</th>
                        <th class="text-end text-muted" id="totalEstimadoFoot">S/ {{ number_format($compra->monto_total, 2) }}</th>
                        <th></th>
                        <th class="text-end text-primary" id="totalRealFoot">S/ {{ number_format($compra->monto_total, 2) }}</th>
                        <th class="text-end fw-bold" id="totalDiferenciaFoot">S/ 0.00</th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="p-3">
            <div class="alert mb-0 py-2 d-flex align-items-center gap-2" id="diferenciaBanner" style="background:#f8fafc;border:1px solid #e2e8f0;">
                <i class="bi bi-info-circle text-muted"></i>
                <span id="diferenciaMsg" class="small text-muted">Ajusta los precios reales para ver la diferencia.</span>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <a href="/casadets/compras/{{ $compra->id }}" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-warning px-4 fw-semibold">
            <i class="bi bi-arrow-left-right me-1"></i> Confirmar reconciliación
        </button>
    </div>
</form>

<script>
(function () {
    const fmt = n => 'S/ ' + parseFloat(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    function recalcular() {
        let totalReal = 0;
        let totalEst  = 0;

        document.querySelectorAll('.precio-real').forEach(inp => {
            const row      = inp.closest('tr');
            const cant     = parseFloat(inp.dataset.cantidad) || 0;
            const est      = parseFloat(inp.dataset.totalEstimado) || 0;
            const precioR  = parseFloat(inp.value) || 0;
            const totReal  = cant * precioR;
            const diff     = totReal - est;

            totalReal += totReal;
            totalEst  += est;

            row.querySelector('.total-real-cell').textContent = fmt(totReal);

            const diffCell = row.querySelector('.diferencia-cell');
            diffCell.textContent = (diff >= 0 ? '+' : '') + fmt(diff);
            diffCell.className = 'text-end diferencia-cell fw-semibold '
                + (diff > 0.001 ? 'text-danger' : diff < -0.001 ? 'text-success' : 'text-muted');
        });

        const difTotal = totalReal - totalEst;

        document.getElementById('totalRealFoot').textContent = fmt(totalReal);
        document.getElementById('totalDiferenciaFoot').textContent =
            (difTotal >= 0 ? '+' : '') + fmt(difTotal);
        document.getElementById('totalDiferenciaFoot').className =
            'text-end fw-bold ' + (difTotal > 0.001 ? 'text-danger' : difTotal < -0.001 ? 'text-success' : 'text-muted');

        const banner  = document.getElementById('diferenciaBanner');
        const msg     = document.getElementById('diferenciaMsg');
        if (Math.abs(difTotal) < 0.01) {
            banner.style.cssText = 'background:#f0fdf4;border:1px solid #bbf7d0;';
            msg.innerHTML = '<strong>Sin diferencia.</strong> Los precios reales coinciden con el estimado. No habrá ajuste en el cierre.';
            msg.className = 'small text-success';
        } else if (difTotal > 0) {
            banner.style.cssText = 'background:#fff1f2;border:1px solid #fecdd3;';
            msg.innerHTML = `<strong>El proveedor cobró S/ ${Math.abs(difTotal).toFixed(2)} más de lo estimado.</strong> La diferencia reducirá la utilidad en el próximo cierre de semana.`;
            msg.className = 'small text-danger';
        } else {
            banner.style.cssText = 'background:#f0fdf4;border:1px solid #bbf7d0;';
            msg.innerHTML = `<strong>El proveedor cobró S/ ${Math.abs(difTotal).toFixed(2)} menos de lo estimado.</strong> La diferencia aumentará la utilidad en el próximo cierre de semana.`;
            msg.className = 'small text-success';
        }
    }

    document.querySelectorAll('.precio-real').forEach(inp => {
        inp.addEventListener('input', recalcular);
    });

    recalcular();
})();
</script>
@endsection
