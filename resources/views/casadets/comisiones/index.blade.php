@extends('layouts.app')

@section('content')
<div class="mb-3">
    <h3 class="mb-1">Comisiones</h3>
    <div class="text-muted small">Libro de movimientos. El saldo puede ser negativo y no equivale a pagos al vendedor.</div>
</div>

<form method="GET" action="/comisiones" class="card card-body mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-sm-6 col-lg-3">
            <label class="form-label">Vendedor</label>
            <select class="form-select" name="vendedor">
                <option value="">Todos</option>
                @foreach($vendedores as $vendedor)
                    <option value="{{ $vendedor->id }}" @selected((string) request('vendedor') === (string) $vendedor->id)>{{ $vendedor->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-6 col-lg-2"><label class="form-label">Desde</label><input class="form-control" type="date" name="desde" value="{{ request('desde') }}"></div>
        <div class="col-sm-6 col-lg-2"><label class="form-label">Hasta</label><input class="form-control" type="date" name="hasta" value="{{ request('hasta') }}"></div>
        <div class="col-sm-6 col-lg-2">
            <label class="form-label">Movimiento</label>
            <select class="form-select" name="tipo">
                <option value="">Todos</option>
                @foreach(['liberacion' => 'Liberación', 'reversion' => 'Reversión', 'ajuste_positivo' => 'Ajuste positivo', 'ajuste_negativo' => 'Ajuste negativo'] as $key => $label)
                    <option value="{{ $key }}" @selected(request('tipo') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-3 d-flex gap-2">
            <button class="btn btn-primary flex-grow-1" type="submit">Filtrar</button>
            <a class="btn btn-outline-secondary" href="/comisiones">Limpiar</a>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card card-body"><small class="text-muted">Movimientos positivos</small><strong class="fs-5">S/ {{ $totales['positivos'] }}</strong></div></div>
    <div class="col-md-4"><div class="card card-body"><small class="text-muted">Movimientos negativos</small><strong class="fs-5">S/ {{ $totales['negativos'] }}</strong></div></div>
    <div class="col-md-4"><div class="card card-body"><small class="text-muted">Saldo del período</small><strong class="fs-5">S/ {{ $totales['saldo'] }}</strong></div></div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Fecha efectiva</th><th>Vendedor</th><th>Venta</th><th>Tipo</th><th class="text-end">Tasa</th><th class="text-end">Utilidad base</th><th class="text-end">Importe</th><th>Usuario</th></tr></thead>
            <tbody>
                @forelse($movimientos as $movimiento)
                    <tr>
                        <td>{{ $movimiento->fecha_efectiva->format('d/m/Y') }}</td>
                        <td>{{ $movimiento->vendedor?->nombre ?? '—' }}</td>
                        <td>
                            <a href="/casadets/ventas/{{ $movimiento->venta_id }}">{{ $movimiento->venta?->documento_numero ?: 'Venta #' . $movimiento->venta_id }}</a>
                        </td>
                        <td>{{ ucfirst(str_replace('_', ' ', $movimiento->tipo)) }}</td>
                        <td class="text-end">{{ $movimiento->porcentaje === null ? '—' : number_format((float) $movimiento->porcentaje, 2) . '%' }}</td>
                        <td class="text-end">{{ $movimiento->utilidad_base === null ? '—' : 'S/ ' . number_format((float) $movimiento->utilidad_base, 2) }}</td>
                        <td class="text-end fw-semibold {{ (float) $movimiento->importe < 0 ? 'text-danger' : 'text-success' }}">S/ {{ number_format((float) $movimiento->importe, 2) }}</td>
                        <td>{{ $movimiento->user?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-5">No hay movimientos en este período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($movimientos->hasPages())<div class="card-footer">{{ $movimientos->links() }}</div>@endif
</div>
@endsection
