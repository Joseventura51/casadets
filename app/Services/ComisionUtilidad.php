<?php

namespace App\Services;

use App\Models\Venta;
use App\Support\DecimalMoney;
use Illuminate\Support\Collection;

/**
 * Comisión calculada sobre la utilidad (ganancia) de cada venta.
 * Regla de negocio: ventas del día domingo aportan al 50%, el resto de días al 35%.
 */
class ComisionUtilidad
{
    public static function calcular(Collection $ventaIdsPagadasYCosteadas): float
    {
        if ($ventaIdsPagadasYCosteadas->isEmpty()) {
            return 0.0;
        }

        $totalCents = 0;
        $service = app(UtilidadVentaService::class);
        Venta::query()
            ->whereIn('id', $ventaIdsPagadasYCosteadas->all())
            ->orderBy('id')
            ->chunkById(200, function ($ventas) use ($service, &$totalCents): void {
                foreach ($service->resumenes($ventas) as $resumen) {
                    if ($resumen['comision_potencial'] !== null) {
                        $totalCents += DecimalMoney::cents($resumen['comision_potencial']);
                    }
                }
            });

        return (float) DecimalMoney::formatCents($totalCents);
    }
}
