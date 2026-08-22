@extends('layouts.app')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h3 class="mb-1">Cuentas por pagar y letras</h3>
        <p class="text-muted mb-0">Facturas recibidas, F001 y pagos a proveedores</p>
    </div>
    <a href="/zendy/letras/importar" class="btn btn-primary"><i class="bi bi-upload me-1"></i>Importar reporte Excel</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">Documentos importados</small><div class="fs-3 fw-bold">{{ number_format($totales['documentos']) }}</div></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">Total registrado</small><div class="fs-3 fw-bold text-primary">S/ {{ number_format($totales['total'], 2) }}</div></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">Facturas serie F001</small><div class="fs-3 fw-bold text-success">{{ number_format($totales['f001']) }}</div></div></div></div>
</div>

<div class="card shadow-sm">
    <div class="card-body border-bottom">
        <form class="row g-2">
            <div class="col-md-5"><input name="buscar" value="{{ request('buscar') }}" class="form-control" placeholder="Buscar proveedor, RUC o factura"></div>
            <div class="col-md-2"><input name="serie" value="{{ request('serie') }}" class="form-control" placeholder="Serie, ej. F001"></div>
            <div class="col-md-3">
                <select name="estado" class="form-select">
                    <option value="">Todos los estados</option>
                    <option value="pendientes" @selected(request('estado')==='pendientes')>Con saldo pendiente</option>
                    <option value="vencidas" @selected(request('estado')==='vencidas')>Vencidas</option>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filtrar</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Proveedor</th><th>Documento</th><th>Emisión</th><th>Vencimiento</th><th class="text-end">Total</th><th class="text-end">Pagado</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            @forelse($cuentas as $cuenta)
                @php $pagado = (float) ($cuenta->aplicaciones_sum_monto_aplicado ?? 0); $saldo = max(0, (float)$cuenta->total - $pagado); $vencida = $saldo > 0 && $cuenta->fecha_vencimiento?->isPast(); @endphp
                <tr>
                    <td><div class="fw-semibold">{{ $cuenta->proveedor->razon_social }}</div><small class="text-muted">{{ $cuenta->proveedor->ruc ?: 'Sin RUC' }}</small></td>
                    <td><span class="badge {{ $cuenta->serie === 'F001' ? 'bg-success' : 'bg-secondary' }}">{{ $cuenta->serie }}</span> {{ $cuenta->correlativo }}</td>
                    <td>{{ $cuenta->fecha_emision?->format('d/m/Y') ?: '—' }}</td>
                    <td class="{{ $vencida ? 'text-danger fw-semibold' : '' }}">{{ $cuenta->fecha_vencimiento?->format('d/m/Y') ?: '—' }}</td>
                    <td class="text-end">S/ {{ number_format($cuenta->total, 2) }}</td>
                    <td class="text-end text-success">S/ {{ number_format($pagado, 2) }}<br><small class="text-danger">Saldo S/ {{ number_format($saldo, 2) }}</small></td>
                    <td><span class="badge {{ $saldo <= 0 ? 'bg-success' : ($vencida ? 'bg-danger' : ($pagado > 0 ? 'bg-warning text-dark' : 'bg-secondary')) }}">{{ ucfirst(str_replace('_',' ', $cuenta->estado_calculado)) }}</span></td>
                    <td><a href="/zendy/letras/{{ $cuenta->id }}" class="btn btn-sm btn-outline-primary">Ver</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-5">No hay cuentas por pagar. Importa el reporte de recibidos para comenzar.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $cuentas->links() }}</div>
</div>
@endsection