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
                    <a href="/casadets/pendientes" class="nav-link {{ request()->is('casadets/pendientes*') ? 'active' : '' }}">
                        <i class="bi bi-clock-history me-2"></i>Pendientes
                        @php $nPend = \App\Models\Venta::where('estado','pendiente')->whereDate('fecha','<',today())->count(); @endphp
                        @if($nPend > 0)
                            <span class="badge bg-danger ms-auto">{{ $nPend }}</span>
                        @endif
                    </a>
                </li>
                <li>
                    <a href="/casadets/compras" class="nav-link {{ request()->is('casadets/compras*') ? 'active' : '' }}">
                        <i class="bi bi-bag me-2"></i>Compras
                    </a>
                </li>
                <li>
                    <a href="/casadets/productos" class="nav-link {{ request()->is('casadets/productos*') ? 'active' : '' }}">
                        <i class="bi bi-box-seam me-2"></i>Productos
                        @php $nStockBajo = \App\Models\Producto::where('activo', true)->where('stock_actual', '<=', 0)->count(); @endphp
                        @if($nStockBajo > 0)
                            <span class="badge bg-warning text-dark ms-auto">{{ $nStockBajo }}</span>
                        @endif
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
                    <a href="/casadets/saldos-favor" class="nav-link {{ request()->is('casadets/saldos-favor*') ? 'active' : '' }}">
                        <i class="bi bi-wallet2 me-2"></i>Saldos a favor
                        @php $nSaldos = \App\Models\SaldoFavor::whereIn('estado',['disponible','parcialmente_usado'])->where('monto_disponible','>',0)->distinct('cliente_id')->count('cliente_id'); @endphp
                        @if($nSaldos > 0)
                            <span class="badge bg-info text-dark ms-auto">{{ $nSaldos }}</span>
                        @endif
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
