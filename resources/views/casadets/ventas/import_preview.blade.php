@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">Vista previa de importación</h3>
        <p class="text-muted mb-0">Revisa y edita las ventas antes de guardar. Detectadas: <strong>{{ count($grupos) }}</strong> venta(s).</p>
    </div>
    <a href="/casadets/ventas/import" class="btn btn-outline-secondary btn-sm">← Cancelar</a>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="alert alert-warning small">
    <i class="bi bi-pencil-square"></i>
    Puedes <strong>cambiar el vendedor</strong> de cada venta y <strong>ajustar el total cobrado</strong> si el cliente pagó un monto distinto al del comprobante (ej: redondeo, descuento manual). Los productos del comprobante quedan tal cual.
</div>

<form action="/casadets/ventas/import/confirm" method="POST">
    @csrf
    <input type="hidden" name="metodo_pago" value="{{ $metodo_pago }}">

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span><strong>Método de pago para todas:</strong> {{ ucfirst($metodo_pago) }}</span>
            <button type="submit" class="btn btn-success">
                <i class="bi bi-check-lg"></i> Confirmar e importar todo
            </button>
        </div>

        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width:90px;">Fecha</th>
                        <th style="width:130px;">Documento</th>
                        <th>Productos</th>
                        <th style="width:170px;">Vendedor</th>
                        <th style="width:120px;" class="text-end">Total real</th>
                        <th style="width:130px;" class="text-end">Total cobrado</th>
                        <th style="width:110px;" class="text-end">Diferencia</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($grupos as $i => $g)
                    <tr data-idx="{{ $i }}">
                        <td>
                            {{ \Carbon\Carbon::parse($g['fecha'])->format('d/m/Y') }}
                            <input type="hidden" name="ventas[{{ $i }}][fecha]" value="{{ $g['fecha'] }}">
                        </td>
                        <td>
                            @if($g['doc'])
                                <span class="badge {{ strtoupper($g['doc']) == 'B' ? 'bg-secondary' : 'bg-primary' }}">
                                    {{ strtoupper($g['doc']) == 'B' ? 'Boleta' : (strtoupper($g['doc']) == 'F' ? 'Factura' : $g['doc']) }}
                                </span><br>
                                <small>{{ $g['serie'] }}-{{ $g['numero'] }}</small>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                            <input type="hidden" name="ventas[{{ $i }}][doc]" value="{{ $g['doc'] }}">
                            <input type="hidden" name="ventas[{{ $i }}][serie]" value="{{ $g['serie'] }}">
                            <input type="hidden" name="ventas[{{ $i }}][numero]" value="{{ $g['numero'] }}">
                        </td>
                        <td>
                            <small>
                                @foreach($g['detalles'] as $j => $d)
                                    <div>• {{ $d['producto'] }} <span class="text-muted">({{ rtrim(rtrim(number_format($d['cantidad'], 2), '0'), '.') }} × S/ {{ number_format($d['precio_unitario'], 2) }})</span></div>
                                    <input type="hidden" name="ventas[{{ $i }}][detalles][{{ $j }}][producto]" value="{{ $d['producto'] }}">
                                    <input type="hidden" name="ventas[{{ $i }}][detalles][{{ $j }}][cantidad]" value="{{ $d['cantidad'] }}">
                                    <input type="hidden" name="ventas[{{ $i }}][detalles][{{ $j }}][precio_unitario]" value="{{ $d['precio_unitario'] }}">
                                    <input type="hidden" name="ventas[{{ $i }}][detalles][{{ $j }}][subtotal]" value="{{ $d['subtotal'] }}">
                                @endforeach
                            </small>
                        </td>
                        <td>
                            <select name="ventas[{{ $i }}][vendedor_id]" class="form-select form-select-sm" required>
                                @foreach($vendedores as $v)
                                    <option value="{{ $v->id }}" {{ $v->id == $vendedor_id_default ? 'selected' : '' }}>{{ $v->nombre }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="text-end">
                            <span class="text-muted total-real" data-total="{{ $g['total'] }}">S/ {{ number_format($g['total'], 2) }}</span>
                        </td>
                        <td>
                            <input type="number" name="ventas[{{ $i }}][total_cobrado]"
                                value="{{ number_format($g['total'], 2, '.', '') }}"
                                step="0.01" min="0"
                                class="form-control form-control-sm text-end total-cobrado" required>
                        </td>
                        <td class="text-end">
                            <span class="diferencia fw-semibold">S/ 0.00</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <a href="/casadets/ventas/import" class="btn btn-outline-secondary">← Volver</a>
            <button type="submit" class="btn btn-success">
                <i class="bi bi-check-lg"></i> Confirmar e importar todo
            </button>
        </div>
    </div>
</form>

<script>
document.querySelectorAll('tr[data-idx]').forEach(tr => {
    const real = parseFloat(tr.querySelector('.total-real').dataset.total) || 0;
    const cob = tr.querySelector('.total-cobrado');
    const dif = tr.querySelector('.diferencia');

    function actualizar() {
        const v = parseFloat(cob.value) || 0;
        const d = v - real;
        let txt = 'S/ ' + d.toFixed(2);
        if (d > 0) {
            dif.className = 'diferencia fw-semibold text-success';
            txt = '+' + txt;
        } else if (d < 0) {
            dif.className = 'diferencia fw-semibold text-danger';
        } else {
            dif.className = 'diferencia text-muted';
        }
        dif.textContent = txt;
    }
    cob.addEventListener('input', actualizar);
    actualizar();
});
</script>
@endsection
