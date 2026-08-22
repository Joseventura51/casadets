@extends('layouts.app')

@section('content')
@php $pagado = $cuenta->pagado; $saldo = $cuenta->saldo; @endphp
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h3 class="mb-1">{{ $cuenta->serie }}-{{ $cuenta->correlativo }}</h3><p class="text-muted mb-0">{{ $cuenta->proveedor->razon_social }} · RUC {{ $cuenta->proveedor->ruc ?: '—' }}</p></div>
    <a href="/zendy/letras" class="btn btn-outline-secondary">Volver</a>
</div>
@if($cuenta->serie === 'F001') <div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i>Factura F001 identificada como documento origen de esta cuenta por pagar.</div> @endif
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Total</small><div class="fs-4 fw-bold">S/ {{ number_format($cuenta->total,2) }}</div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Pagado</small><div class="fs-4 fw-bold text-success">S/ {{ number_format($pagado,2) }}</div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Saldo</small><div class="fs-4 fw-bold text-danger">S/ {{ number_format($saldo,2) }}</div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Vencimiento</small><div class="fs-4 fw-bold">{{ $cuenta->fecha_vencimiento?->format('d/m/Y') ?: '—' }}</div></div></div></div>
</div>
<div class="row g-4">
<div class="col-lg-7">
<div class="card shadow-sm mb-4"><div class="card-header fw-semibold">Historial de pagos</div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Fecha</th><th>Método</th><th>Referencia</th><th class="text-end">Importe</th></tr></thead><tbody>
@forelse($cuenta->aplicaciones as $aplicacion)<tr><td>{{ $aplicacion->pago?->fecha?->format('d/m/Y') }}</td><td>{{ ucfirst($aplicacion->pago?->metodo_pago) }}</td><td>{{ $aplicacion->pago?->referencia ?: '—' }}</td><td class="text-end">S/ {{ number_format($aplicacion->monto_aplicado,2) }}</td></tr>@empty<tr><td colspan="4" class="text-center text-muted py-4">Sin pagos registrados.</td></tr>@endforelse
</tbody></table></div></div>
<div class="card shadow-sm"><div class="card-header fw-semibold">Letras asociadas</div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Número</th><th>Vencimiento</th><th class="text-end">Importe</th></tr></thead><tbody>
@forelse($cuenta->letras as $letra)<tr><td>{{ $letra->numero }}</td><td>{{ $letra->fecha_vencimiento->format('d/m/Y') }}</td><td class="text-end">S/ {{ number_format($letra->monto,2) }}</td></tr>@empty<tr><td colspan="3" class="text-center text-muted py-4">No se han registrado letras para esta factura.</td></tr>@endforelse
</tbody></table></div></div>
</div>
<div class="col-lg-5">
<div class="card shadow-sm mb-4"><div class="card-header fw-semibold">Registrar pago</div><div class="card-body">
@if($saldo > 0)<form method="POST" action="/zendy/letras/{{ $cuenta->id }}/pago">@csrf
<div class="row g-2"><div class="col-6"><label class="form-label">Fecha</label><input type="date" name="fecha" value="{{ today()->toDateString() }}" class="form-control" required></div><div class="col-6"><label class="form-label">Monto</label><input type="number" name="monto" step="0.01" min="0.01" max="{{ $saldo }}" class="form-control" required></div>
<div class="col-6"><label class="form-label">Método</label><select name="metodo_pago" class="form-select"><option>transferencia</option><option>efectivo</option><option>depósito</option><option>cheque</option><option>otro</option></select></div><div class="col-6"><label class="form-label">Letra</label><select name="letra_id" class="form-select"><option value="">Pago directo</option>@foreach($cuenta->letras as $letra)<option value="{{ $letra->id }}">{{ $letra->numero }}</option>@endforeach</select></div>
<div class="col-12"><label class="form-label">N° operación / referencia</label><input name="referencia" class="form-control"></div><div class="col-12"><label class="form-label">Observación</label><textarea name="observaciones" rows="2" class="form-control"></textarea></div><div class="col-12"><button class="btn btn-success w-100">Registrar pago</button></div></div></form>
@else <div class="alert alert-success mb-0">Esta cuenta ya está pagada.</div>@endif
</div></div>
<div class="card shadow-sm"><div class="card-header fw-semibold">Agregar letra</div><div class="card-body"><form method="POST" action="/zendy/letras/{{ $cuenta->id }}/letra">@csrf
<div class="mb-2"><label class="form-label">Número de letra</label><input name="numero" class="form-control" placeholder="LT-001" required></div><div class="row g-2"><div class="col-6"><label class="form-label">Vencimiento</label><input type="date" name="fecha_vencimiento" class="form-control" required></div><div class="col-6"><label class="form-label">Monto</label><input type="number" name="monto" step="0.01" min="0.01" max="{{ $saldo }}" class="form-control" required></div></div><button class="btn btn-outline-primary w-100 mt-3">Guardar letra</button>
</form></div></div>
</div></div>
@endsection