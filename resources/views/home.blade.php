@extends("layouts.app")

@section("content")

<div class="container-fluid">
    <div class="row">

        <!-- SIDEBAR -->
        <div class="col-md-2 sidebar p-3">
            <h5 class="text-center fw-bold mb-4">Sistema</h5>

            <ul class="nav flex-column">

                <li class="nav-item">
                    <a href="/" class="nav-link {{ request()->is('/') ? 'active' : '' }}">
                        Dashboard
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="collapse" href="#casadetsMenu">
                        CASADETS
                    </a>
                    <div class="collapse {{ request()->is('ingresos*') || request()->is('salidas*') || request()->is('ventas*') ? 'show' : '' }}" id="casadetsMenu">
    <ul class="nav flex-column ms-3">

        <li>
            <a href="/movimientos/create/ingreso" class="nav-link">
                Ingresos
            </a>
        </li>

        <li>
            <a href="/movimientos/create/egreso" class="nav-link">
                Salidas
            </a>
        </li>

        <li>
            <a href="/ventas" class="nav-link {{ request()->is('ventas*') ? 'active' : '' }}">
                Ventas
            </a>
        </li>

    </ul>
</div>
                </li>

                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="collapse" href="#zendyMenu">
                        ZENDY
                    </a>
                    <div class="collapse {{ request()->is('letras*') ? 'show' : '' }}" id="zendyMenu">
                        <ul class="nav flex-column ms-3">
                            <li><a href="/letras" class="nav-link">Pago de letras</a></li>
                        </ul>
                    </div>
                </li>

                <li class="nav-item mt-3">
                    <a href="/reportes" class="nav-link">Reportes</a>
                </li>

            </ul>
        </div>

        <!-- CONTENIDO -->
        <div class="col-md-10 content-area p-4">

            <h4 class="mb-4">Dashboard</h4>

            <!-- INDICADORES -->
            <div class="row g-3">

                <div class="col-md-4">
                    <div class="card kpi-card">
                        <div>Total Ingresos</div>
                        <h5 class="text-success">S/ 5,000</h5>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card kpi-card">
                        <div>Total Salidas</div>
                        <h5 class="text-danger">S/ 2,000</h5>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card kpi-card">
                        <div>Balance</div>
                        <h5 class="text-primary">S/ 3,000</h5>
                    </div>
                </div>

            </div>

            <!-- TABLA -->
            <div class="card mt-4">
                <div class="card-header">
                    Últimos movimientos
                </div>

                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tipo</th>
                                <th>Monto</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Ingreso</td>
                                <td>S/ 1000</td>
                                <td>2026-03-18</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>
</div>

@endsection