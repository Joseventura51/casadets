@php $user = auth()->user(); @endphp
<ul class="nav flex-column">

    @if(!$user || $user->puedeVer('dashboard'))
    <li class="nav-item">
        <a href="/" class="nav-link {{ request()->is('/') ? 'active' : '' }}">
            <i class="bi bi-speedometer2 me-2"></i>Dashboard
        </a>
    </li>
    @endif

    <!-- CASADETS -->
    @php
        $showCasadets = $user && (
            $user->puedeVer('caja') || $user->puedeVer('ventas') || $user->puedeVer('pendientes') ||
            $user->puedeVer('compras') || $user->puedeVer('productos') || $user->puedeVer('clientes') ||
            $user->puedeVer('vendedores') || $user->puedeVer('saldos-favor') || $user->puedeVer('movimientos')
        );
    @endphp

    @if($showCasadets)
    <li class="nav-item mt-2">
        <a class="nav-link sidebar-section" data-bs-toggle="collapse" href="#casadetsMenu">
            <i class="bi bi-shop me-2"></i>GESTION
            <i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <div class="collapse {{ request()->is('casadets*') || request()->is('movimientos*') ? 'show' : '' }}" id="casadetsMenu">
            <ul class="nav flex-column ms-3 mt-1">

                @if($user->puedeVer('caja'))
                <li>
                    <a href="/casadets/caja" class="nav-link {{ request()->is('casadets/caja*') ? 'active' : '' }}">
                        <i class="bi bi-cash-coin me-2"></i>Caja
                    </a>
                </li>
                @endif

                @if($user->puedeVer('ventas'))
                <li>
                    <a href="/casadets/ventas" class="nav-link {{ request()->is('casadets/ventas*') ? 'active' : '' }}">
                        <i class="bi bi-cart3 me-2"></i>Ventas
                    </a>
                </li>
                @endif

                @if($user->puedeVer('pendientes'))
                <li>
                    <a href="/casadets/pendientes" class="nav-link {{ request()->is('casadets/pendientes*') ? 'active' : '' }}">
                        <i class="bi bi-clock-history me-2"></i>Pendientes
                        @php $nPend = \App\Models\Venta::where('estado','pendiente')->whereDate('fecha','<',today())->count(); @endphp
                        @if($nPend > 0)
                            <span class="badge bg-danger ms-auto">{{ $nPend }}</span>
                        @endif
                    </a>
                </li>
                @endif

                @if($user->puedeVer('compras'))
                <li>
                    <a href="/casadets/compras" class="nav-link {{ request()->is('casadets/compras*') ? 'active' : '' }}">
                        <i class="bi bi-bag me-2"></i>Compras
                    </a>
                </li>
                @endif

                @if($user->puedeVer('productos'))
                <li>
                    <a href="/casadets/productos" class="nav-link {{ request()->is('casadets/productos*') ? 'active' : '' }}">
                        <i class="bi bi-box-seam me-2"></i>Productos
                        @php $nStockBajo = \App\Models\Producto::where('activo', true)->where('stock_actual', '<=', 0)->count(); @endphp
                        @if($nStockBajo > 0)
                            <span class="badge bg-warning text-dark ms-auto">{{ $nStockBajo }}</span>
                        @endif
                    </a>
                </li>
                @endif

                @if($user->puedeVer('clientes'))
                <li>
                    <a href="/casadets/clientes" class="nav-link {{ request()->is('casadets/clientes*') ? 'active' : '' }}">
                        <i class="bi bi-person-lines-fill me-2"></i>Clientes
                    </a>
                </li>
                @endif

                @if($user->puedeVer('vendedores'))
                <li>
                    <a href="/casadets/vendedores" class="nav-link {{ request()->is('casadets/vendedores*') ? 'active' : '' }}">
                        <i class="bi bi-people me-2"></i>Vendedores
                    </a>
                </li>
                @endif

                @if($user->puedeVer('saldos-favor'))
                <li>
                    <a href="/casadets/saldos-favor" class="nav-link {{ request()->is('casadets/saldos-favor*') ? 'active' : '' }}">
                        <i class="bi bi-wallet2 me-2"></i>Saldos a favor
                        @php $nSaldos = \App\Models\SaldoFavor::whereIn('estado',['disponible','parcialmente_usado'])->where('monto_disponible','>',0)->distinct('cliente_id')->count('cliente_id'); @endphp
                        @if($nSaldos > 0)
                            <span class="badge bg-info text-dark ms-auto">{{ $nSaldos }}</span>
                        @endif
                    </a>
                </li>
                @endif

                @if($user->puedeVer('movimientos'))
                <li>
                    <a href="/movimientos" class="nav-link {{ request()->is('movimientos*') ? 'active' : '' }}">
                        <i class="bi bi-arrow-left-right me-2"></i>Movimientos
                    </a>
                </li>
                @endif

            </ul>
        </div>
    </li>
    @endif

    <!-- ZENDY -->
    @if($user && $user->puedeVer('zendy'))
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
    @endif

    @if($user && $user->puedeVer('reportes'))
    <li class="nav-item mt-2">
        <a href="/reportes" class="nav-link {{ request()->is('reportes*') ? 'active' : '' }}">
            <i class="bi bi-bar-chart-line me-2"></i>Reportes
        </a>
    </li>
    @endif

    <!-- ADMINISTRACIÓN -->
    @php
        $showAdmin = $user && ($user->puedeVer('admin.usuarios') || $user->puedeVer('admin.roles'));
    @endphp
    @if($showAdmin)
    <li class="nav-item mt-2">
        <a class="nav-link sidebar-section" data-bs-toggle="collapse" href="#adminMenu">
            <i class="bi bi-gear me-2"></i>Administración
            <i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <div class="collapse {{ request()->is('admin*') ? 'show' : '' }}" id="adminMenu">
            <ul class="nav flex-column ms-3 mt-1">
                @if($user->puedeVer('admin.usuarios'))
                <li>
                    <a href="/admin/usuarios" class="nav-link {{ request()->is('admin/usuarios*') ? 'active' : '' }}">
                        <i class="bi bi-people-fill me-2"></i>Usuarios
                    </a>
                </li>
                @endif
                @if($user->puedeVer('admin.roles'))
                <li>
                    <a href="/admin/roles" class="nav-link {{ request()->is('admin/roles*') ? 'active' : '' }}">
                        <i class="bi bi-shield-lock me-2"></i>Roles y permisos
                    </a>
                </li>
                @endif
            </ul>
        </div>
    </li>
    @endif

</ul>

<!-- Usuario autenticado en el pie del sidebar -->
@if($user)
<div class="mt-auto pt-3 border-top border-secondary" style="position:absolute;bottom:0;left:0;right:0;padding:1rem;">
    <div class="d-flex align-items-center gap-2 mb-2">
        <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#4f8ef7,#7c5cfc);display:flex;align-items:center;justify-content:center;font-size:.85rem;color:#fff;flex-shrink:0;">
            {{ strtoupper(substr($user->name, 0, 1)) }}
        </div>
        <div style="overflow:hidden;">
            <div class="text-white fw-semibold text-truncate" style="font-size:.8rem;line-height:1.2;">{{ $user->name }}</div>
            <div style="font-size:.72rem;color:#7c8099;">{{ $user->rol?->nombre ?? 'Sin rol' }}</div>
        </div>
    </div>
    <form method="POST" action="/logout">
        @csrf
        <button type="submit" class="btn btn-sm w-100" style="background:rgba(255,255,255,.06);color:#8b8fa8;border:1px solid #2a2d3a;font-size:.78rem;">
            <i class="bi bi-box-arrow-left me-1"></i>Cerrar sesión
        </button>
    </form>
</div>
@endif
