<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRol
{
    public function handle(Request $request, Closure $next, string ...$modulos): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        foreach ($modulos as $modulo) {
            if ($user->puedeVer($modulo)) {
                return $next($request);
            }
        }

        foreach ($this->permisosDeLaRuta($request) as $permiso) {
            if ($user->puedeHacer($permiso)) {
                return $next($request);
            }
        }

        return redirect('/')
            ->with('error', 'No tienes permisos para acceder a esta seccion.');
    }

    private function permisosDeLaRuta(Request $request): array
    {
        $route = $request->route();
        if (!$route) {
            return [];
        }

        $permisos = [];
        foreach ($route->gatherMiddleware() as $middleware) {
            if (!str_starts_with($middleware, 'permiso:')) {
                continue;
            }

            foreach (explode(',', substr($middleware, strlen('permiso:'))) as $permiso) {
                $permiso = trim($permiso);
                if ($permiso !== '') {
                    $permisos[] = $permiso;
                }
            }
        }

        return array_values(array_unique($permisos));
    }
}
