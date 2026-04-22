<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Sistema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('estyle.css') }}">
</head>
<body>

<div class="container-fluid">
    <div class="row">

        <!-- SIDEBAR -->
        <div class="col-md-2 sidebar p-3">
            <h5 class="text-center fw-bold mb-4 text-white">Sistema</h5>

            <ul class="nav flex-column">

                <li class="nav-item">
                    <a href="/" class="nav-link {{ request()->is('/') ? 'active' : '' }}">
                        <i class="bi bi-speedometer2 me-2"></i>Dashboard
                    </a>
                </li>

                <!-- CASADETS -->
                <li class="nav-item mt-2">
                    <a class="nav-link sidebar-section" data-bs-toggle="collapse" href="#casadetsMenu">
                        <i class="bi bi-shop me-2"></i>CASADETS
                        <i class="bi bi-chevron-down ms-auto"></i>
                    </a>
                    <div class="collapse {{ request()->is('casadets*') || request()->is('ventas*') || request()->is('vendedores*') || request()->is('movimientos*') || request()->is('caja*') ? 'show' : '' }}" id="casadetsMenu">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li>
                                <a href="/casadets/caja" class="nav-link {{ request()->is('casadets/caja*') ? 'active' : '' }}">
                                    <i class="bi bi-cash-coin me-2"></i>Caja
                                </a>
                            </li>
                            <li>
                                <a href="/casadets/ventas" class="nav-link {{ request()->is('casadets/ventas*') ? 'active' : '' }}">
                                    <i class="bi bi-cart3 me-2"></i>Ventas
                                </a>
                            </li>
                            <li>
                                <a href="/casadets/compras" class="nav-link {{ request()->is('casadets/compras*') ? 'active' : '' }}">
                                    <i class="bi bi-bag me-2"></i>Compras
                                </a>
                            </li>
                            <li>
                                <a href="/casadets/vendedores" class="nav-link {{ request()->is('casadets/vendedores*') ? 'active' : '' }}">
                                    <i class="bi bi-people me-2"></i>Vendedores
                                </a>
                            </li>
                            <li>
                                <a href="/movimientos" class="nav-link {{ request()->is('movimientos*') ? 'active' : '' }}">
                                    <i class="bi bi-arrow-left-right me-2"></i>Movimientos
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>

                <!-- ZENDY -->
                <li class="nav-item mt-2">
                    <a class="nav-link sidebar-section" data-bs-toggle="collapse" href="#zendyMenu">
                        <i class="bi bi-building me-2"></i>ZENDY
                        <i class="bi bi-chevron-down ms-auto"></i>
                    </a>
                    <div class="collapse {{ request()->is('zendy*') ? 'show' : '' }}" id="zendyMenu">
                        <ul class="nav flex-column ms-3 mt-1">
                            <li><a href="/zendy/letras" class="nav-link {{ request()->is('zendy/letras*') ? 'active' : '' }}">
                                <i class="bi bi-file-earmark-text me-2"></i>Pago de letras
                            </a></li>
                        </ul>
                    </div>
                </li>

                <li class="nav-item mt-2">
                    <a href="/reportes" class="nav-link {{ request()->is('reportes*') ? 'active' : '' }}">
                        <i class="bi bi-bar-chart-line me-2"></i>Reportes
                    </a>
                </li>

            </ul>
        </div>

        <!-- CONTENIDO -->
        <div class="col-md-10 content-area p-4">

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
