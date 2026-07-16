<?php

namespace App\Http\Controllers;

use App\Models\NubefactComprobante;
use App\Models\Venta;
use App\Services\NubefactService;
use Illuminate\Http\Request;

class NubefactController extends Controller
{
    public function __construct(private readonly NubefactService $nubefact) {}

    public function emitir(Request $request, Venta $venta)
    {
        $tiposPermitidos = ['factura', 'boleta', 'nota_credito'];
        if (!in_array($venta->documento_tipo, $tiposPermitidos)) {
            return back()->with('error', 'Solo se pueden emitir facturas, boletas o notas de crédito electrónicas.');
        }

        if ($venta->nubefactComprobante?->estaAceptado()) {
            return back()->with('error', 'Este comprobante ya fue emitido y aceptado por SUNAT.');
        }

        $ventaReferenciaId = $request->input('venta_referencia_id');

        if ($venta->documento_tipo === 'nota_credito' && !$ventaReferenciaId) {
            return back()->with('error', 'Para emitir una Nota de Crédito electrónica debes indicar el comprobante original que modifica.');
        }

        $comprobante = $this->nubefact->emitir($venta, $ventaReferenciaId ? (int) $ventaReferenciaId : null);

        if ($comprobante->estaAceptado()) {
            return back()->with('success', '✓ Comprobante electrónico emitido y aceptado por SUNAT. ' . $comprobante->numeroCompleto());
        }

        return back()->with('error', 'Nubefact rechazó el comprobante: ' . $comprobante->error_mensaje);
    }

    public function ver(NubefactComprobante $comprobante)
    {
        $comprobante->load(['venta.cliente', 'venta.detalles']);
        return view('casadets.ventas.nubefact_comprobante', compact('comprobante'));
    }

    public function reintentar(NubefactComprobante $comprobante)
    {
        if ($comprobante->estaAceptado()) {
            return back()->with('error', 'El comprobante ya fue aceptado.');
        }

        $nuevo = $this->nubefact->emitir($comprobante->venta, $comprobante->venta_referencia_id);

        if ($nuevo->estaAceptado()) {
            $comprobante->delete();
            return back()->with('success', '✓ Reintento exitoso. Comprobante aceptado por SUNAT.');
        }

        return back()->with('error', 'Nubefact rechazó el reintento: ' . $nuevo->error_mensaje);
    }
}
