@extends('layouts.app')

@section('content')
<div class="container-fluid px-3 py-3">

    {{-- Header --}}
    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
        <a href="/casadets/devoluciones" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h4 class="mb-0 fw-bold"><i class="bi bi-arrow-return-left me-2 text-danger"></i>Devolución / Anulación</h4>
        <span class="badge
            {{ $venta->estado === 'pagado' ? 'bg-success' : ($venta->estado === 'parcial' ? 'bg-warning text-dark' : ($venta->estado === 'anulado' ? 'bg-danger' : 'bg-secondary')) }}">
            {{ ucfirst($venta->estado) }}
        </span>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show py-2"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show py-2"><i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger py-2"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li class="small">{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-3">

        {{-- Columna izquierda: info del vale + historial --}}
        <div class="col-md-4">

            {{-- Info del vale --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-receipt me-1"></i>Información del Vale</div>
                <div class="card-body py-2" style="font-size:.88rem;">
                    <table class="table table-sm mb-0 border-0">
                        <tr>
                            <td class="text-muted border-0 py-1">Documento</td>
                            <td class="border-0 py-1 fw-semibold">
                                {{ ucfirst($venta->documento_tipo ?? 'Vale') }} {{ $venta->documento_numero ?? '#'.$venta->id }}
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted border-0 py-1">Fecha</td>
                            <td class="border-0 py-1">{{ $venta->fecha->format('d/m/Y') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted border-0 py-1">Cliente</td>
                            <td class="border-0 py-1">{{ $venta->cliente->nombre ?? '—' }}</td>
                        </tr>
                        @if($venta->vendedor)
                        <tr>
                            <td class="text-muted border-0 py-1">Vendedor</td>
                            <td class="border-0 py-1">{{ $venta->vendedor->nombre }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="text-muted border-0 py-1">Total original</td>
                            <td class="border-0 py-1">S/ {{ number_format($venta->total, 2) }}</td>
                        </tr>
                        @if((float)$venta->ajuste != 0)
                        <tr>
                            <td class="text-muted border-0 py-1">Ajuste</td>
                            <td class="border-0 py-1 {{ (float)$venta->ajuste < 0 ? 'text-danger' : 'text-success' }}">
                                S/ {{ number_format($venta->ajuste, 2) }}
                            </td>
                        </tr>
                        @endif
                        <tr>
                            <td class="text-muted border-0 py-1">Total a cobrar</td>
                            <td class="border-0 py-1 fw-bold">S/ {{ number_format($venta->total_a_cobrar, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted border-0 py-1">Pagado</td>
                            <td class="border-0 py-1 text-success fw-semibold">S/ {{ number_format($venta->pagado, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted border-0 py-1">Saldo pendiente</td>
                            <td class="border-0 py-1 fw-bold {{ $venta->saldo_pendiente > 0 ? 'text-danger' : 'text-success' }}">
                                S/ {{ number_format($venta->saldo_pendiente, 2) }}
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            {{-- Historial de devoluciones de este vale --}}
            @if($devoluciones->count() > 0)
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold" style="font-size:.85rem;">
                    <i class="bi bi-clock-history me-1"></i>Historial de devoluciones
                </div>
                <div class="card-body p-0">
                    @foreach($devoluciones as $dev)
                    <div class="px-3 py-2 border-bottom" style="font-size:.8rem;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge {{ $dev->tipo === 'total' ? 'bg-danger' : 'bg-warning text-dark' }} me-1">
                                    {{ $dev->tipo === 'total' ? 'Anulación' : 'Parcial' }}
                                </span>
                                {{ $dev->fecha->format('d/m/Y') }}
                                <span class="text-muted ms-1">{{ $dev->user->name ?? '' }}</span>
                            </div>
                            <strong class="text-danger">S/ {{ number_format($dev->monto_devuelto, 2) }}</strong>
                        </div>
                        @if($dev->motivo)
                            <div class="text-muted mt-1">{{ $dev->motivo }}</div>
                        @endif
                        @if($dev->detalles->count())
                        <div class="mt-1">
                            @foreach($dev->detalles as $dd)
                                <div class="text-muted" style="font-size:.75rem;">
                                    · {{ $dd->producto->nombre ?? 'Producto' }} —
                                    {{ rtrim(rtrim(number_format($dd->cantidad_devuelta, 2), '0'), '.') }} unid.
                                    × S/ {{ number_format($dd->precio_unitario, 2) }}
                                </div>
                            @endforeach
                        </div>
                        @endif
                        @if((float)$dev->saldo_generado > 0)
                            <div class="text-success mt-1" style="font-size:.75rem;">
                                <i class="bi bi-wallet2 me-1"></i>Saldo a favor generado: S/ {{ number_format($dev->saldo_generado, 2) }}
                            </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

        </div>

        {{-- Columna derecha: formulario --}}
        <div class="col-md-8">

            @if($venta->estado === 'anulado')
                <div class="alert alert-danger d-flex align-items-center gap-2">
                    <i class="bi bi-x-octagon-fill fs-4"></i>
                    <div>
                        <strong>Este vale está anulado.</strong>
                        Ya no se pueden registrar devoluciones sobre él.
                    </div>
                </div>
            @else

            {{-- Formulario de devolución parcial --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-box-arrow-left me-1 text-warning"></i>Registrar Devolución Parcial
                </div>
                <div class="card-body">
                    <form method="POST" action="/casadets/devoluciones/{{ $venta->id }}" id="formDevolucion">
                        @csrf

                        <div class="table-responsive mb-3">
                            <table class="table table-sm align-middle" id="tablaProductos">
                                <thead class="table-light">
                                    <tr>
                                        <th>Producto</th>
                                        <th class="text-end">Cant. vendida</th>
                                        <th class="text-end">Ya devuelto</th>
                                        <th class="text-end">Disponible</th>
                                        <th class="text-end">Precio unit.</th>
                                        <th style="min-width:110px;">Cant. a devolver</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($venta->detalles as $detalle)
                                    @php
                                        $cantVendida    = (float) $detalle->cantidad;
                                        $yaDevueltoItem = (float) ($yaDevuelto[$detalle->id] ?? 0);
                                        $disponible     = max(0, $cantVendida - $yaDevueltoItem);
                                    @endphp
                                    <tr class="{{ $disponible <= 0 ? 'table-secondary text-muted' : '' }}">
                                        <td>
                                            @php $nombreProducto = $detalle->getRawOriginal('producto') ?? $detalle->producto; @endphp
                                            <div class="fw-semibold small">{{ $nombreProducto }}</div>
                                            @if($detalle->producto_id)
                                                @php $prodModel = \App\Models\Producto::find($detalle->producto_id); @endphp
                                                @if($prodModel)
                                                <div class="text-muted" style="font-size:.7rem;">
                                                    Stock: {{ number_format($prodModel->stock_actual, 2) }}
                                                </div>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="text-end small">{{ rtrim(rtrim(number_format($cantVendida, 2), '0'), '.') }}</td>
                                        <td class="text-end small {{ $yaDevueltoItem > 0 ? 'text-warning fw-semibold' : 'text-muted' }}">
                                            {{ $yaDevueltoItem > 0 ? rtrim(rtrim(number_format($yaDevueltoItem, 2), '0'), '.') : '—' }}
                                        </td>
                                        <td class="text-end small {{ $disponible <= 0 ? 'text-danger' : 'fw-semibold text-success' }}">
                                            {{ rtrim(rtrim(number_format($disponible, 2), '0'), '.') }}
                                        </td>
                                        <td class="text-end small">S/ {{ number_format($detalle->precio_unitario, 2) }}</td>
                                        <td>
                                            @if($disponible > 0)
                                            <input type="number"
                                                   name="cantidades[{{ $detalle->id }}]"
                                                   class="form-control form-control-sm text-end cant-input"
                                                   min="0"
                                                   max="{{ $disponible }}"
                                                   step="0.01"
                                                   value="{{ old("cantidades.{$detalle->id}", 0) }}"
                                                   data-precio="{{ $detalle->precio_unitario }}"
                                                   data-max="{{ $disponible }}"
                                                   oninput="recalcTotal()">
                                            @else
                                                <span class="text-muted small">Agotado</span>
                                            @endif
                                        </td>
                                        <td class="text-end small fw-semibold subtotal-cell">S/ 0.00</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="table-light">
                                        <td colspan="6" class="text-end fw-bold">Total a devolver:</td>
                                        <td class="text-end fw-bold text-danger" id="totalDevolver">S/ 0.00</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Motivo (opcional)</label>
                                <input type="text"
                                       name="motivo"
                                       class="form-control form-control-sm"
                                       placeholder="Ej: Producto defectuoso, error de pedido..."
                                       value="{{ old('motivo') }}">
                            </div>
                        </div>

                        <div class="d-flex gap-2 align-items-center">
                            <button type="submit" class="btn btn-warning fw-semibold" id="btnDevolver" disabled>
                                <i class="bi bi-arrow-return-left me-1"></i>Registrar Devolución
                            </button>
                            <span class="text-muted small" id="resumenDevolucion"></span>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Anulación completa --}}
            <div class="card border-0 shadow-sm border-danger border-opacity-25">
                <div class="card-header bg-white fw-semibold text-danger">
                    <i class="bi bi-x-octagon me-1"></i>Anular Vale Completo
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Al anular el vale se devolverá <strong>todo el stock</strong> al inventario y los movimientos financieros quedarán marcados como anulados.
                        @if((float)$venta->pagado > 0)
                            Como este vale tiene <strong class="text-success">S/ {{ number_format($venta->pagado, 2) }} cobrado</strong>,
                            se generará un <strong>saldo a favor</strong> para el cliente.
                        @endif
                    </p>

                    <button type="button"
                            class="btn btn-outline-danger fw-semibold"
                            data-bs-toggle="modal"
                            data-bs-target="#modalAnular">
                        <i class="bi bi-x-octagon me-1"></i>Anular este Vale
                    </button>
                </div>
            </div>

            @endif {{-- fin if no anulado --}}
        </div>
    </div>
</div>

{{-- Modal de confirmación de anulación --}}
<div class="modal fade" id="modalAnular" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title text-danger"><i class="bi bi-x-octagon me-2"></i>Confirmar Anulación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/casadets/devoluciones/{{ $venta->id }}/anular">
                @csrf
                <div class="modal-body">
                    <p class="mb-2">¿Está seguro que desea anular el siguiente vale?</p>
                    <div class="bg-light rounded p-2 mb-3">
                        <strong>{{ ucfirst($venta->documento_tipo ?? 'Vale') }} {{ $venta->documento_numero ?? '#'.$venta->id }}</strong><br>
                        <span class="text-muted small">{{ $venta->cliente->nombre ?? '—' }} · {{ $venta->fecha->format('d/m/Y') }}</span><br>
                        <span class="text-primary fw-semibold">S/ {{ number_format($venta->total_a_cobrar, 2) }}</span>
                    </div>
                    @if((float)$venta->pagado > 0 && $venta->cliente_id)
                    <div class="alert alert-info py-2 small">
                        <i class="bi bi-info-circle me-1"></i>
                        Se generará un saldo a favor de <strong>S/ {{ number_format($venta->pagado, 2) }}</strong>
                        para {{ $venta->cliente->nombre ?? 'el cliente' }}.
                    </div>
                    @endif
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Motivo de anulación (opcional)</label>
                        <input type="text" name="motivo" class="form-control form-control-sm"
                               placeholder="Ej: Error en el pedido, duplicado...">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="confirmarAnular" required>
                        <label class="form-check-label small" for="confirmarAnular">
                            Entiendo que esta acción <strong>no se puede deshacer</strong>.
                        </label>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger" id="btnAnularConfirm" disabled>
                        <i class="bi bi-x-octagon me-1"></i>Anular Vale
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function recalcTotal() {
    const rows   = document.querySelectorAll('#tablaProductos tbody tr');
    let total    = 0;
    let hayItems = false;

    rows.forEach(row => {
        const input = row.querySelector('.cant-input');
        const cell  = row.querySelector('.subtotal-cell');
        if (!input || !cell) return;

        const cant    = parseFloat(input.value) || 0;
        const precio  = parseFloat(input.dataset.precio) || 0;
        const max     = parseFloat(input.dataset.max) || 0;
        const subtotal = Math.round(cant * precio * 100) / 100;

        cell.textContent = 'S/ ' + subtotal.toFixed(2);
        if (cant > max) {
            input.classList.add('is-invalid');
        } else {
            input.classList.remove('is-invalid');
        }
        if (cant > 0) hayItems = true;
        total += subtotal;
    });

    total = Math.round(total * 100) / 100;
    document.getElementById('totalDevolver').textContent = 'S/ ' + total.toFixed(2);

    const btn = document.getElementById('btnDevolver');
    const res = document.getElementById('resumenDevolucion');
    if (hayItems && total > 0) {
        btn.disabled = false;
        res.textContent = 'Se devolverá S/ ' + total.toFixed(2) + ' al cliente.';
    } else {
        btn.disabled = true;
        res.textContent = '';
    }
}

// Checkbox confirmar anulación
document.getElementById('confirmarAnular')?.addEventListener('change', function () {
    document.getElementById('btnAnularConfirm').disabled = !this.checked;
});
</script>
@endsection
