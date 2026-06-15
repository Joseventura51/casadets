<?php

namespace App\Http\Middleware;

use App\Models\CajaSesion;
use App\Services\CajaService;
use Closure;
use Illuminate\Http\Request;

class CajaAbierta
{
    public function handle(Request $request, Closure $next)
    {
        $cajaId = session('caja_id');

        // Si hay caja seleccionada, validar que esté abierta
        if ($cajaId) {
            $abierta = CajaSesion::where('caja_id', $cajaId)
                ->whereDate('fecha', now()->toDateString())
                ->where('estado', 'abierta')
                ->exists();

            if (!$abierta) {
                $msg = 'La caja seleccionada no está abierta. Debes abrir la caja antes de realizar esta operación.';
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

        // Fallback: sin caja seleccionada, verificar empresa (compatibilidad histórica)
        $empresa = session('empresa', 'casadets');
        $abiertaEmpresa = CajaSesion::where('empresa', $empresa)
            ->whereDate('fecha', now()->toDateString())
            ->where('estado', 'abierta')
            ->exists();

        if (!$abiertaEmpresa) {
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
