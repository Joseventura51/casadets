<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mi Sistema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="/estyle.css">
</head>
<body>

{{-- NAVBAR MÓVIL (solo visible en pantallas pequeñas) --}}
<nav class="navbar navbar-dark d-md-none mobile-navbar px-3">
    <span class="navbar-brand fw-bold mb-0">
        <i class="bi bi-layers me-1"></i>Sistema
    </span>
    <button class="btn btn-outline-light btn-sm" type="button"
            data-bs-toggle="offcanvas" data-bs-target="#sidebarMobile">
        <i class="bi bi-list fs-5"></i>
    </button>
</nav>

{{-- OFFCANVAS SIDEBAR (móvil) --}}
<div class="offcanvas offcanvas-start sidebar" tabindex="-1" id="sidebarMobile" style="width:260px;">
    <div class="offcanvas-header border-bottom border-secondary">
        <h5 class="text-white fw-bold mb-0"><i class="bi bi-layers me-2"></i>Sistema</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-3 d-flex flex-column" id="sidebarMobileNav" style="padding-bottom:90px!important;">
        @include('layouts._sidebar_nav')
    </div>
</div>

<div class="container-fluid">
    <div class="row">

        {{-- SIDEBAR ESCRITORIO (oculto en móvil) --}}
        <div class="col-md-2 sidebar p-3 d-none d-md-flex flex-column" style="padding-bottom:90px!important;">
            <h5 class="text-center fw-bold mb-4 text-white">Sistema</h5>
            <div id="sidebarDesktopNav" class="flex-grow-1">
                @include('layouts._sidebar_nav')
            </div>
        </div>

        {{-- CONTENIDO --}}
        <main class="col-12 col-md-10 content-area p-3 p-md-4" id="appContent" aria-live="polite">


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
        </main>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el, { trigger: 'hover' }));
});
</script>
@stack('scripts')
</body>
</html>
