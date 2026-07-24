<?php

namespace App\Http\Middleware;

use App\Models\Caja;
use App\Services\CajaService;
use Closure;
use Illuminate\Http\Request;

class CajaAbierta
{
    public function handle(Request $request, Closure $next)
    {
        $cajaId = session('caja_id');

        // Si hay caja seleccionada, leer el boolean directamente de la tabla cajas
        if ($cajaId) {
            $caja = Caja::find($cajaId);

            if (!$caja || !$caja->esta_abierta) {
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

        // Fallback: sin caja seleccionada, verificar si alguna caja de la empresa está abierta
        $empresa = session('empresa', 'casadets');
        $abiertaEmpresa = Caja::where('empresa', $empresa)
            ->where('esta_abierta', true)
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
