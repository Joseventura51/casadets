@extends('layouts.app')

@section('content')
@php
    $metodos = ['ninguno','efectivo','tarjeta','yape','plin','transferencia'];
    $metodoLabels = ['ninguno'=>'Ninguno (pendiente)','efectivo'=>'Efectivo','tarjeta'=>'Tarjeta','yape'=>'Yape','plin'=>'Plin','transferencia'=>'Transferencia'];

    // Helper: normalizar texto (igual que el controller) para buscar en $vendedoresMap
    $normVendedor = fn(string $s) => str_replace(
        ['á','é','í','ó','ú','ü','ñ','à','è','ì','ò','ù'],
        ['a','e','i','o','u','u','n','a','e','i','o','u'],
        mb_strtolower(trim($s), 'UTF-8')
    );

    // También hacer un mapa por sub-string para nombres parciales (ej "ALM PIMPOM" puede coincidir con "ALMACEN PIMPOM")
    $resolverVendedorId = function(string $nombre) use ($vendedoresMap, $vendedores, $vendedor_id_default, $normVendedor): array {
        if ($nombre === '') return ['id' => $vendedor_id_default, 'matched' => false, 'from_excel' => false];
        $norm = $normVendedor($nombre);
        // Exact match
        if (isset($vendedoresMap[$norm])) return ['id' => $vendedoresMap[$norm], 'matched' => true, 'from_excel' => true];
        // Partial match: alguno empieza o contiene el texto
        foreach ($vendedoresMap as $key => $id) {
            if (str_contains($key, $norm) || str_contains($norm, $key)) {
                return ['id' => $id, 'matched' => true, 'from_excel' => true];
        }
        }
        return ['id' => $vendedor_id_default, 'matched' => false, 'from_excel' => true];
    };
@endphp

