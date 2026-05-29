<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermiso
{
    public function handle(Request $request, Closure $next, string ...$permisos): Response
    {
        $user = $request->user();

        // Usuario no autenticado
        if (!$user) {
            return redirect('/login');
        }

        // Verificar permisos
        foreach ($permisos as $permiso) {

            if ($user->puedeHacer($permiso)) {
                return $next($request);
            }
        }

        // Usuario autenticado pero sin permisos
        return redirect('/')
            ->with('error', 'No tienes permisos para acceder.');
    }
}