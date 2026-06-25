<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\CompraLinea;
use App\Models\ConciliacionAuditoria;
use App\Models\VentaDetalle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service layer para asignaciones compra ↔ venta_detalle.
 *
 * Garantías:
 *   - Locking pesimista sobre filas de venta_detalle antes de validar
 *   - Prevención de sobreasignación concurrente
 *   - Constraint lógico centralizado (única fuente de verdad)
 *   - Auditoría inmutable de cada cambio
 */
class ConciliacionService
{
    /**
     * Valida, sincroniza y audita las asignaciones de una compra.
     *
     * @param  Compra    $compra           Compra cuyas asignaciones se sincronizan.
     * @param  array     $syncData         Array keyed by venta_detalle_id con pivot data.
     * @param  int|null  $compraIdExcluir  ID de la misma compra (para excluir sus propios registros previos al validar saldo).
     */
    public function sincronizar(
        Compra $compra,
        array  $syncData,
        ?int   $compraIdExcluir       = null,
        array  $ventaDetallesOverride = []
    ): void {
        DB::transaction(function () use ($compra, $syncData, $compraIdExcluir, $ventaDetallesOverride) {

            // ── 1. LOCKING PESIMISTA ────────────────────────────────────
            // Adquiere lock de fila sobre cada venta_detalle involucrado.
            // En PostgreSQL → SELECT ... FOR UPDATE
            // En SQLite    → serialización nativa por transacción (WAL)
            $ventaDetalleIds = array_keys($syncData);
            if (!empty($ventaDetalleIds)) {
                VentaDetalle::whereIn('id', $ventaDetalleIds)
                    ->lockForUpdate()
                    ->pluck('id');           // ejecuta la query y adquiere el lock
            }

            // ── 2. VALIDACIÓN CENTRALIZADA (post-lock) ──────────────────
            $this->validar($syncData, $compraIdExcluir, $ventaDetallesOverride);

            // ── 3. CAPTURA ESTADO ANTERIOR (para auditoría) ─────────────
            $estadoAnterior = $this->capturarEstado($compra->id);

            // ── 4. SYNC ─────────────────────────────────────────────────
            $compra->detalles()->sync($syncData);

            // ── 5. CAPTURA ESTADO NUEVO + AUDITORÍA ─────────────────────
            $estadoNuevo = $this->capturarEstado($compra->id);
            $this->auditar($compra->id, $estadoAnterior, $estadoNuevo);
        });
    }

    /* ── Constraint lógico ─────────────────────────────────────────────── */

