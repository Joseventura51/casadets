@extends('layouts.app')

@section('content')
<style>
/* ── Layout ──────────────────────────────────────────── */
.rpt-header { margin-bottom:1.25rem; }
.rpt-filter-card { border:0; box-shadow:0 1px 6px rgba(15,23,42,.09); border-radius:10px; }
.rpt-filter-label { display:block; font-size:.68rem; font-weight:700; text-transform:uppercase;
                    letter-spacing:.04em; color:#6c757d; margin-bottom:.22rem; }

/* ── Period tabs ─────────────────────────────────────── */
.periodo-tabs { display:flex; gap:.5rem; flex-wrap:wrap; }
.periodo-tabs .btn { border-radius:20px; font-size:.82rem; padding:.3rem .95rem; }

/* ── KPI cards ───────────────────────────────────────── */
.kpi-card { border-radius:12px; border:1px solid rgba(0,0,0,.07);
            box-shadow:0 2px 8px rgba(15,23,42,.06); transition:transform .15s; }
.kpi-card:hover { transform:translateY(-2px); }
.kpi-card .kpi-icon { font-size:1.6rem; opacity:.8; }
.kpi-card .kpi-value { font-size:1.5rem; font-weight:700; line-height:1.1; }
.kpi-card .kpi-label { font-size:.72rem; text-transform:uppercase; letter-spacing:.04em;
                        color:#6c757d; margin-bottom:.15rem; }
.kpi-card .kpi-sub { font-size:.75rem; color:#9ca3af; margin-top:.2rem; }

/* Colores KPI */
.kpi-ventas  { background:linear-gradient(135deg,#eff6ff 0%,#fff 100%); border-color:#bfdbfe; }
.kpi-compras { background:linear-gradient(135deg,#f0fdf4 0%,#fff 100%); border-color:#bbf7d0; }
.kpi-utilidad { background:linear-gradient(135deg,#fdf4ff 0%,#fff 100%); border-color:#e9d5ff; cursor:pointer; }
.kpi-utilidad:hover { background:linear-gradient(135deg,#f3e8ff 0%,#fdf4ff 100%); }
.kpi-margen  { background:linear-gradient(135deg,#fffbeb 0%,#fff 100%); border-color:#fde68a; }

/* ── Section tabs ────────────────────────────────────── */
.rpt-section-tabs .nav-link { border-radius:8px 8px 0 0; font-size:.85rem;
                               padding:.45rem 1.1rem; color:#6c757d; }
.rpt-section-tabs .nav-link.active { color:#2563eb; font-weight:600; border-bottom:2px solid #2563eb; }

/* ── Tables ──────────────────────────────────────────── */
.rpt-table th { font-size:.7rem; text-transform:uppercase; letter-spacing:.04em;
                color:#6c757d; white-space:nowrap; padding:.55rem .75rem; }
.rpt-table td { vertical-align:middle; font-size:.85rem; padding:.55rem .75rem; }
.rpt-table tbody tr:hover { background:#f8fafc; }

/* ── Progress bars (métodos pago, top listas) ─────────── */
.rpt-bar-row { display:flex; align-items:center; gap:.6rem; margin-bottom:.55rem; }
.rpt-bar-row .rpt-bar-label { font-size:.8rem; min-width:110px; }
.rpt-bar-row .rpt-bar-fill { flex:1; background:#f1f5f9; border-radius:20px; height:8px; overflow:hidden; }
.rpt-bar-fill-inner { height:100%; border-radius:20px; transition:width .5s ease; }
.rpt-bar-row .rpt-bar-val { font-size:.8rem; font-weight:600; min-width:80px; text-align:right; }

/* ── Chart containers ────────────────────────────────── */
.chart-wrap { position:relative; height:220px; }

/* ── Spinner ─────────────────────────────────────────── */
#rptLoading { padding:4rem 0; }

/* ── Print CSS ───────────────────────────────────────── */
@media print {
    .sidebar, .mobile-navbar, .rpt-filter-card, .rpt-header .btn,
    .rpt-section-tabs, #tabCompras, #tabUtilidad, .modal { display:none!important; }
    .kpi-card { box-shadow:none!important; break-inside:avoid; }
    .chart-wrap { height:160px; }
}

/* ── Responsive ──────────────────────────────────────── */
@media (max-width:576px) {
    .kpi-card .kpi-value { font-size:1.2rem; }
    .chart-wrap { height:160px; }
}
</style>

{{-- ── HEADER ─────────────────────────────────────────── --}}
<div class="rpt-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h2 class="h3 mb-1"><i class="bi bi-bar-chart-line me-2 text-primary"></i>Reportes</h2>
        <p class="text-muted mb-0" id="rptSubtitulo">Estadísticas de ventas, compras y utilidades</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-outline-success btn-sm" onclick="exportarExcel()">
            <i class="bi bi-file-earmark-excel me-1"></i>Excel
        </button>
        <button class="btn btn-outline-danger btn-sm" onclick="exportarPdf()">
            <i class="bi bi-file-earmark-pdf me-1"></i>PDF
        </button>
    </div>
</div>

{{-- ── FILTROS ──────────────────────────────────────────── --}}
<div class="card rpt-filter-card mb-3">
    <div class="card-body pb-2">
        {{-- Tabs de período --}}
        <div class="periodo-tabs mb-3">
            <button class="btn btn-primary tab-periodo" data-periodo="diario">Diario</button>
            <button class="btn btn-outline-primary tab-periodo" data-periodo="semanal">Semanal</button>
            <button class="btn btn-outline-primary tab-periodo" data-periodo="mensual">Mensual</button>
        </div>
        {{-- Filtros adicionales --}}
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="rpt-filter-label">Desde</label>
                <input type="date" id="fDesde" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label class="rpt-filter-label">Hasta</label>
                <input type="date" id="fHasta" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label class="rpt-filter-label">Vendedor</label>
                <select id="fVendedor" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($vendedores as $vend)
                        <option value="{{ $vend->id }}">{{ $vend->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="rpt-filter-label">Método pago</label>
                <select id="fMetodo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="efectivo">Efectivo</option>
                    <option value="yape">Yape</option>
                    <option value="plin">Plin</option>
                    <option value="deposito">Depósito</option>
                    <option value="transferencia">Transferencia</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="rpt-filter-label">Cliente</label>
                <select id="fCliente" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($clientes as $cli)
                        <option value="{{ $cli->id }}">{{ $cli->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2 d-flex gap-1">
                <button class="btn btn-primary btn-sm flex-fill" onclick="cargarDatos()">
                    <i class="bi bi-search me-1"></i>Filtrar
                </button>
                <button class="btn btn-outline-secondary btn-sm" onclick="limpiarFiltros()" title="Limpiar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ── SPINNER ──────────────────────────────────────────── --}}
<div id="rptLoading" class="text-center">
    <div class="spinner-border text-primary" style="width:2.5rem;height:2.5rem;"></div>
    <p class="text-muted mt-2 mb-0">Cargando reporte...</p>
</div>

{{-- ── CONTENIDO REPORTE ────────────────────────────────── --}}
<div id="rptContent" style="display:none;">

    {{-- KPI Cards --}}
    <div class="row g-3 mb-4" id="kpiRow">
        <div class="col-6 col-md-3">
            <div class="card kpi-card kpi-ventas p-3 h-100" onclick="abrirModalVentas()" title="Click para ver detalle" style="cursor:pointer;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="kpi-label">Total Ventas <small class="text-muted">(click para detalle)</small></div>
                        <div class="kpi-value text-primary" id="kpiTotalVentas">S/ 0.00</div>
                        <div class="kpi-sub"><span id="kpiCantVentas">0</span> ventas</div>
                    </div>
                    <span class="kpi-icon">🛒</span>
                </div>
                <div class="mt-2 pt-2 border-top" style="font-size:.72rem;color:#6c757d;">
                    IGV: <strong id="kpiIgvVentas">S/ 0.00</strong> &middot;
                    Base: <strong id="kpiSubtotalVentas">S/ 0.00</strong>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card kpi-card kpi-compras p-3 h-100" onclick="abrirModalCompras()" title="Click para ver detalle" style="cursor:pointer;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="kpi-label">Total Compras <small class="text-muted">(click para detalle)</small></div>
                        <div class="kpi-value text-success" id="kpiTotalCompras">S/ 0.00</div>
                        <div class="kpi-sub"><span id="kpiCantCompras">0</span> compras</div>
                    </div>
                    <span class="kpi-icon">📦</span>
                </div>
                <div class="mt-2 pt-2 border-top" style="font-size:.72rem;color:#6c757d;">
                    IGV: <strong id="kpiIgvCompras">S/ 0.00</strong>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card kpi-card kpi-utilidad p-3 h-100" onclick="abrirModalUtilidad()" title="Click para ver detalle">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="kpi-label">Utilidad <small class="text-muted">(click para detalle)</small></div>
                        <div class="kpi-value text-purple" id="kpiUtilidad" style="color:#7c3aed;">S/ 0.00</div>
                        <div class="kpi-sub">Costo: <span id="kpiCosto">S/ 0.00</span></div>
                    </div>
                    <span class="kpi-icon">💰</span>
                </div>
                <div class="mt-2 pt-2 border-top" style="font-size:.72rem;color:#6c757d;">
                    Ventas: <strong id="kpiVentasUtilidad">S/ 0.00</strong>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card kpi-card kpi-margen p-3 h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="kpi-label">Comisión Utilidad (35%/50% dom.)</div>
                        <div class="kpi-value text-warning" id="kpiMargen">S/ 0.00</div>
                        <div class="kpi-sub">Utilidad total: <span id="kpiInvertido">S/ 0.00</span></div>
                    </div>
                    <span class="kpi-icon">📈</span>
                </div>
                <div class="mt-2 pt-2 border-top" style="font-size:.72rem;color:#6c757d;">
                    Margen: <strong id="kpiRecuperado">0%</strong>
                </div>
            </div>
        </div>
    </div>

    {{-- Gráficos --}}
    <div class="row g-3 mb-4">
        <div class="col-md-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-semibold small">Ventas por día</span>
                    <span class="text-muted" style="font-size:.72rem;" id="chartLabel">—</span>
                </div>
                <div class="card-body p-3">
                    <div class="chart-wrap"><canvas id="chartVentas"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom">
                    <span class="fw-semibold small">Métodos de pago</span>
                </div>
                <div class="card-body p-3 d-flex align-items-center justify-content-center">
                    <div class="chart-wrap w-100"><canvas id="chartMetodos"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs de sección: Ventas | Compras | Utilidad --}}
    <ul class="nav rpt-section-tabs nav-tabs mb-0" id="seccionTabs">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#tabVentas">
                <i class="bi bi-cart3 me-1"></i>Ventas
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tabCompras">
                <i class="bi bi-box-seam me-1"></i>Compras
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tabUtilidad">
                <i class="bi bi-graph-up-arrow me-1"></i>Utilidad
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tabSemanales" onclick="cargarSemanales()">
                <i class="bi bi-calendar-check me-1"></i>Reportes Semanales
            </a>
        </li>
    </ul>

    <div class="tab-content card shadow-sm border-0 border-top-0 rounded-top-0" id="seccionTabContent">

        {{-- TAB VENTAS --}}
        <div class="tab-pane fade show active p-3" id="tabVentas">
            <div class="row g-3">
                {{-- Top Clientes --}}
                <div class="col-md-6">
                    <h6 class="small fw-bold text-uppercase text-muted mb-3">
                        <i class="bi bi-people me-1"></i>Top Clientes
                    </h6>
                    <div id="listaTopClientes">
                        <p class="text-muted text-center py-3">Sin datos</p>
                    </div>
                </div>
                {{-- Top Productos --}}
                <div class="col-md-6">
                    <h6 class="small fw-bold text-uppercase text-muted mb-3">
                        <i class="bi bi-box me-1"></i>Top Productos
                    </h6>
                    <div id="listaTopProductosV">
                        <p class="text-muted text-center py-3">Sin datos</p>
                    </div>
                </div>
                {{-- Comprobantes --}}
                <div class="col-md-6">
                    <h6 class="small fw-bold text-uppercase text-muted mb-3">
                        <i class="bi bi-receipt me-1"></i>Comprobantes emitidos
                    </h6>
                    <div class="table-responsive">
                        <table class="table table-sm rpt-table">
                            <thead><tr><th>Tipo</th><th>Cantidad</th><th class="text-end">Total</th></tr></thead>
                            <tbody id="tablaComprobantes">
                                <tr><td colspan="3" class="text-center text-muted">Sin datos</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                {{-- Métodos de pago detalle --}}
                <div class="col-md-6">
                    <h6 class="small fw-bold text-uppercase text-muted mb-3">
                        <i class="bi bi-credit-card me-1"></i>Métodos de pago
                    </h6>
                    <div id="listaMetodos">
                        <p class="text-muted text-center py-3">Sin datos</p>
                    </div>
                </div>
            </div>

            {{-- Ventas por día con totales --}}
            <div class="mt-4">
                <h6 class="small fw-bold text-uppercase text-muted mb-3">
                    <i class="bi bi-calendar3 me-1"></i>Ventas por día
                </h6>
                <div class="table-responsive">
                    <table class="table table-sm rpt-table" id="tablaPorDia">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th class="text-center"># Ventas</th>
                                <th class="text-end">Total Vendido</th>
                                <th class="text-end">Costo</th>
                                <th class="text-end">Utilidad Bruta</th>
                                <th class="text-end">Comisión</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyPorDia">
                            <tr><td colspan="6" class="text-center text-muted py-3">Sin datos en este período</td></tr>
                        </tbody>
                        <tfoot class="table-secondary fw-bold" id="tfootPorDia"></tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- TAB COMPRAS --}}
        <div class="tab-pane fade p-3" id="tabCompras">
            <div class="row g-3">
                {{-- KPI compras --}}
                <div class="col-12">
                    <div class="row g-2 mb-2">
                        <div class="col-6 col-md-3">
                            <div class="bg-light rounded p-2 text-center">
                                <div class="small text-muted">Total comprado</div>
                                <div class="fw-bold text-success" id="cmpTotal">S/ 0.00</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="bg-light rounded p-2 text-center">
                                <div class="small text-muted">Cantidad compras</div>
                                <div class="fw-bold" id="cmpCant">0</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="bg-light rounded p-2 text-center">
                                <div class="small text-muted">IGV compras</div>
                                <div class="fw-bold text-warning" id="cmpIgv">S/ 0.00</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="bg-light rounded p-2 text-center">
                                <div class="small text-muted">Base imponible</div>
                                <div class="fw-bold" id="cmpBase">S/ 0.00</div>
                            </div>
                        </div>
                    </div>
                </div>
                {{-- Proveedores --}}
                <div class="col-md-6">
                    <h6 class="small fw-bold text-uppercase text-muted mb-3">
                        <i class="bi bi-building me-1"></i>Proveedores
                    </h6>
                    <div id="listaProveedores">
                        <p class="text-muted text-center py-3">Sin datos</p>
                    </div>
                </div>
                {{-- Top productos comprados --}}
                <div class="col-md-6">
                    <h6 class="small fw-bold text-uppercase text-muted mb-3">
                        <i class="bi bi-box-seam me-1"></i>Productos comprados
                    </h6>
                    <div id="listaTopProductosC">
                        <p class="text-muted text-center py-3">Sin datos</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- TAB UTILIDAD --}}
        <div class="tab-pane fade p-3" id="tabUtilidad">

            {{-- Fila 1: Estimada vs Real --}}
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-secondary">Utilidad Estimada</span>
                        <small class="text-muted">basada en precio_costo actual del catálogo</small>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="bg-light rounded p-3 text-center">
                        <div class="small text-muted">Utilidad estimada</div>
                        <div class="fw-bold fs-5" style="color:#7c3aed;" id="utilTotal">S/ 0.00</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="bg-light rounded p-3 text-center">
                        <div class="small text-muted">Margen estimado</div>
                        <div class="fw-bold fs-5 text-warning" id="utilMargen">0%</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="bg-light rounded p-3 text-center">
                        <div class="small text-muted">Costo estimado</div>
                        <div class="fw-bold fs-5 text-danger" id="utilInvertido">S/ 0.00</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="bg-light rounded p-3 text-center">
                        <div class="small text-muted">Total ventas</div>
                        <div class="fw-bold fs-5 text-success" id="utilRecuperado">S/ 0.00</div>
                    </div>
                </div>
            </div>

            {{-- Fila 2: Utilidad Real --}}
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-success">Utilidad Real</span>
                        <small class="text-muted">basada en costos congelados al momento de la compra</small>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card border-success-subtle bg-success-subtle rounded p-3 text-center">
                        <div class="small text-muted">Utilidad real</div>
                        <div class="fw-bold fs-5 text-success" id="utilRealTotal">S/ 0.00</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card border-success-subtle bg-success-subtle rounded p-3 text-center">
                        <div class="small text-muted">Margen real</div>
                        <div class="fw-bold fs-5 text-success" id="utilRealMargen">0%</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card border-success-subtle bg-success-subtle rounded p-3 text-center">
                        <div class="small text-muted">Costo real congelado</div>
                        <div class="fw-bold fs-5 text-danger" id="utilRealCosto">S/ 0.00</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card border-success-subtle bg-success-subtle rounded p-3 text-center">
                        <div class="small text-muted">Ingreso costeado</div>
                        <div class="fw-bold fs-5" id="utilRealIngreso">S/ 0.00</div>
                    </div>
                </div>
            </div>

            {{-- Fila 3: Cobertura de costeo --}}
            <div class="card border-0 bg-light mb-3">
                <div class="card-body py-2">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                        <span class="fw-semibold small"><i class="bi bi-shield-check me-1 text-success"></i>Cobertura de costeo</span>
                        <span class="fw-bold" id="utilCoberturaTotal">—</span>
                    </div>
                    <div class="progress mb-2" style="height:10px;" title="% de líneas de venta costeadas">
                        <div class="progress-bar bg-success" id="barCosteada" style="width:0%;"></div>
                        <div class="progress-bar bg-warning" id="barParcial"  style="width:0%;"></div>
                        <div class="progress-bar bg-danger"  id="barSinCostear" style="width:0%;"></div>
                    </div>
                    <div class="d-flex gap-3 flex-wrap" style="font-size:.78rem;">
                        <span><span class="badge bg-success me-1" id="badgeCosteada">0</span>Costeadas</span>
                        <span><span class="badge bg-warning text-dark me-1" id="badgeParcial">0</span>Parciales</span>
                        <span><span class="badge bg-danger me-1" id="badgeSinCostear">0</span>Sin costear</span>
                        <span class="text-muted ms-auto"><span id="badgeTotalLineas">0</span> líneas totales</span>
                    </div>
                </div>
            </div>

            <div class="text-center">
                <button class="btn btn-outline-purple btn-sm" style="border-color:#7c3aed;color:#7c3aed;"
                        onclick="abrirModalUtilidad()">
                    <i class="bi bi-table me-1"></i>Ver detalle por venta
                </button>
            </div>
        </div>

        {{-- TAB REPORTES SEMANALES --}}
        <div class="tab-pane fade p-3" id="tabSemanales">
            <div class="card border-0 bg-light mb-3">
                <div class="card-body">
                    <h6 class="small fw-bold text-uppercase text-muted mb-3">
                        <i class="bi bi-lock me-1"></i>Cerrar semana
                    </h6>
                    <p class="small text-muted mb-3" id="semInicioInfo">Cargando información del período abierto...</p>
                    <div class="row g-2 align-items-end">
                        <div class="col-auto">
                            <label class="form-label small mb-1">Fecha de cierre (fin de período)</label>
                            <input type="date" class="form-control form-control-sm" id="semFechaFin">
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-sm btn-outline-primary" onclick="previsualizarCierre()">
                                <i class="bi bi-eye me-1"></i>Previsualizar
                            </button>
                        </div>
                    </div>

                    <div id="semPreviewBox" class="mt-3" style="display:none;">
                        <div class="row g-2 mb-2">
                            <div class="col-6 col-md-3">
                                <div class="bg-white rounded p-2 text-center border">
                                    <div class="small text-muted">Ventas a archivar</div>
                                    <div class="fw-bold" id="semPvVentas">S/ 0.00</div>
                                    <div class="small text-muted" id="semPvCantVentas">0 ventas</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-white rounded p-2 text-center border">
                                    <div class="small text-muted">Compras a archivar</div>
                                    <div class="fw-bold" id="semPvCompras">S/ 0.00</div>
                                    <div class="small text-muted" id="semPvCantCompras">0 compras</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-white rounded p-2 text-center border">
                                    <div class="small text-muted">Utilidad</div>
                                    <div class="fw-bold text-purple" style="color:#7c3aed;" id="semPvUtilidad">S/ 0.00</div>
                                    <div class="small text-muted" id="semPvMargen">0%</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-white rounded p-2 text-center border">
                                    <div class="small text-muted">Comisión Utilidad</div>
                                    <div class="fw-bold text-warning" id="semPvComision">S/ 0.00</div>
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-warning small py-2 mb-3" id="semPvPendientesAlert" style="display:none;">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            <span id="semPvPendientesTexto"></span> venta(s) no anuladas, no pagadas o sin costeo completo
                            <strong>no se archivarán</strong> y quedarán visibles para la siguiente semana.
                        </div>
                        <button class="btn btn-sm btn-danger" onclick="confirmarCierre()">
                            <i class="bi bi-lock-fill me-1"></i>Confirmar y cerrar semana
                        </button>
                    </div>
                </div>
            </div>

            <h6 class="small fw-bold text-uppercase text-muted mb-3">
                <i class="bi bi-archive me-1"></i>Semanas cerradas
            </h6>
            <div class="table-responsive">
                <table class="table table-sm rpt-table">
                    <thead class="table-light">
                        <tr>
                            <th>Período</th>
                            <th class="text-end">Ventas</th>
                            <th class="text-end">Compras</th>
                            <th class="text-end">Utilidad</th>
                            <th class="text-end">Comisión</th>
                            <th class="text-center">Pendientes</th>
                            <th>Cerrado por</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="tbodySemanales">
                        <tr><td colspan="8" class="text-center text-muted py-3">Sin semanas cerradas aún</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /tab-content -->
</div><!-- /rptContent -->

{{-- ── MODAL UTILIDAD ───────────────────────────────────── --}}
<div class="modal fade" id="modalUtilidad" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#7c3aed,#a855f7);color:#fff;">
                <h5 class="modal-title"><i class="bi bi-graph-up-arrow me-2"></i>Detalle de Utilidad</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="modalUtilidadLoading" class="text-center py-5">
                    <div class="spinner-border text-purple" style="color:#7c3aed;"></div>
                    <p class="text-muted mt-2">Cargando detalle...</p>
                </div>
                <div id="modalUtilidadContent" style="display:none;">
                    <div class="p-3 pb-0">
                        <input type="text" class="form-control form-control-sm" id="buscarUtilidad"
                               placeholder="Buscar por cliente, número de venta...">
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm rpt-table mb-0" id="tablaUtilidad">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>N° Venta</th>
                                    <th>Fecha</th>
                                    <th>Cliente</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-end">Ganancia</th>
                                    <th style="width:2rem;"></th>
                                </tr>
                            </thead>
                            <tbody id="tbodyUtilidad"></tbody>
                            <tfoot class="table-light fw-bold" id="tfootUtilidad"></tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <small class="text-muted" id="modalUtilidadResumen">—</small>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

{{-- ── MODAL VENTAS ──────────────────────────────────────── --}}
<div class="modal fade" id="modalVentas" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#2563eb,#3b82f6);color:#fff;">
                <h5 class="modal-title"><i class="bi bi-cart3 me-2"></i>Detalle de Ventas</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="modalVentasLoading" class="text-center py-5">
                    <div class="spinner-border text-primary"></div>
                    <p class="text-muted mt-2">Cargando detalle...</p>
                </div>
                <div id="modalVentasContent" style="display:none;">
                    <div class="p-3 pb-0">
                        <input type="text" class="form-control form-control-sm" id="buscarVentas"
                               placeholder="Buscar por cliente, número de venta...">
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm rpt-table mb-0" id="tablaVentasModal">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>N° Venta</th>
                                    <th>Fecha</th>
                                    <th>Cliente</th>
                                    <th>Método pago</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyVentasModal"></tbody>
                            <tfoot class="table-light fw-bold" id="tfootVentasModal"></tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <small class="text-muted" id="modalVentasResumen">—</small>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

{{-- ── MODAL COMPRAS ─────────────────────────────────────── --}}
<div class="modal fade" id="modalCompras" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#059669,#10b981);color:#fff;">
                <h5 class="modal-title"><i class="bi bi-box-seam me-2"></i>Detalle de Compras</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="modalComprasLoading" class="text-center py-5">
                    <div class="spinner-border text-success"></div>
                    <p class="text-muted mt-2">Cargando detalle...</p>
                </div>
                <div id="modalComprasContent" style="display:none;">
                    <div class="p-3 pb-0">
                        <input type="text" class="form-control form-control-sm" id="buscarCompras"
                               placeholder="Buscar por proveedor, número...">
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm rpt-table mb-0" id="tablaComprasModal">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>N°</th>
                                    <th>Fecha</th>
                                    <th>Proveedor</th>
                                    <th>Método pago</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyComprasModal"></tbody>
                            <tfoot class="table-light fw-bold" id="tfootComprasModal"></tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <small class="text-muted" id="modalComprasResumen">—</small>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
/* ── Estado global ──────────────────────────────────── */
let periodoActual  = 'diario';
let datosActuales  = null;
let chartVentas    = null;
let chartMetodos   = null;
let modalUtilidad  = null;
let modalVentas    = null;
let modalCompras   = null;

const fmt = n => 'S/ ' + parseFloat(n || 0).toLocaleString('es-PE', {minimumFractionDigits:2, maximumFractionDigits:2});
const fmtN = n => parseFloat(n || 0).toLocaleString('es-PE', {minimumFractionDigits:2, maximumFractionDigits:2});

/* ── Inicialización ─────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    modalUtilidad = new bootstrap.Modal(document.getElementById('modalUtilidad'));
    modalVentas   = new bootstrap.Modal(document.getElementById('modalVentas'));
    modalCompras  = new bootstrap.Modal(document.getElementById('modalCompras'));

    document.querySelectorAll('.tab-periodo').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-periodo').forEach(b => {
                b.classList.remove('btn-primary');
                b.classList.add('btn-outline-primary');
            });
            btn.classList.add('btn-primary');
            btn.classList.remove('btn-outline-primary');
            periodoActual = btn.dataset.periodo;
            cargarDatos();
        });
    });

    cargarDatos();
});

/* ── Carga de datos principal ───────────────────────── */
function cargarDatos() {
    document.getElementById('rptLoading').style.display  = 'block';
    document.getElementById('rptContent').style.display  = 'none';

    const params = new URLSearchParams({
        periodo:     periodoActual,
        desde:       document.getElementById('fDesde').value,
        hasta:       document.getElementById('fHasta').value,
        vendedor_id: document.getElementById('fVendedor').value,
        metodo_pago: document.getElementById('fMetodo').value,
        cliente_id:  document.getElementById('fCliente').value,
    });

    fetch('/reportes/datos?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(async r => {
        if (!r.ok) {
            const text = await r.text();
            throw new Error('HTTP ' + r.status + ': ' + text.substring(0, 300));
        }
        return r.json();
    })
    .then(data => {
        if (data.error) throw new Error(data.error);
        datosActuales = data;
        renderDatos(data);
        document.getElementById('rptLoading').style.display = 'none';
        document.getElementById('rptContent').style.display = 'block';
    })
    .catch(err => {
        console.error('[Reportes] Error:', err.message);
        document.getElementById('rptLoading').innerHTML =
            '<div class="alert alert-danger mx-3 mt-3"><strong>Error al cargar el reporte:</strong><br><code style="font-size:.75rem;white-space:pre-wrap;">' +
            err.message.replace(/</g,'&lt;').replace(/>/g,'&gt;') +
            '</code></div>';
    });
}

function limpiarFiltros() {
    document.getElementById('fDesde').value    = '';
    document.getElementById('fHasta').value    = '';
    document.getElementById('fVendedor').value = '';
    document.getElementById('fMetodo').value   = '';
    document.getElementById('fCliente').value  = '';
    cargarDatos();
}

/* ── Render general ─────────────────────────────────── */
function renderDatos(data) {
    const v = data.ventas;
    const c = data.compras;
    const u = data.utilidad;
    const periodoLabel = { diario:'Hoy', semanal:'Esta semana', mensual:'Este mes' }[data.periodo] ?? data.periodo;

    document.getElementById('rptSubtitulo').textContent =
        periodoLabel + ' · ' + data.desde + ' — ' + data.hasta;

    // KPI cards
    document.getElementById('kpiTotalVentas').textContent   = fmt(v.total);
    document.getElementById('kpiCantVentas').textContent    = v.cantidad;
    document.getElementById('kpiIgvVentas').textContent     = fmt(v.igv);
    document.getElementById('kpiSubtotalVentas').textContent= fmt(v.subtotal);

    document.getElementById('kpiTotalCompras').textContent  = fmt(c.total);
    document.getElementById('kpiCantCompras').textContent   = c.cantidad;
    document.getElementById('kpiIgvCompras').textContent    = fmt(c.igv);

    document.getElementById('kpiUtilidad').textContent      = fmt(u.utilidad);
    document.getElementById('kpiCosto').textContent         = fmt(u.total_costo);
    document.getElementById('kpiVentasUtilidad').textContent= fmt(u.total_ventas);

    document.getElementById('kpiMargen').textContent        = fmt(u.comision_utilidad ?? 0);
    document.getElementById('kpiInvertido').textContent     = fmt(u.utilidad);
    document.getElementById('kpiRecuperado').textContent    = u.margen + '%';

    // Tab Compras KPIs
    document.getElementById('cmpTotal').textContent = fmt(c.total);
    document.getElementById('cmpCant').textContent  = c.cantidad;
    document.getElementById('cmpIgv').textContent   = fmt(c.igv);
    document.getElementById('cmpBase').textContent  = fmt(c.neto / 1.18);

    // Tab Utilidad — Estimada
    document.getElementById('utilTotal').textContent     = fmt(u.utilidad);
    document.getElementById('utilMargen').textContent    = u.margen + '%';
    document.getElementById('utilInvertido').textContent = fmt(u.total_costo);
    document.getElementById('utilRecuperado').textContent= fmt(u.total_ventas);

    // Tab Utilidad — Real (FASE 4)
    document.getElementById('utilRealTotal').textContent  = fmt(u.utilidad_real  ?? 0);
    document.getElementById('utilRealMargen').textContent = (u.margen_real ?? 0) + '%';
    document.getElementById('utilRealCosto').textContent  = fmt(u.costo_real     ?? 0);
    document.getElementById('utilRealIngreso').textContent= fmt(u.ingreso_real   ?? 0);

    // Cobertura de costeo (FASE 2)
    const cob = u.cobertura ?? { total:0, sin_costear:0, parcial:0, costeada:0, pct:0 };
    const total = cob.total || 1;
    document.getElementById('utilCoberturaTotal').textContent  = cob.pct + '% costeado';
    document.getElementById('barCosteada').style.width         = Math.round(cob.costeada   / total * 100) + '%';
    document.getElementById('barParcial').style.width          = Math.round(cob.parcial    / total * 100) + '%';
    document.getElementById('barSinCostear').style.width       = Math.round(cob.sin_costear/ total * 100) + '%';
    document.getElementById('badgeCosteada').textContent       = cob.costeada;
    document.getElementById('badgeParcial').textContent        = cob.parcial;
    document.getElementById('badgeSinCostear').textContent     = cob.sin_costear;
    document.getElementById('badgeTotalLineas').textContent    = cob.total;

    // Gráficos
    renderChartVentas(v.por_dia, periodoLabel);
    renderChartMetodos(v.por_metodo);
    renderTablaPorDia(v.por_dia, u.comision_total ?? 0);

    // Listas
    renderBarras('listaTopClientes',   v.top_clientes,   'nombre', 'total', '#2563eb');
    renderBarras('listaTopProductosV', v.top_productos,  'nombre', 'total', '#059669');
    renderBarras('listaProveedores',   c.por_proveedor,  'proveedor', 'total', '#d97706');
    renderBarras('listaTopProductosC', c.top_productos,  'nombre', 'total', '#7c3aed');
    renderBarras('listaMetodos',       v.por_metodo,     'metodo', 'total', '#0891b2');

    // Comprobantes
    const tbody = document.getElementById('tablaComprobantes');
    if (!v.comprobantes.length) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">Sin datos</td></tr>';
    } else {
        tbody.innerHTML = v.comprobantes.map(c =>
            `<tr>
                <td>${ucfirst(c.tipo)}</td>
                <td><span class="badge bg-light text-dark border">${c.count}</span></td>
                <td class="text-end fw-semibold">${fmt(c.total)}</td>
            </tr>`
        ).join('');
    }
}

/* ── Tabla Ventas por día con totales ───────────────────── */
function renderTablaPorDia(porDia, comisionTotal) {
    const tbody = document.getElementById('tbodyPorDia');
    const tfoot = document.getElementById('tfootPorDia');
    if (!porDia || !porDia.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Sin datos en este período</td></tr>';
        tfoot.innerHTML = '';
        return;
    }

    let sumVentas = 0, sumCount = 0, sumCosto = 0, sumUtil = 0;
    tbody.innerHTML = porDia.map(d => {
        sumCount  += d.count;
        sumVentas += d.total;
        sumCosto  += d.costo   ?? 0;
        sumUtil   += d.utilidad ?? 0;
        const util   = d.utilidad ?? 0;
        const costo  = d.costo   ?? 0;
        const cls    = util >= 0 ? 'text-success' : 'text-danger';
        return `<tr>
            <td>${d.fecha}</td>
            <td class="text-center"><span class="badge bg-light text-dark border">${d.count}</span></td>
            <td class="text-end">${fmt(d.total)}</td>
            <td class="text-end text-danger">${fmt(costo)}</td>
            <td class="text-end ${cls}">${fmt(util)}</td>
            <td class="text-end text-muted">—</td>
        </tr>`;
    }).join('');

    const clsTotal = sumUtil >= 0 ? 'text-success' : 'text-danger';
    tfoot.innerHTML = `<tr>
        <td class="fw-bold">TOTAL</td>
        <td class="text-center fw-bold">${sumCount}</td>
        <td class="text-end fw-bold">${fmt(sumVentas)}</td>
        <td class="text-end text-danger fw-bold">${fmt(sumCosto)}</td>
        <td class="text-end ${clsTotal} fw-bold">${fmt(sumUtil)}</td>
        <td class="text-end fw-bold text-primary">${fmt(comisionTotal)}</td>
    </tr>`;
}

/* ── Gráfico mixto: Ventas (barras) + Utilidad (línea) ── */
function renderChartVentas(porDia, label) {
    const ctx = document.getElementById('chartVentas').getContext('2d');
    document.getElementById('chartLabel').textContent = label;

    const labels   = porDia.map(d => {
        const f = new Date(d.fecha + 'T00:00:00');
        return f.toLocaleDateString('es-PE', { day:'2-digit', month:'short' });
    });
    const ventas   = porDia.map(d => d.total);
    const utilidad = porDia.map(d => d.utilidad);

    if (chartVentas) chartVentas.destroy();
    chartVentas = new Chart(ctx, {
        data: {
            labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Ventas',
                    data: ventas,
                    backgroundColor: 'rgba(37,99,235,.7)',
                    borderColor: '#2563eb',
                    borderWidth: 1,
                    borderRadius: 4,
                    yAxisID: 'y',
                },
                {
                    type: 'line',
                    label: 'Utilidad',
                    data: utilidad,
                    borderColor: '#7c3aed',
                    backgroundColor: 'rgba(124,58,237,.1)',
                    borderWidth: 2,
                    pointRadius: 4,
                    pointBackgroundColor: '#7c3aed',
                    tension: 0.35,
                    fill: true,
                    yAxisID: 'y',
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: { font: { size: 11 }, boxWidth: 12, padding: 12 }
                },
                tooltip: {
                    callbacks: {
                        label: ctx => ` S/ ${fmtN(ctx.raw)} (${ctx.dataset.label})`
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: v => 'S/ ' + fmtN(v), font: { size: 10 } },
                    grid: { color: 'rgba(0,0,0,.04)' }
                },
                x: { ticks: { font: { size: 10 } }, grid: { display: false } }
            }
        }
    });
}

/* ── Gráfico: Métodos de pago ───────────────────────── */
function renderChartMetodos(porMetodo) {
    const ctx = document.getElementById('chartMetodos').getContext('2d');
    const colores = ['#2563eb','#059669','#d97706','#dc2626','#7c3aed','#0891b2'];

    if (chartMetodos) chartMetodos.destroy();

    if (!porMetodo.length) {
        chartMetodos = null; return;
    }

    chartMetodos = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: porMetodo.map(m => ucfirst(m.metodo)),
            datasets: [{
                data: porMetodo.map(m => m.total),
                backgroundColor: colores.slice(0, porMetodo.length),
                borderWidth: 2,
                borderColor: '#fff',
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 10 } },
                tooltip: {
                    callbacks: {
                        label: ctx => ` S/ ${fmtN(ctx.raw)} (${ctx.label})`
                    }
                }
            }
        }
    });
}

/* ── Render barras de lista ─────────────────────────── */
function renderBarras(containerId, lista, keyLabel, keyVal, color) {
    const el = document.getElementById(containerId);
    if (!lista || !lista.length) {
        el.innerHTML = '<p class="text-muted text-center py-2" style="font-size:.82rem;">Sin datos en este período</p>';
        return;
    }
    const max = Math.max(...lista.map(i => i[keyVal])) || 1;
    el.innerHTML = lista.map(item => {
        const pct = Math.round(item[keyVal] / max * 100);
        return `<div class="rpt-bar-row">
            <div class="rpt-bar-label text-truncate" title="${item[keyLabel]}">${item[keyLabel]}</div>
            <div class="rpt-bar-fill">
                <div class="rpt-bar-fill-inner" style="width:${pct}%;background:${color};"></div>
            </div>
            <div class="rpt-bar-val text-muted">${fmt(item[keyVal])}</div>
        </div>`;
    }).join('');
}

/* ── Modal Utilidad ─────────────────────────────────── */
function abrirModalUtilidad() {
    modalUtilidad.show();
    document.getElementById('modalUtilidadLoading').style.display = 'block';
    document.getElementById('modalUtilidadContent').style.display = 'none';

    const params = new URLSearchParams({
        periodo:     periodoActual,
        desde:       document.getElementById('fDesde').value,
        hasta:       document.getElementById('fHasta').value,
        vendedor_id: document.getElementById('fVendedor').value,
        metodo_pago: document.getElementById('fMetodo').value,
        cliente_id:  document.getElementById('fCliente').value,
    });

    fetch('/reportes/utilidad-detalle?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        renderModalUtilidad(data.detalle);
        document.getElementById('modalUtilidadLoading').style.display = 'none';
        document.getElementById('modalUtilidadContent').style.display = 'block';
    })
    .catch(() => {
        document.getElementById('modalUtilidadLoading').innerHTML =
            '<p class="text-danger text-center">Error al cargar.</p>';
    });
}

let detalleUtilidad = [];

function renderModalUtilidad(detalle) {
    detalleUtilidad = detalle;
    filtrarModalUtilidad('');

    document.getElementById('buscarUtilidad').addEventListener('input', function() {
        filtrarModalUtilidad(this.value.toLowerCase());
    });
}

function filtrarModalUtilidad(q) {
    const lista = q
        ? detalleUtilidad.filter(v =>
            v.numero.toLowerCase().includes(q) ||
            v.cliente.toLowerCase().includes(q))
        : detalleUtilidad;

    const tbody = document.getElementById('tbodyUtilidad');
    const tfoot = document.getElementById('tfootUtilidad');

    if (!lista.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Sin resultados</td></tr>';
        tfoot.innerHTML = '';
        document.getElementById('modalUtilidadResumen').textContent = '0 ventas';
        return;
    }

    tbody.innerHTML = lista.map((v, i) => {
        const colGan = v.ganancia >= 0 ? 'text-success' : 'text-danger';
        const lineasHtml = v.lineas.map(l => `
            <tr class="table-light" style="font-size:.78rem;">
                <td colspan="2" class="ps-4 text-muted">${l.codigo ? '<span class=\'text-muted\'>['+l.codigo+']</span> ' : ''}${l.producto}</td>
                <td class="text-muted">×${fmtN(l.cantidad)}</td>
                <td class="text-end text-muted">${fmt(l.precio_venta)}</td>
                <td class="text-end ${l.ganancia >= 0 ? 'text-success' : 'text-danger'} fw-semibold">
                    ${fmt(l.ganancia)}
                    ${l.costo > 0 ? '<br><span class=\'text-muted\' style=\'font-size:.7rem;\'>Costo: '+fmt(l.costo)+'</span>' : ''}
                </td>
                <td></td>
            </tr>`).join('');

        return `
        <tr class="fw-semibold" style="cursor:pointer;" onclick="toggleLineasUtilidad(${i})">
            <td>${v.numero}</td>
            <td>${v.fecha}</td>
            <td>${v.cliente}</td>
            <td class="text-end">${fmt(v.total)}</td>
            <td class="text-end ${colGan}">${fmt(v.ganancia)}</td>
            <td class="text-center text-muted">
                <i class="bi bi-chevron-right" id="iconUtil${i}"></i>
            </td>
        </tr>
        <tr class="collapse-lineas" id="lineasUtil${i}" style="display:none;">
            <td colspan="6" class="p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-secondary" style="font-size:.7rem;">
                        <tr>
                            <th colspan="2">Producto</th><th>Cant.</th>
                            <th class="text-end">Precio venta</th>
                            <th class="text-end">Ganancia</th><th></th>
                        </tr>
                    </thead>
                    <tbody>${lineasHtml}</tbody>
                </table>
            </td>
        </tr>`;
    }).join('');

    const totalG = lista.reduce((s, v) => s + v.ganancia, 0);
    const totalV = lista.reduce((s, v) => s + v.total, 0);
    tfoot.innerHTML = `
        <tr>
            <td colspan="3" class="text-end text-muted">Totales (${lista.length} ventas)</td>
            <td class="text-end">${fmt(totalV)}</td>
            <td class="text-end ${totalG >= 0 ? 'text-success' : 'text-danger'}">${fmt(totalG)}</td>
            <td></td>
        </tr>`;

    document.getElementById('modalUtilidadResumen').textContent =
        `${lista.length} ventas · Utilidad total: S/ ${fmtN(totalG)}`;
}

function toggleLineasUtilidad(i) {
    const row  = document.getElementById('lineasUtil' + i);
    const icon = document.getElementById('iconUtil' + i);
    const open = row.style.display === 'table-row';
    row.style.display  = open ? 'none' : 'table-row';
    icon.className     = open ? 'bi bi-chevron-right' : 'bi bi-chevron-down text-primary';
}

/* ── Modal Ventas ────────────────────────────────────── */
let detalleVentasModal = [];

function abrirModalVentas() {
    modalVentas.show();
    document.getElementById('modalVentasLoading').style.display = 'block';
    document.getElementById('modalVentasContent').style.display = 'none';

    fetch('/reportes/ventas-detalle?' + _params(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        detalleVentasModal = data.detalle;
        filtrarModalVentas('');
        document.getElementById('modalVentasLoading').style.display = 'none';
        document.getElementById('modalVentasContent').style.display = 'block';
        document.getElementById('buscarVentas').value = '';
        document.getElementById('buscarVentas').oninput = function() {
            filtrarModalVentas(this.value.toLowerCase());
        };
    })
    .catch(() => {
        document.getElementById('modalVentasLoading').innerHTML =
            '<p class="text-danger text-center">Error al cargar.</p>';
    });
}

function filtrarModalVentas(q) {
    const lista = q
        ? detalleVentasModal.filter(v =>
            v.numero.toLowerCase().includes(q) ||
            v.cliente.toLowerCase().includes(q))
        : detalleVentasModal;

    const tbody = document.getElementById('tbodyVentasModal');
    const tfoot = document.getElementById('tfootVentasModal');

    if (!lista.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Sin resultados</td></tr>';
        tfoot.innerHTML = '';
        document.getElementById('modalVentasResumen').textContent = '0 ventas';
        return;
    }

    tbody.innerHTML = lista.map(v => `
        <tr>
            <td>${v.numero}</td>
            <td>${v.fecha}</td>
            <td>${v.cliente}</td>
            <td><span class="badge bg-light text-dark border">${v.metodo_pago}</span></td>
            <td class="text-end fw-semibold">${fmt(v.total)}</td>
        </tr>`).join('');

    const totalV = lista.reduce((s, v) => s + v.total, 0);
    tfoot.innerHTML = `
        <tr>
            <td colspan="4" class="text-end text-muted">Totales (${lista.length} ventas)</td>
            <td class="text-end">${fmt(totalV)}</td>
        </tr>`;

    document.getElementById('modalVentasResumen').textContent =
        `${lista.length} ventas · Total: S/ ${fmtN(totalV)}`;
}

/* ── Modal Compras ───────────────────────────────────── */
let detalleComprasModal = [];

function abrirModalCompras() {
    modalCompras.show();
    document.getElementById('modalComprasLoading').style.display = 'block';
    document.getElementById('modalComprasContent').style.display = 'none';

    fetch('/reportes/compras-detalle?' + _params(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        detalleComprasModal = data.detalle;
        filtrarModalCompras('');
        document.getElementById('modalComprasLoading').style.display = 'none';
        document.getElementById('modalComprasContent').style.display = 'block';
        document.getElementById('buscarCompras').value = '';
        document.getElementById('buscarCompras').oninput = function() {
            filtrarModalCompras(this.value.toLowerCase());
        };
    })
    .catch(() => {
        document.getElementById('modalComprasLoading').innerHTML =
            '<p class="text-danger text-center">Error al cargar.</p>';
    });
}

function filtrarModalCompras(q) {
    const lista = q
        ? detalleComprasModal.filter(c =>
            c.numero.toLowerCase().includes(q) ||
            c.proveedor.toLowerCase().includes(q))
        : detalleComprasModal;

    const tbody = document.getElementById('tbodyComprasModal');
    const tfoot = document.getElementById('tfootComprasModal');

    if (!lista.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Sin resultados</td></tr>';
        tfoot.innerHTML = '';
        document.getElementById('modalComprasResumen').textContent = '0 compras';
        return;
    }

    tbody.innerHTML = lista.map(c => `
        <tr>
            <td>${c.numero}</td>
            <td>${c.fecha}</td>
            <td>${c.proveedor}</td>
            <td><span class="badge bg-light text-dark border">${c.metodo_pago}</span></td>
            <td class="text-end fw-semibold">${fmt(c.total)}</td>
        </tr>`).join('');

    const totalC = lista.reduce((s, c) => s + c.total, 0);
    tfoot.innerHTML = `
        <tr>
            <td colspan="4" class="text-end text-muted">Totales (${lista.length} compras)</td>
            <td class="text-end">${fmt(totalC)}</td>
        </tr>`;

    document.getElementById('modalComprasResumen').textContent =
        `${lista.length} compras · Total: S/ ${fmtN(totalC)}`;
}

/* ── Exportar Excel / PDF ───────────────────────────── */
function _params() {
    return new URLSearchParams({
        periodo:     periodoActual,
        desde:       document.getElementById('fDesde').value,
        hasta:       document.getElementById('fHasta').value,
        vendedor_id: document.getElementById('fVendedor').value,
        metodo_pago: document.getElementById('fMetodo').value,
        cliente_id:  document.getElementById('fCliente').value,
    }).toString();
}

function exportarExcel() {
    window.location.href = '/reportes/export-excel?' + _params();
}

function exportarPdf() {
    window.open('/reportes/export-pdf?' + _params(), '_blank');
}

/* ── Reportes Semanales ─────────────────────────────── */
let semInicioSugerido = null;

function cargarSemanales() {
    fetch('/reportes/semanales', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(d => {
            semInicioSugerido = d.inicio_sugerido;
            const info = document.getElementById('semInicioInfo');
            if (semInicioSugerido) {
                info.textContent = `El próximo cierre incluirá desde ${formatearFecha(semInicioSugerido)} hasta la fecha que elijas.`;
                const fin = document.getElementById('semFechaFin');
                if (!fin.value) fin.value = new Date().toISOString().slice(0, 10);
            } else {
                info.textContent = 'No hay ventas ni compras abiertas para archivar.';
            }
            renderTablaSemanales(d.reportes);
        })
        .catch(() => {
            document.getElementById('semInicioInfo').textContent = 'Error al cargar información.';
        });
}

function formatearFecha(iso) {
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
}

function renderTablaSemanales(lista) {
    const tbody = document.getElementById('tbodySemanales');
    if (!lista.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">Sin semanas cerradas aún</td></tr>';
        return;
    }
    tbody.innerHTML = lista.map(r => `
        <tr>
            <td>${r.periodo_inicio} — ${r.periodo_fin}</td>
            <td class="text-end">${fmt(r.total_ventas)}</td>
            <td class="text-end">${fmt(r.total_compras)}</td>
            <td class="text-end">${fmt(r.utilidad)}</td>
            <td class="text-end">${fmt(r.comision_utilidad)}</td>
            <td class="text-center">${r.ventas_pendientes > 0 ? `<span class="badge bg-warning text-dark">${r.ventas_pendientes}</span>` : '<span class="badge bg-success">0</span>'}</td>
            <td>${r.cerrado_por || '—'}</td>
            <td><a class="btn btn-sm btn-outline-success" href="/reportes/semanales/${r.id}/excel" title="Descargar Excel"><i class="bi bi-file-earmark-excel"></i></a></td>
        </tr>
    `).join('');
}

function previsualizarCierre() {
    const fin = document.getElementById('semFechaFin').value;
    if (!fin) { alert('Selecciona una fecha de cierre.'); return; }

    fetch('/reportes/semanales/preview?fin=' + fin, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json().then(d => ({ status: r.status, body: d })))
        .then(({ status, body }) => {
            if (status !== 200) { alert(body.error || 'Error al previsualizar.'); return; }
            const t = body.totales;
            document.getElementById('semPvVentas').textContent      = fmt(t.total_ventas);
            document.getElementById('semPvCantVentas').textContent  = t.cantidad_ventas + ' ventas';
            document.getElementById('semPvCompras').textContent     = fmt(t.total_compras);
            document.getElementById('semPvCantCompras').textContent = t.cantidad_compras + ' compras';
            document.getElementById('semPvUtilidad').textContent    = fmt(t.utilidad);
            document.getElementById('semPvMargen').textContent      = t.margen + '%';
            document.getElementById('semPvComision').textContent    = fmt(t.comision_utilidad);

            const alertBox = document.getElementById('semPvPendientesAlert');
            if (t.ventas_pendientes > 0) {
                document.getElementById('semPvPendientesTexto').textContent = t.ventas_pendientes;
                alertBox.style.display = '';
            } else {
                alertBox.style.display = 'none';
            }
            document.getElementById('semPreviewBox').style.display = '';
        })
        .catch(() => alert('Error al previsualizar el cierre.'));
}

function confirmarCierre() {
    const fin = document.getElementById('semFechaFin').value;
    if (!fin) return;
    if (!confirm('¿Confirmas cerrar la semana hasta el ' + formatearFecha(fin) + '? Esta acción archivará las ventas y compras del período y no se puede deshacer.')) return;

    fetch('/reportes/semanales/cerrar', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ fin }),
    })
        .then(r => r.json().then(d => ({ status: r.status, body: d })))
        .then(({ status, body }) => {
            if (status !== 200) { alert(body.error || 'Error al cerrar la semana.'); return; }
            document.getElementById('semPreviewBox').style.display = 'none';
            cargarSemanales();
            cargarDatos();
            alert('Semana cerrada correctamente.');
        })
        .catch(() => alert('Error al cerrar la semana.'));
}


/* ── Helpers ────────────────────────────────────────── */
function ucfirst(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1).replace(/_/g, ' ');
}
</script>
@endsection
