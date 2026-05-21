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
        <div class="collapse {{ request()->is('casadets*') || request()->is('movimientos*') ? 'show' : '' }}" id="casadetsMenu">
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
                    <a href="/casadets/clientes" class="nav-link {{ request()->is('casadets/clientes*') ? 'active' : '' }}">
                        <i class="bi bi-person-lines-fill me-2"></i>Clientes
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
                <li>
                    <a href="/zendy/letras" class="nav-link {{ request()->is('zendy/letras*') ? 'active' : '' }}">
                        <i class="bi bi-file-earmark-text me-2"></i>Pago de letras
                    </a>
                </li>
            </ul>
        </div>
    </li>

    <li class="nav-item mt-2">
        <a href="/reportes" class="nav-link {{ request()->is('reportes*') ? 'active' : '' }}">
            <i class="bi bi-bar-chart-line me-2"></i>Reportes
        </a>
    </li>

</ul>
