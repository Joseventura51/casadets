<?php

namespace App\Console\Commands;

use App\Models\Caja;
use App\Models\CajaSesion;
use App\Services\ReporteCajaService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CierreAutomaticoCaja extends Command
{
    protected $signature   = 'caja:cierre-automatico {--dry-run : Solo muestra qué haría sin ejecutar nada}';
    protected $description = 'Cierra automáticamente las sesiones de caja que quedaron abiertas de días anteriores y genera su reporte.';

    public function handle(): int
    {
        $hoy    = Carbon::today()->toDateString();
        $dryRun = $this->option('dry-run');

        // Sesiones abiertas cuya fecha es anterior a hoy
        $sesionesVencidas = CajaSesion::where('estado', 'abierta')
            ->whereDate('fecha', '<', $hoy)
            ->with('caja')
            ->get();

        if ($sesionesVencidas->isEmpty()) {
            $this->info('[CierreAutomatico] No hay sesiones vencidas. Nada que hacer.');
            return self::SUCCESS;
        }

        $this->info("[CierreAutomatico] Encontradas {$sesionesVencidas->count()} sesión(es) vencida(s).");

        foreach ($sesionesVencidas as $sesion) {
            $label = "Sesión #{$sesion->id} — Caja " . ($sesion->caja?->codigo ?? "sin-caja") . " — Fecha {$sesion->fecha}";

            if ($dryRun) {
                $this->line("  [dry-run] Cerraría: {$label}");
                continue;
            }

            try {
                // 1. Cerrar la sesión con monto_cierre = 0 (cierre automático sin conteo físico)
                $sesion->update([
                    'monto_cierre'  => 0,
                    'estado'        => 'cerrada',
                    'observaciones' => trim(($sesion->observaciones ? $sesion->observaciones . ' | ' : '')
                        . 'CIERRE AUTOMÁTICO SISTEMA — ' . now()->format('d/m/Y H:i')),
                ]);
                $sesion->refresh();

                $this->line("  ✓ Cerrada: {$label}");

                // 2. Generar reporte
                try {
                    ReporteCajaService::generar($sesion);
                    $this->line("  ✓ Reporte generado para {$label}");
                } catch (\Throwable $e) {
                    $this->warn("  ⚠ Reporte falló para {$label}: {$e->getMessage()}");
                    Log::error('CierreAutomatico: ReporteCaja::generar falló', [
                        'sesion_id' => $sesion->id,
                        'error'     => $e->getMessage(),
                        'file'      => basename($e->getFile()) . ':' . $e->getLine(),
                    ]);
                }
            } catch (\Throwable $e) {
                $this->error("  ✗ Error cerrando {$label}: {$e->getMessage()}");
                Log::error('CierreAutomatico: error cerrando sesión', [
                    'sesion_id' => $sesion->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        $this->info('[CierreAutomatico] Proceso completado.');
        return self::SUCCESS;
    }
}
