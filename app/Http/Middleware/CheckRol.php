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
            return redirect('/login');
        }

        foreach ($modulos as $modulo) {
            if ($user->puedeVer($modulo)) {
                return $next($request);
            }
        }

        abort(403, 'No tienes permiso para acceder a esta sección.');
    }
}