<style>
.venta-card { border-radius: 10px; overflow: hidden; }
.venta-card .card-header { border-bottom: 1px solid rgba(0,0,0,.08); }
.pago-row { background: #f8f9fa; border-radius: 8px; padding: .5rem .75rem; margin-bottom: .4rem; display: flex; gap: .5rem; align-items: center; }
.pago-row select, .pago-row input { border-radius: 6px; }
.btn-add-pago { border: 1.5px dashed #0d6efd; border-radius: 8px; font-size: .8rem; padding: .25rem .75rem; color: #0d6efd; background: transparent; }
.btn-add-pago:hover { background: #e8f0fe; }
.total-cobrado-display { font-size: 1.15rem; font-weight: 700; color: #0d6efd; }
.diferencia-pill { font-size: .8rem; padding: .2rem .55rem; border-radius: 20px; display: inline-block; }
.doc-badge { font-size: .78rem; padding: .25rem .55rem; border-radius: 20px; letter-spacing: .02em; }
.venta-num { font-size: .8rem; color: #6c757d; font-weight: 500; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0"><i class="bi bi-eye me-2 text-primary"></i>Vista previa de importación</h3>
        <p class="text-muted mb-0 small">
            <span id="contadorVentas" class="fw-semibold text-dark">{{ count($grupos) }}</span> venta(s) detectadas.
            Revisa y confirma antes de guardar.
        </p>
    </div>
    <a href="/casadets/ventas/import" class="btn btn-outline-secondary btn-sm">← Cancelar</a>
</div>

@if($errors->any())
    <div class="alert alert-danger mb-3">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

@if(!empty($omitidos ?? []))
    <div class="alert alert-warning mb-3 d-flex gap-2 align-items-start">
        <i class="bi bi-skip-forward-fill fs-5 mt-1 text-warning"></i>
        <div>
            <strong>{{ count($omitidos) }} documento(s) omitidos por ya existir en el sistema:</strong>
            <div class="mt-1 d-flex flex-wrap gap-1">
                @foreach($omitidos as $dup)
                    <span class="badge bg-warning text-dark" style="font-size:.78rem;">{{ $dup }}</span>
                @endforeach
            </div>
            <div class="mt-1 text-muted small">Solo se muestran abajo los documentos nuevos.</div>
        </div>
    </div>
@endif

<div class="alert alert-light border small mb-3 py-2">
    <i class="bi bi-info-circle text-primary me-1"></i>
    Selecciona el <strong>método de pago</strong> y el <strong>monto cobrado</strong> por cada venta.
    Si aún no se cobró, deja <strong>Ninguno</strong> y quedará como <em>pendiente</em>.
</div>
@php
    $cntCanjeadas    = count(array_filter($grupos, fn($g) => !empty($g['canjeada'])));
    $cntNC           = count(array_filter($grupos, fn($g) => strtoupper($g['doc'] ?? '') === 'NC'));
    $cntConFactura   = count(array_filter($grupos, fn($g) => !empty($g['reemplazada_por']) && empty($g['canjeada'])));
@endphp
@if($cntCanjeadas > 0 || $cntNC > 0 || $cntConFactura > 0)
<div class="alert alert-info border small mb-3 py-2">
    <i class="bi bi-info-circle-fill text-info me-1"></i>
    <strong>Este archivo contiene documentos especiales:</strong>
    <ul class="mb-0 mt-1">
        @if($cntCanjeadas > 0)
        <li>
            <span class="badge bg-secondary me-1">{{ $cntCanjeadas }} Factura(s)/Boleta(s) de canje</span>
            Se importarán como <strong>referencia fiscal</strong> — sin generar deuda adicional ni mover stock.
            La cobranza se gestiona sobre las <strong>proformas</strong> que cubren.
        </li>
        @endif
        @if($cntConFactura > 0)
        <li>
            <span class="badge bg-warning text-dark me-1">{{ $cntConFactura }} Proforma(s) con factura emitida</span>
            Se importarán como <strong>pendientes de cobro</strong> — son el documento principal de cobranza.
            Se muestra qué factura/boleta fue emitida, solo como referencia.
        </li>
        @endif
        @if($cntNC > 0)
        <li>
            <span class="badge bg-danger me-1">{{ $cntNC }} Nota(s) de crédito</span>
            Se importarán con total <strong>negativo</strong> y estado <strong>pagado</strong>. El stock se ajustará como entrada (devolución).
        </li>
        @endif
    </ul>
</div>
@endif

{{-- Un solo campo JSON: evita el límite de max_input_vars de PHP --}}
<form action="/casadets/ventas/import/confirm" method="POST" id="formImport">
    @csrf
    <input type="hidden" name="ventas_json" id="ventasJson">

    <div class="d-flex justify-content-end mb-3">
        <button type="submit" class="btn btn-success px-4">
            <i class="bi bi-check-lg me-1"></i> Confirmar e importar todo
        </button>
    </div>

    <div id="ventasContainer">
        @foreach($grupos as $i => $g)
        @php
            $numFmt          = trim(($g['serie'] ?? '') . '-' . ($g['numero'] ?? ''), '-');
            $docLetra        = strtoupper($g['doc'] ?? '');
            $esNC            = $docLetra === 'NC';
            $esCanjeada      = !empty($g['canjeada']);
            $reemplazadaPor  = $g['reemplazada_por'] ?? [];
            $tieneFactura    = !empty($reemplazadaPor) && !$esCanjeada;
            $badgeCls = match($docLetra) {
                'B'  => 'bg-secondary',
                'F'  => $esCanjeada ? 'bg-secondary' : 'bg-primary',
                'NC' => 'bg-danger',
                default => 'bg-warning text-dark',
            };
            $cardBorder = $esNC ? 'border-danger' : ($esCanjeada ? 'border-secondary' : 'border-0');
            $cardBg     = $esNC ? 'bg-danger bg-opacity-10' : ($esCanjeada ? 'bg-secondary bg-opacity-10' : 'bg-white');

            $vNombre    = $g['vendedor_nombre'] ?? '';
            $vResuelto  = $resolverVendedorId($vNombre);
            $vId        = $vResuelto['id'];
            $vMatched   = $vResuelto['matched'];
            $vFromExcel = $vResuelto['from_excel'];
        @endphp
        <div class="card mb-2 venta-card shadow-sm {{ $cardBorder }}"
             data-idx="{{ $i }}"
             data-vendedor-id="{{ $vId }}"
             data-total-real="{{ $g['total'] }}"
             data-es-canjeada="{{ $esCanjeada ? '1' : '0' }}">

            {{-- HEADER --}}
            <div class="card-header {{ $cardBg }} d-flex justify-content-between align-items-center py-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="venta-num">#{{ $i + 1 }}</span>
                    <span class="text-dark fw-semibold">{{ \Carbon\Carbon::parse($g['fecha'])->format('d/m/Y') }}</span>
                    @if($numFmt)
                        <span class="badge {{ $badgeCls }} doc-badge">{{ $numFmt }}</span>
                    @endif
                    @if($esNC)
                        <span class="badge bg-danger" style="font-size:.75rem;">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Nota de Crédito
                        </span>
                    @endif
                    @if($esCanjeada)
                        {{-- Factura/boleta que cubre proformas: referencia fiscal --}}
                        <span class="badge bg-secondary" style="font-size:.75rem;">
                            <i class="bi bi-file-earmark-check me-1"></i>Ref. fiscal (canje)
                        </span>
                        @if(!empty($g['canjes']))
                            <span class="text-muted small">Cubre: {{ implode(', ', $g['canjes']) }}</span>
                        @endif
                    @elseif($tieneFactura)
                        {{-- Proforma con factura emitida: sigue siendo el doc de cobro --}}
                        <span class="badge bg-warning text-dark" style="font-size:.75rem;">
                            <i class="bi bi-receipt me-1"></i>Proforma (cobro pendiente)
                        </span>
                        <span class="text-muted small">Factura emitida: {{ implode(', ', $reemplazadaPor) }}</span>
                    @elseif(!$esNC && in_array($docLetra, ['PR', 'P']))
                        <span class="badge bg-warning text-dark" style="font-size:.75rem;">
                            <i class="bi bi-receipt me-1"></i>Proforma
                        </span>
                    @endif
                    @if(!empty($g['razon_social']))
                        <span class="text-dark" style="font-size:.9rem;">
                            <i class="bi bi-building me-1 text-secondary"></i>{{ $g['razon_social'] }}
                        </span>
                    @endif
                    <span class="text-muted small">
                        {{ $g['detalles'] ? count($g['detalles']).' producto(s)' : '' }}
                        · S/ {{ number_format(abs($g['total']), 2) }}
                        @if($esNC) <span class="text-danger fw-semibold">(crédito)</span> @endif
                        @if($esCanjeada) <span class="text-muted fst-italic">(sin stock · sin deuda)</span> @endif
                    </span>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-venta">
                    <i class="bi bi-trash"></i>
                </button>
            </div>

            <div class="card-body py-3">

                {{-- Tabla de productos con código editable --}}
                <div class="mb-3">
                    <details open>
                        <summary class="small fw-semibold text-secondary mb-2" style="cursor:pointer;list-style:none;">
                            <i class="bi bi-box-seam me-1"></i>Productos
                            <span class="badge bg-light text-secondary border ms-1" style="font-size:.72rem;">{{ count($g['detalles']) }}</span>
                        </summary>
                        <div class="table-responsive mt-2">
                            <table class="table table-sm table-bordered mb-0 detalles-table" style="font-size:.82rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th>Producto</th>
                                        <th style="width:120px;">Código</th>
                                        <th class="text-end" style="width:70px;">Cant.</th>
                                        <th class="text-end" style="width:80px;">P.Unit</th>
                                        <th class="text-end" style="width:85px;">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($g['detalles'] as $di => $d)
                                    <tr
                                        data-producto="{{ $d['producto'] }}"
                                        data-cantidad="{{ $d['cantidad'] }}"
                                        data-precio="{{ $d['precio_unitario'] }}"
                                        data-subtotal="{{ $d['subtotal'] }}">
                                        <td class="det-producto">{{ $d['producto'] }}</td>
                                        <td>
                                            <input type="text"
                                                class="form-control form-control-sm det-codigo"
                                                value="{{ $d['codigo'] ?? '' }}"
                                                placeholder="—"
                                                maxlength="100">
                                        </td>
                                        <td class="text-end text-muted">{{ $d['cantidad'] }}</td>
                                        <td class="text-end text-muted">{{ $d['precio_unitario'] }}</td>
                                        <td class="text-end fw-semibold">{{ number_format($d['subtotal'], 2) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                </div>

                {{-- VENDEDOR --}}
                <div class="mb-3 d-flex align-items-center gap-2 flex-wrap">
                    <label class="form-label small fw-semibold mb-0 text-nowrap">
                        <i class="bi bi-person-badge me-1 text-secondary"></i>Vendedor
                    </label>
                    <div style="min-width:180px;max-width:260px;">
                        <select class="form-select form-select-sm vendedor-sel">
                            @foreach($vendedores as $vv)
                                <option value="{{ $vv->id }}" {{ $vv->id == $vId ? 'selected' : '' }}>
                                    {{ $vv->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @if($vFromExcel && $vNombre !== '')
                        @if($vMatched)
                            <span class="badge bg-success bg-opacity-75" title="Detectado automáticamente desde el Excel">
                                <i class="bi bi-check-lg me-1"></i>Excel: {{ $vNombre }}
                            </span>
                        @else
                            <span class="badge bg-warning text-dark" title="El nombre del Excel no coincidió con ningún vendedor registrado. Se asignó el vendedor por defecto.">
                                <i class="bi bi-exclamation-triangle me-1"></i>Excel: "{{ $vNombre }}" — sin coincidencia
                            </span>
                        @endif
                    @endif
                </div>

                <div class="row g-3 align-items-end">
                    {{-- MÉTODOS DE PAGO: solo para ventas normales --}}
                    @if(!$esCanjeada && !$esNC)
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold mb-1">
                            <i class="bi bi-credit-card me-1"></i>Método de pago
                        </label>
                        <div class="pagos-container">
                            <div class="pago-row">
                                <select class="form-select form-select-sm metodo-sel" style="flex:1;">
                                    @foreach($metodos as $m)
                                        <option value="{{ $m }}" {{ $m == $metodo_pago_default ? 'selected' : '' }}>
                                            {{ $metodoLabels[$m] ?? ucfirst($m) }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="input-group input-group-sm" style="width:120px;">
                                    <span class="input-group-text py-0 px-1 bg-white border-end-0 small text-muted">S/</span>
                                    <input type="number" value="0" step="0.01" min="0"
                                        class="form-control form-control-sm text-end monto-pago border-start-0">
                                </div>
                                <button type="button" class="btn btn-sm p-1 lh-1 text-danger border-0 bg-transparent btn-del-pago" style="font-size:1rem;">
                                    <i class="bi bi-x-circle-fill"></i>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="btn-add-pago w-100 mt-1 btn-agregar-pago">
                            <i class="bi bi-plus-lg me-1"></i>Agregar método
                        </button>
                    </div>
                    @else
                    <div class="col-md-6">
                        <div class="p-3 rounded text-center text-muted small"
                             style="border: 1.5px dashed #dee2e6; background: #fafafa;">
                            @if($esCanjeada)
                                <i class="bi bi-arrow-left-right me-1"></i>
                                Proforma canjeada — sin pago ni stock adicional
                            @else
                                <i class="bi bi-arrow-counterclockwise me-1"></i>
                                Nota de crédito — se registra automáticamente como abono
                            @endif
                        </div>
                    </div>
                    @endif

                    {{-- TOTALES --}}
                    <div class="col-md-6">
                        <div class="row g-2 text-center">
                            <div class="col-4">
                                <div class="text-muted small">Total productos</div>
                                <div class="fw-semibold">S/ {{ number_format($g['total'], 2) }}</div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted small">Total cobrado</div>
                                <div class="total-cobrado-display">S/ 0.00</div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted small">Diferencia</div>
                                <div class="diferencia-pill bg-light text-muted w-100">—</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        @endforeach
    </div>

    <div id="sinVentas" class="alert alert-secondary text-center" style="display:none;">
        No quedan ventas para importar. <a href="/casadets/ventas/import">Volver</a>.
    </div>

    <div class="d-flex justify-content-between align-items-center mt-3 pb-4">
        <a href="/casadets/ventas/import" class="btn btn-outline-secondary">← Volver</a>
        <button type="submit" class="btn btn-success px-4">
            <i class="bi bi-check-lg me-1"></i> Confirmar e importar todo
        </button>
    </div>
</form>

<script>
const METODOS       = @json($metodos);
const METODO_LABELS = @json($metodoLabels);

function opcionesSelect(metodoSel) {
    return METODOS.map(m =>
        `<option value="${m}" ${m===metodoSel?'selected':''}>${METODO_LABELS[m]||m}</option>`
    ).join('');
}

function recalcPagos(card) {
    let totalCob = 0;
    card.querySelectorAll('.monto-pago').forEach(inp => totalCob += parseFloat(inp.value) || 0);
    const realRaw = parseFloat(card.dataset.totalReal) || 0;

    card.querySelector('.total-cobrado-display').textContent = 'S/ ' + totalCob.toFixed(2);

    const d = totalCob - realRaw;
    const pill = card.querySelector('.diferencia-pill');
    if (Math.abs(d) < 0.005) {
        pill.className = 'diferencia-pill bg-light text-muted w-100';
        pill.textContent = 'Exacto';
    } else if (d > 0) {
        pill.className = 'diferencia-pill bg-success text-white w-100';
        pill.textContent = '+S/ ' + d.toFixed(2);
    } else {
        pill.className = 'diferencia-pill bg-danger text-white w-100';
        pill.textContent = '-S/ ' + Math.abs(d).toFixed(2);
    }
}

function crearPagoRow() {
    const div = document.createElement('div');
    div.className = 'pago-row';
    div.innerHTML = `
        <select class="form-select form-select-sm metodo-sel" style="flex:1;">
            ${opcionesSelect('ninguno')}
        </select>
        <div class="input-group input-group-sm" style="width:120px;">
            <span class="input-group-text py-0 px-1 bg-white border-end-0 small text-muted">S/</span>
            <input type="number" value="0" step="0.01" min="0"
                class="form-control form-control-sm text-end monto-pago border-start-0">
        </div>
        <button type="button" class="btn btn-sm p-1 lh-1 text-danger border-0 bg-transparent btn-del-pago" style="font-size:1rem;">
            <i class="bi bi-x-circle-fill"></i>
        </button>`;
    return div;
}

// Serializa todos los datos visibles en un único JSON
function serializarTodo() {
    const ventas = [];
    document.querySelectorAll('.venta-card').forEach(card => {
        const idx        = parseInt(card.dataset.idx);
        const vendedorSel = card.querySelector('.vendedor-sel');
        const vendedorId  = vendedorSel ? vendedorSel.value : card.dataset.vendedorId;

        // Detalles desde la tabla
        const detalles = [];
        card.querySelectorAll('.detalles-table tbody tr').forEach(tr => {
            detalles.push({
                producto:        tr.dataset.producto,
                codigo:          tr.querySelector('.det-codigo')?.value.trim() ?? '',
                cantidad:        parseFloat(tr.dataset.cantidad) || 0,
                precio_unitario: parseFloat(tr.dataset.precio) || 0,
                subtotal:        parseFloat(tr.dataset.subtotal) || 0,
            });
        });

        // Pagos desde los inputs visibles
        const pagos = [];
        card.querySelectorAll('.pago-row').forEach(row => {
            pagos.push({
                metodo: row.querySelector('.metodo-sel').value,
                monto:  parseFloat(row.querySelector('.monto-pago').value) || 0,
            });
        });

        const totalCobrado = pagos.reduce((s, p) => s + p.monto, 0);

        ventas.push({ session_idx: idx, vendedor_id: vendedorId, detalles, pagos, total_cobrado: totalCobrado });
    });
    return ventas;
}

document.getElementById('formImport').addEventListener('submit', function(e) {
    const ventas = serializarTodo();
    if (ventas.length === 0) { e.preventDefault(); alert('No hay ventas para importar.'); return; }
    document.getElementById('ventasJson').value = JSON.stringify(ventas);
});

document.querySelectorAll('.venta-card').forEach(card => {
    recalcPagos(card);

    card.addEventListener('input', e => {
        if (e.target.matches('.monto-pago')) recalcPagos(card);
    });

    card.addEventListener('click', e => {
        const btnD = e.target.closest('.btn-del-pago');
        if (btnD) {
            const container = card.querySelector('.pagos-container');
            if (container.querySelectorAll('.pago-row').length <= 1) {
                alert('Debe quedar al menos un método de pago.'); return;
            }
            btnD.closest('.pago-row').remove();
            recalcPagos(card);
        }
    });

    card.querySelector('.btn-agregar-pago').addEventListener('click', () => {
        const container = card.querySelector('.pagos-container');
        const row = crearPagoRow();
        container.appendChild(row);
        row.querySelector('.monto-pago').focus();
        recalcPagos(card);
    });

    card.querySelector('.btn-eliminar-venta').addEventListener('click', () => {
        if (confirm('¿Eliminar esta venta? No se importará.')) {
            card.remove();
            actualizarContador();
        }
    });
});

function actualizarContador() {
    const n = document.querySelectorAll('.venta-card').length;
    document.getElementById('contadorVentas').textContent = n;
    document.getElementById('sinVentas').style.display = n === 0 ? '' : 'none';
}
</script>
@endsection
