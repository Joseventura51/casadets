<?php

namespace App\Http\Controllers;

use App\Models\CompraVentaDetalle;
use App\Models\Venta;
use App\Services\RegularizacionCostoService;
use App\Services\VendedorScope;
use Illuminate\Http\Request;

class RegularizacionCostoController extends Controller
{
    public function store(
        Request $request,
        Venta $venta,
        CompraVentaDetalle $asignacion,
        RegularizacionCostoService $service
    ) {
        $this->authorizeVenta($venta);
        abort_unless($asignacion->venta_detalle_id && $asignacion->ventaDetalle()->where('venta_id', $venta->id)->exists(), 404);

        $data = $request->validate([
            'costo_nuevo' => ['required', 'regex:/^\d{1,8}(?:\.\d{1,4})?$/'],
            'idempotency_key' => ['required', 'uuid'],
            'referencia' => ['nullable', 'string', 'max:150'],
            'motivo' => ['nullable', 'string', 'max:2000'],
        ]);

        $service->regularizar(
            $asignacion->id,
            $data['costo_nuevo'],
            $data['idempotency_key'],
            $data['referencia'] ?? null,
            $data['motivo'] ?? null
        );

        return back()->with('success', 'Se registró la regularización de costo y quedó auditada.');
    }

    private function authorizeVenta(Venta $venta): void
    {
        $query = Venta::query()->whereKey($venta->getKey());
        VendedorScope::aplicar($query);
        abort_unless($query->exists(), 403);
    }
}
