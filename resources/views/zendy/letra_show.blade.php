@extends('layouts.app')

@section('content')
@php $pagado = $cuenta->pagado; $saldo = $cuenta->saldo; @endphp
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h3 class="mb-1">{{ $cuenta->serie }}-{{ $cuenta->correlativo }}</h3><p class="text-muted mb-0">{{ $cuenta->proveedor->razon_social }} · RUC {{ $cuenta->proveedor->ruc ?: '—' }}</p></div>
    <a href="/zendy/letras" class="btn btn-outline-secondary">Volver</a>
</div>
@if($cuenta->serie === 'F001') <div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i>Factura F001 identificada como documento origen.</div> @endif
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Total</small><div class="fs-4 fw-bold">S/ {{ number_format($cuenta->total,2) }}</div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Pagado</small><div class="fs-4 fw-bold text-success">S/ {{ number_format($pagado,2) }}</div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Saldo</small><div class="fs-4 fw-bold text-danger">S/ {{ number_format($saldo,2) }}</div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Vencimiento</small><div class="fs-4 fw-bold">{{ $cuenta->fecha_vencimiento?->format('d/m/Y') ?: '—' }}</div></div></div></div>
</div>
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#panelPagos" type="button"><i class="bi bi-wallet2 me-1"></i>Pagos y letras</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#panelDocumentos" type="button"><i class="bi bi-paperclip me-1"></i>Documentos <span class="badge bg-secondary">{{ $cuenta->documentos->count() }}</span></button></li>
</ul>
<div class="tab-content">
<div class="tab-pane fade show active" id="panelPagos">
<div class="row g-4">
<div class="col-lg-7">
<div class="card shadow-sm mb-4"><div class="card-header fw-semibold">Historial de pagos</div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Fecha</th><th>Método</th><th>Referencia</th><th>Retención</th><th class="text-end">Aplicado</th><th class="text-end">Salida</th></tr></thead><tbody>
@forelse($cuenta->aplicaciones as $a)<tr><td>{{ $a->pago?->fecha?->format('d/m/Y') }}</td><td>{{ ucfirst($a->pago?->metodo_pago) }}</td><td>{{ $a->pago?->referencia ?: '—' }}</td><td>@if($a->pago?->retencion_aplicada)<span class="badge bg-warning text-dark">3% · S/ {{ number_format($a->pago->retencion_monto,2) }}</span>@else — @endif</td><td class="text-end">S/ {{ number_format($a->monto_aplicado,2) }}</td><td class="text-end">S/ {{ number_format($a->pago?->monto_neto ?? $a->monto_aplicado,2) }}</td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">Sin pagos registrados.</td></tr>@endforelse
</tbody></table></div></div>
<div class="card shadow-sm"><div class="card-header fw-semibold">Letras asociadas</div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Número</th><th>Vencimiento</th><th class="text-end">Importe</th></tr></thead><tbody>
@forelse($cuenta->letras as $letra)<tr><td>{{ $letra->numero }}</td><td>{{ $letra->fecha_vencimiento->format('d/m/Y') }}</td><td class="text-end">S/ {{ number_format($letra->monto,2) }}</td></tr>@empty<tr><td colspan="3" class="text-center text-muted py-4">No hay letras asociadas.</td></tr>@endforelse
</tbody></table></div></div>
</div>
<div class="col-lg-5">
<div class="card shadow-sm mb-4"><div class="card-header fw-semibold">Registrar pago</div><div class="card-body">
@if($saldo > 0)<form method="POST" action="/zendy/letras/{{ $cuenta->id }}/pago" enctype="multipart/form-data">@csrf
<div class="row g-2"><div class="col-6"><label class="form-label">Fecha</label><input type="date" name="fecha" value="{{ today()->toDateString() }}" class="form-control" required></div><div class="col-6"><label class="form-label">Monto bruto aplicado</label><input type="number" name="monto" step="0.01" min="0.01" max="{{ $saldo }}" class="form-control" required></div>
<div class="col-6"><label class="form-label">Método</label><select name="metodo_pago" class="form-select"><option>transferencia</option><option>efectivo</option><option>depósito</option><option>cheque</option><option>otro</option></select></div><div class="col-6"><label class="form-label">Letra</label><select name="letra_id" class="form-select"><option value="">Pago directo</option>@foreach($cuenta->letras as $letra)<option value="{{ $letra->id }}">{{ $letra->numero }}</option>@endforeach</select></div>
<div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="retencion_aplicada" value="1" id="retencion"><label class="form-check-label" for="retencion">Aplicar retención del 3%</label></div><small class="text-muted">La deuda baja por el bruto; caja registra el neto.</small></div>
<div class="col-12"><label class="form-label">Imagen del comprobante de pago</label><input type="file" name="pago_imagen" class="form-control" accept="image/*"></div><div class="col-12"><label class="form-label">N° operación / referencia</label><input name="referencia" class="form-control"></div><div class="col-12"><label class="form-label">Observación</label><textarea name="observaciones" rows="2" class="form-control"></textarea></div><div class="col-12"><button class="btn btn-success w-100">Registrar pago</button></div></div></form>
@else <div class="alert alert-success mb-0">Esta cuenta ya está pagada.</div>@endif
</div></div>
<div class="card shadow-sm"><div class="card-header fw-semibold">Agregar letra</div><div class="card-body"><form method="POST" action="/zendy/letras/{{ $cuenta->id }}/letra">@csrf
<div class="mb-2"><label class="form-label">Número de letra</label><input name="numero" class="form-control" placeholder="LT-001" required></div><div class="row g-2"><div class="col-6"><label class="form-label">Vencimiento</label><input type="date" name="fecha_vencimiento" class="form-control" required></div><div class="col-6"><label class="form-label">Monto</label><input type="number" name="monto" step="0.01" min="0.01" max="{{ $saldo }}" class="form-control" required></div></div><button class="btn btn-outline-primary w-100 mt-3">Guardar letra</button>
</form></div></div>
</div></div>
</div>
<div class="tab-pane fade" id="panelDocumentos">
    <div class="card shadow-sm">
        <div class="card-header fw-semibold">Expediente documental</div>
        <div class="card-body">
            <p class="text-muted">Guarda los archivos de la factura y de la retención para consultarlos desde esta cuenta.</p>
            <form method="POST" action="/zendy/letras/{{ $cuenta->id }}/documentos" enctype="multipart/form-data">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">XML de factura</label><input type="file" name="factura_xml" class="form-control" accept=".xml"></div>
                    <div class="col-md-6"><label class="form-label">PDF de factura</label><input type="file" name="factura_pdf" class="form-control" accept=".pdf"></div>
                    <div class="col-md-6"><label class="form-label">XML de retención</label><input type="file" name="retencion_xml" class="form-control" accept=".xml"></div>
                    <div class="col-md-6"><label class="form-label">PDF de retención</label><input type="file" name="retencion_pdf" class="form-control" accept=".pdf"></div>
                </div>
                <button class="btn btn-primary mt-3"><i class="bi bi-cloud-upload me-1"></i>Guardar documentos</button>
            </form>
            @if($cuenta->documentos->count())
                <hr><h6>Archivos guardados</h6>
                <div class="row g-2">@foreach($cuenta->documentos as $d)<div class="col-md-6"><div class="border rounded p-2 d-flex justify-content-between"><span>{{ ucfirst(str_replace('_',' ', $d->tipo)) }}<br><small class="text-muted">{{ $d->nombre_original }}</small></span><a href="{{ $d->url() }}" class="btn btn-sm btn-outline-primary">Abrir</a></div></div>@endforeach</div>
            @else <div class="text-muted mt-4">Todavía no hay documentos guardados.</div>@endif
        </div>
    </div>
</div>
</div>
@endsection