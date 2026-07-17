@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-broadcast me-2 text-primary"></i>Configuración Nubefact / Facturación Electrónica</h4>
        <p class="text-muted small mb-0">Datos de tu empresa y credenciales para emitir comprobantes electrónicos a SUNAT.</p>
    </div>
    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnTestConexion">
        <i class="bi bi-wifi me-1"></i>Probar conexión
    </button>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div id="testResultado" class="mb-3" style="display:none;"></div>

@if(!$tokenConfigurado)
<div class="alert alert-warning d-flex gap-2 align-items-start mb-3">
    <i class="bi bi-exclamation-triangle-fill fs-5 mt-1 text-warning"></i>
    <div>
        <strong>Token no configurado.</strong> Sin el Token API de Nubefact, la emisión electrónica no funcionará.
        Obtén tu token desde tu cuenta en <a href="https://nubefact.com" target="_blank">nubefact.com</a> → Perfil → API Token.
    </div>
</div>
@endif

<form action="/admin/nubefact" method="POST">
    @csrf
    @method('PUT')

    {{-- Token --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-key me-2 text-warning"></i>Credenciales API
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Token API Nubefact <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" name="token" id="tokenInput"
                               class="form-control font-monospace @error('token') is-invalid @enderror"
                               placeholder="{{ $tokenConfigurado ? '••••••••••••••••  (ya configurado — deja vacío para mantener)' : 'Pega aquí tu token de Nubefact' }}"
                               autocomplete="new-password">
                        <button type="button" class="btn btn-outline-secondary" id="btnToggleToken" title="Mostrar/ocultar">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    @if($tokenConfigurado)
                    <div class="form-text text-success"><i class="bi bi-check-circle me-1"></i>Token guardado. Deja el campo vacío si no deseas cambiarlo.</div>
                    @else
                    <div class="form-text">Lo encuentras en tu cuenta Nubefact → <strong>Configuración → API</strong>.</div>
                    @endif
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">URL API</label>
                    <input type="url" name="url" class="form-control font-monospace form-control-sm"
                           value="{{ $valores['url'] }}" required>
                    <div class="form-text">No cambiar salvo indicación de Nubefact.</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Datos empresa --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-building me-2 text-info"></i>Datos de la Empresa (enviados a SUNAT)
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">RUC <span class="text-danger">*</span></label>
                    <input type="text" name="ruc" class="form-control font-monospace @error('ruc') is-invalid @enderror"
                           value="{{ old('ruc', $valores['ruc']) }}" maxlength="11" inputmode="numeric" required>
                    @error('ruc')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Razón Social <span class="text-danger">*</span></label>
                    <input type="text" name="razon_social" class="form-control @error('razon_social') is-invalid @enderror"
                           value="{{ old('razon_social', $valores['razon_social']) }}" required>
                    @error('razon_social')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Nombre Comercial</label>
                    <input type="text" name="nombre_comercial" class="form-control"
                           value="{{ old('nombre_comercial', $valores['nombre_comercial']) }}">
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Dirección Fiscal</label>
                    <input type="text" name="direccion" class="form-control"
                           value="{{ old('direccion', $valores['direccion']) }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">IGV (%)</label>
                    <input type="number" name="igv_porcentaje" class="form-control text-end"
                           value="{{ old('igv_porcentaje', $valores['igv_porcentaje']) }}"
                           step="0.01" min="0" max="100" required>
                </div>
            </div>
        </div>
    </div>

    {{-- Series --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-123 me-2 text-success"></i>Series de Comprobantes
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Serie Factura</label>
                    <input type="text" name="serie_factura" class="form-control font-monospace text-uppercase @error('serie_factura') is-invalid @enderror"
                           value="{{ old('serie_factura', $valores['serie_factura']) }}"
                           maxlength="10" placeholder="FFF1" required>
                    <div class="form-text">Ej: F001, FFF1</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Serie Boleta</label>
                    <input type="text" name="serie_boleta" class="form-control font-monospace text-uppercase @error('serie_boleta') is-invalid @enderror"
                           value="{{ old('serie_boleta', $valores['serie_boleta']) }}"
                           maxlength="10" placeholder="BBB1" required>
                    <div class="form-text">Ej: B001, BBB1</div>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-light border mb-0 py-2 small">
                        <i class="bi bi-info-circle me-1"></i>
                        Las series deben coincidir con las que registraste en Nubefact. El número correlativo se lleva automáticamente por las <a href="/admin/series">Series de la Caja</a>.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-floppy me-1"></i>Guardar configuración
        </button>
        <a href="/casadets/ventas" class="btn btn-outline-secondary">Ir a Ventas</a>
    </div>
</form>

<script>
document.getElementById('btnToggleToken')?.addEventListener('click', function() {
    const inp = document.getElementById('tokenInput');
    const ico = this.querySelector('i');
    if (inp.type === 'password') {
        inp.type = 'text';
        ico.className = 'bi bi-eye-slash';
    } else {
        inp.type = 'password';
        ico.className = 'bi bi-eye';
    }
});

document.getElementById('btnTestConexion')?.addEventListener('click', async function() {
    const btn = this;
    const res = document.getElementById('testResultado');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Probando…';
    res.style.display = 'none';

    try {
        const r = await fetch('/admin/nubefact/test', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const d = await r.json();
        res.className = d.ok ? 'alert alert-success mb-3' : 'alert alert-warning mb-3';
        res.innerHTML = (d.ok ? '<i class="bi bi-check-circle me-2"></i>' : '<i class="bi bi-exclamation-triangle me-2"></i>') + d.mensaje;
        res.style.display = '';
    } catch(e) {
        res.className = 'alert alert-danger mb-3';
        res.innerHTML = '<i class="bi bi-x-circle me-2"></i>Error de red: ' + e.message;
        res.style.display = '';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-wifi me-1"></i>Probar conexión';
    }
});
</script>
@endsection
