<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Services\CajaService;
use Illuminate\Http\Request;

class CajaSelectorController extends Controller
{
    /**
     * Cambia la caja activa en sesión.
     */
    public function seleccionar(Request $request)
    {
        $request->validate(['caja_id' => 'required|integer|exists:cajas,id']);

        $caja = CajaService::seleccionarCaja((int) $request->caja_id);

        if (!$caja) {
            $msg = 'No tienes permiso para operar en esa caja.';
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 403);
            }
            return back()->with('error', $msg);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success'  => true,
                'caja_id'  => $caja->id,
                'codigo'   => $caja->codigo,
                'nombre'   => $caja->nombre,
                'abierta'  => $caja->estaAbiertaHoy(),
            ]);
        }

        return redirect()->back()->with('success', "Caja {$caja->codigo} — {$caja->nombre} seleccionada.");
    }

    /**
     * API: cajas disponibles para el usuario.
     */
    public function disponibles()
    {
        return response()->json(CajaService::selectorOptions());
    }
}