    /**
     * Valida que ninguna asignación exceda el saldo disponible.
     * Se ejecuta DENTRO del lock, por lo que no puede haber race condition.
     */
    private function validar(array $syncData, ?int $compraIdExcluir, array $ventaDetallesOverride = []): void
    {
        $errors    = [];
        $acumLinea = [];   // compra_linea_id → total asignado en este sync

        foreach ($syncData as $ventaDetalleId => $pivot) {
            $cantidad = (float) ($pivot['cantidad'] ?? 0);

            // — Saldo disponible en venta_detalle —————————————————————
            // Se omite si el usuario confirmó override para este ID.
            $esOverride = in_array((int) $ventaDetalleId, $ventaDetallesOverride);
            $vd = VentaDetalle::find($ventaDetalleId);
            if ($vd && !$esOverride) {
                $yaAsignado = (float) DB::table('compra_venta_detalle')
                    ->where('venta_detalle_id', $ventaDetalleId)
                    ->when($compraIdExcluir, fn($q) => $q->where('compra_id', '!=', $compraIdExcluir))
                    ->sum('cantidad');

                $saldo = (float) $vd->cantidad - $yaAsignado;

                if ($cantidad > $saldo + 0.001) {
                    $errors[] = "Producto \"{$vd->producto}\": saldo disponible {$saldo} (intentas asignar {$cantidad}).";
                }
            }

            // — Acumulador por compra_linea ————————————————————————————
            $lineaId = $pivot['compra_linea_id'] ?? null;
            if ($lineaId) {
                $acumLinea[$lineaId] = ($acumLinea[$lineaId] ?? 0) + $cantidad;
            }
        }

        // — Saldo disponible en cada compra_linea —————————————————————
        foreach ($acumLinea as $lineaId => $totalAsignado) {
            $linea = CompraLinea::find($lineaId);
            if (!$linea) {
                continue;
            }
            // Lineas recién creadas → 0 asignaciones previas
            $yaAsignadoLinea = (float) DB::table('compra_venta_detalle')
                ->where('compra_linea_id', $lineaId)
                ->sum('cantidad');

            $saldo = (float) $linea->cantidad - $yaAsignadoLinea;

            if ($totalAsignado > $saldo + 0.001) {
                $errors[] = "Línea \"{$linea->producto}\": saldo {$saldo} (intentas asignar {$totalAsignado}).";
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages(['detalles' => $errors]);
        }
    }

    /* ── Auditoría ─────────────────────────────────────────────────────── */

    /** Lee el estado actual de CVD para una compra. Keyed by venta_detalle_id. */
    private function capturarEstado(int $compraId): array
    {
        return DB::table('compra_venta_detalle')
            ->where('compra_id', $compraId)
            ->get()
            ->keyBy('venta_detalle_id')
            ->toArray();
    }

    /** Compara anterior vs nuevo y escribe registros de auditoría inmutables. */
    private function auditar(int $compraId, array $anterior, array $nuevo): void
    {
        $usuarioId = Auth::id();
        $ip        = request()->ip();
        $ahora     = now();

        $registros = [];

        // — Creadas o modificadas —
        foreach ($nuevo as $vdId => $pivotNuevo) {
            if (!isset($anterior[$vdId])) {
                // Nueva asignación
                $registros[] = $this->buildAuditRow(
                    $compraId, $vdId, 'crear',
                    null, $pivotNuevo,
                    $usuarioId, $ip, $ahora
                );
            } else {
                $pivotAnterior = $anterior[$vdId];
                // Solo auditar si algo cambió
                if ($this->pivotCambio($pivotAnterior, $pivotNuevo)) {
                    $registros[] = $this->buildAuditRow(
                        $compraId, $vdId, 'actualizar',
                        $pivotAnterior, $pivotNuevo,
                        $usuarioId, $ip, $ahora
                    );
                }
            }
        }

        // — Eliminadas —
        foreach ($anterior as $vdId => $pivotAnterior) {
            if (!isset($nuevo[$vdId])) {
                $registros[] = $this->buildAuditRow(
                    $compraId, $vdId, 'eliminar',
                    $pivotAnterior, null,
                    $usuarioId, $ip, $ahora
                );
            }
        }

        if (!empty($registros)) {
            ConciliacionAuditoria::insert($registros);
        }
    }

    private function pivotCambio(object $anterior, object $nuevo): bool
    {
        return (float) ($anterior->cantidad       ?? 0) !== (float) ($nuevo->cantidad       ?? 0)
            || (float) ($anterior->costo_unitario ?? 0) !== (float) ($nuevo->costo_unitario ?? 0)
            || (float) ($anterior->costo_total    ?? 0) !== (float) ($nuevo->costo_total    ?? 0)
            || ($anterior->compra_linea_id ?? null) !== ($nuevo->compra_linea_id ?? null);
    }

    private function buildAuditRow(
        int     $compraId,
        int     $vdId,
        string  $accion,
        ?object $anterior,
        ?object $nuevo,
        ?int    $usuarioId,
        ?string $ip,
        \Carbon\Carbon $ahora
    ): array {
        // Nombre del producto para display rápido sin JOIN
        $productoNombre = $anterior?->producto ?? $nuevo?->producto ?? null;
        if (!$productoNombre) {
            $productoNombre = VentaDetalle::find($vdId)?->producto;
        }

        return [
            'compra_id'                => $compraId,
            'venta_detalle_id'         => $vdId,
            'accion'                   => $accion,
            'cantidad_anterior'        => $anterior?->cantidad        ?? null,
            'cantidad_nueva'           => $nuevo?->cantidad           ?? null,
            'costo_unitario_anterior'  => $anterior?->costo_unitario  ?? null,
            'costo_unitario_nuevo'     => $nuevo?->costo_unitario     ?? null,
            'costo_total_anterior'     => $anterior?->costo_total     ?? null,
            'costo_total_nuevo'        => $nuevo?->costo_total        ?? null,
            'compra_linea_id_anterior' => $anterior?->compra_linea_id ?? null,
            'compra_linea_id_nuevo'    => $nuevo?->compra_linea_id    ?? null,
            'producto_nombre'          => $productoNombre,
            'usuario_id'               => $usuarioId,
            'ip'                       => $ip,
            'created_at'               => $ahora,
        ];
    }
}
