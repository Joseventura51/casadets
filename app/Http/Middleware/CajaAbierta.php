<?php

namespace App\Http\Middleware;

use App\Services\CajaService;
use Closure;
use Illuminate\Http\Request;

class CajaAbierta
{
    public function handle(Request $request, Closure $next)
    {
        if (!CajaService::cajaAbierta()) {
            $msg = 'La caja no está abierta. Debes abrir la caja antes de realizar esta operación.';
            if ($request->expectsJson()) {
                return response()->json([
                    'success'      => false,
                    'message'      => $msg,
                    'caja_cerrada' => true,
                ], 403);
            }
            return redirect('/casadets/caja')->with('error', $msg);
        }

        return $next($request);
    }
}
