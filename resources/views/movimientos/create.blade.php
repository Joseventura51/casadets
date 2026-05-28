@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width:680px;">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
            <h2 class="h4 mb-0">Registrar {{ ucfirst($tipo) }}</h2>
            <p class="text-muted mb-0 small">
                Movimiento manual — se registrará con origen "manual" en el ledger.
            </p>
        </div>
        <div class="card-body">
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            <form action="/movimientos" method="POST" class="row g-3">
                @csrf
                <input type="hidden" name="tipo" value="{{ $tipo }}">

                <div class="col-12">
                    <label class="form-label">Categoría <span class="text-danger">*</span></label>
                    <input type="text" name="categoria" value="{{ old('categoria') }}"
                           class="form-control" placeholder="ej: Pago proveedor, Alquiler, Servicios..." required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Monto (S/) <span class="text-danger">*</span></label>
                    <input type="number" name="monto" value="{{ old('monto') }}"
                           class="form-control" step="0.01" min="0.01" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Fecha <span class="text-danger">*</span></label>
                    <input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}"
                           class="form-control" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Empresa</label>
                    <select name="empresa" class="form-select">
                        <option value="casadets" {{ old('empresa', 'casadets') === 'casadets' ? 'selected' : '' }}>CASADETS</option>
                        <option value="zendy"    {{ old('empresa') === 'zendy' ? 'selected' : '' }}>ZENDY</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Método de pago <span class="text-danger">*</span></label>
                    <select name="metodo_pago" class="form-select" required>
                        @foreach([
                            'efectivo'      => 'Efectivo',
                            'yape'          => 'Yape',
                            'plin'          => 'Plin',
                            'deposito'      => 'Depósito',
                            'transferencia' => 'Transferencia',
                        ] as $val => $label)
                            <option value="{{ $val }}" {{ old('metodo_pago') === $val ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @if($tipo === 'salida')
                        <div class="form-text text-warning">
                            <i class="bi bi-info-circle me-1"></i>
                            Las salidas en <strong>Efectivo</strong> se descuentan del saldo de caja.
                        </div>
                    @endif
                </div>

                <div class="col-12">
                    <hr class="my-1">
                    <p class="text-muted small mb-2">Documento de referencia (opcional)</p>
                </div>

                <div class="col-md-5">
                    <label class="form-label small">Tipo de documento</label>
                    <select name="documento_tipo" class="form-select">
                        <option value="">— Sin documento —</option>
                        @foreach(['factura','boleta','proforma','recibo','otro'] as $d)
                            <option value="{{ $d }}" {{ old('documento_tipo') == $d ? 'selected' : '' }}>
                                {{ ucfirst($d) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-7">
                    <label class="form-label small">Número de documento</label>
                    <input type="text" name="documento_numero" value="{{ old('documento_numero') }}"
                           class="form-control" placeholder="ej: F001-00012345">
                </div>

                <div class="col-12">
                    <label class="form-label small">Observaciones</label>
                    <textarea name="observaciones" class="form-control" rows="2"
                              placeholder="Descripción adicional...">{{ old('observaciones') }}</textarea>
                </div>

                <div class="col-12 d-flex gap-2 border-top pt-3 mt-1">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Guardar {{ ucfirst($tipo) }}
                    </button>
                    <a href="/movimientos" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
