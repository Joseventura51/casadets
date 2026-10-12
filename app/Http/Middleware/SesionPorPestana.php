<?php 

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SesionPorPestana
{
    public function handle(Request $request, Closure $next): Response
    {
        $tabId = $request->header('X-Tab-Id')
            ?? $request->input('_tab');

        if (
            !is_string($tabId)
            || !preg_match('/\A[a-f0-9]{32}\z/', $tabId)
        ) {
            abort(400, 'El identificador de la pestaña esta ausente o es inválido.');
        }
        $request->attributes->set('tab_id', $tabId);
        $cookieBase = config('session.cookie');
        config([
            'session.cookie' => $cookieBase . '_tab_' . $tabId,
        ]);
        return $next($request);
    }

}