<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family: DejaVu Sans, Arial, sans-serif; }
body { font-size:11px; color:#1e293b; background:#fff; }
.header { background:#2563eb; color:#fff; padding:16px 20px; margin-bottom:16px; border-radius:4px; }
.header h1 { font-size:18px; margin-bottom:4px; }
.header p  { font-size:11px; opacity:.85; }
.section { margin-bottom:16px; }
.section-title { font-size:10px; font-weight:bold; text-transform:uppercase;
                  letter-spacing:.05em; color:#6c757d; margin-bottom:8px;
                  border-bottom:1px solid #e2e8f0; padding-bottom:4px; }
.kpi-row { display:table; width:100%; border-collapse:separate; border-spacing:8px; margin-bottom:4px; }
.kpi-cell { display:table-cell; width:25%; background:#f8fafc; border:1px solid #e2e8f0;
            border-radius:6px; padding:10px 12px; text-align:center; }
.kpi-label { font-size:9px; text-transform:uppercase; letter-spacing:.04em; color:#6c757d; margin-bottom:4px; }
.kpi-value { font-size:15px; font-weight:bold; }
.kpi-sub   { font-size:9px; color:#9ca3af; margin-top:3px; }
.blue   { color:#2563eb; }
.green  { color:#059669; }
.purple { color:#7c3aed; }
.amber  { color:#d97706; }
.red    { color:#dc2626; }

table { width:100%; border-collapse:collapse; margin-bottom:12px; }
thead th { background:#f1f5f9; font-size:9px; text-transform:uppercase; letter-spacing:.04em;
           color:#6c757d; padding:6px 8px; text-align:left; border-bottom:2px solid #e2e8f0; }
tbody td { padding:5px 8px; border-bottom:1px solid #f1f5f9; font-size:10px; }
tbody tr:nth-child(even) { background:#f8fafc; }
.text-right { text-align:right; }
.text-center { text-align:center; }
.badge { display:inline-block; padding:2px 6px; border-radius:4px; font-size:9px; }
.badge-blue   { background:#eff6ff; color:#2563eb; }
.badge-green  { background:#f0fdf4; color:#059669; }
.badge-purple { background:#fdf4ff; color:#7c3aed; }
.footer { margin-top:20px; text-align:center; font-size:9px; color:#9ca3af; border-top:1px solid #e2e8f0; padding-top:8px; }
.utilidad-box { background:linear-gradient(135deg,#fdf4ff,#fff); border:1px solid #e9d5ff;
                border-radius:8px; padding:12px 16px; margin-bottom:12px; }
.utilidad-box .u-title { font-size:10px; font-weight:bold; color:#7c3aed; margin-bottom:8px;
                          text-transform:uppercase; letter-spacing:.05em; }
.u-grid { display:table; width:100%; }
.u-cell { display:table-cell; width:33.33%; padding:4px 8px; }
.u-cell .u-label { font-size:9px; color:#6c757d; }
.u-cell .u-val   { font-size:13px; font-weight:bold; }
</style>
</head>
<body>

{{-- Header --}}
<div class="header">
    <h1>📊 Reporte {{ ucfirst($periodo) }}</h1>
    <p>Período: {{ $desde->format('d/m/Y') }} — {{ $hasta->format('d/m/Y') }} &nbsp;·&nbsp;
       Generado: {{ now()->format('d/m/Y H:i') }}</p>
</div>

{{-- KPIs --}}
<div class="section">
    <div class="section-title">Resumen Ejecutivo</div>
    <div class="kpi-row">
        <div class="kpi-cell">
            <div class="kpi-label">Total Ventas</div>
            <div class="kpi-value blue">S/ {{ number_format($totalVentas, 2) }}</div>
            <div class="kpi-sub">{{ $cantVentas }} ventas · IGV S/ {{ number_format($igvVentas, 2) }}</div>
        </div>
        <div class="kpi-cell">
            <div class="kpi-label">Total Compras</div>
            <div class="kpi-value green">S/ {{ number_format($totalCompras, 2) }}</div>
            <div class="kpi-sub">{{ $cantCompras }} compras · IGV S/ {{ number_format($igvCompras, 2) }}</div>
        </div>
        <div class="kpi-cell">
            <div class="kpi-label">Utilidad</div>
            <div class="kpi-value {{ $utilidad >= 0 ? 'purple' : 'red' }}">S/ {{ number_format($utilidad, 2) }}</div>
            <div class="kpi-sub">Costo: S/ {{ number_format($totalCosto, 2) }}</div>
        </div>
        <div class="kpi-cell">
            <div class="kpi-label">Margen</div>
            <div class="kpi-value amber">{{ $margen }}%</div>
            <div class="kpi-sub">Ventas − Costo / Ventas</div>
        </div>
    </div>
</div>

{{-- Utilidad detalle --}}
<div class="utilidad-box">
    <div class="u-title">💰 Utilidad</div>
    <div class="u-grid">
        <div class="u-cell">
            <div class="u-label">Total ventas (ingresos)</div>
            <div class="u-val blue">S/ {{ number_format($totalVentas, 2) }}</div>
        </div>
        <div class="u-cell">
            <div class="u-label">Costo de productos</div>
            <div class="u-val red">S/ {{ number_format($totalCosto, 2) }}</div>
        </div>
        <div class="u-cell">
            <div class="u-label">Utilidad neta</div>
            <div class="u-val {{ $utilidad >= 0 ? 'purple' : 'red' }}">S/ {{ number_format($utilidad, 2) }}</div>
        </div>
    </div>
</div>

{{-- Top Clientes --}}
@if($topClientes->count())
<div class="section">
    <div class="section-title">Top Clientes</div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Cliente</th>
                <th class="text-center">Compras</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($topClientes as $i => $c)
            <tr>
                <td class="text-center"><span class="badge badge-blue">{{ $i+1 }}</span></td>
                <td>{{ $c->nombre }}</td>
                <td class="text-center">{{ $c->count }}</td>
                <td class="text-right"><strong>S/ {{ number_format($c->total, 2) }}</strong></td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Top Productos --}}
@if($topProductos->count())
<div class="section">
    <div class="section-title">Top Productos Vendidos</div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Producto</th>
                <th class="text-center">Cantidad</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($topProductos as $i => $p)
            <tr>
                <td class="text-center"><span class="badge badge-green">{{ $i+1 }}</span></td>
                <td>{{ $p->nombre }}</td>
                <td class="text-center">{{ number_format($p->cantidad, 2) }}</td>
                <td class="text-right"><strong>S/ {{ number_format($p->total, 2) }}</strong></td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<div class="footer">
    Reporte generado automáticamente por el sistema &mdash; {{ now()->format('d/m/Y H:i:s') }}
</div>
</body>
</html>
