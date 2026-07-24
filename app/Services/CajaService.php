<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\CajaSesion;
use App\Models\User;

class CajaService
{
    /**
     * Cajas asignadas al usuario actual.
     */
    public static function cajasUsuario(): \Illuminate\Database\Eloquent\Collection
    {
        $user = auth()->user();
        if (!$user) return collect();

        // Admin ve todas las cajas
        if ($user->esAdmin()) {
            return Caja::where('activa', true)->orderBy('codigo')->get();
        }

        // Cajas asignadas al usuario
        $cajasIds = $user->cajasPermitidas()->select('cajas.id')->pluck('cajas.id');
        return Caja::whereIn('id', $cajasIds)
            ->where('activa', true)
            ->orderBy('codigo')
            ->get();
    }

    /**
     * Caja seleccionada en sesión.
     */
    public static function cajaSeleccionada(): ?Caja
    {
        $id = session('caja_id');
        if (!$id) return null;
        return Caja::find($id);
    }

    /**
     * Seleccionar caja en sesión (valida que el usuario tenga permiso).
     */
    public static function seleccionarCaja(int $cajaId): ?Caja
    {
        $user = auth()->user();
        if (!$user) return null;

        $cajasPermitidas = self::cajasUsuario()->pluck('id');
        if (!$cajasPermitidas->contains($cajaId)) {
            return null;
        }

        session(['caja_id' => $cajaId]);
        return Caja::find($cajaId);
    }

    /**
     * Verifica si el usuario puede operar en la caja seleccionada.
     */
    public static function puedeOperar(?int $cajaId = null): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->esAdmin()) return true;

        $cajaId = $cajaId ?? session('caja_id');
        if (!$cajaId) return false;

        return $user->cajasPermitidas()->where('cajas.id', $cajaId)->exists();
    }

    /**
     * Verifica si hay una caja abierta disponible para el usuario.
     * Si la hay, actualiza session('caja_id') automáticamente para que el resto
     * de la app opere sin necesitar que el usuario navegue primero por /caja.
     */
    public static function cajaAbierta(): bool
    {
        $cajaId = session('caja_id');

        // Ruta rápida: leer el booleano directamente de la tabla cajas
        if ($cajaId) {
            if (Caja::where('id', $cajaId)->where('esta_abierta', true)->exists()) {
                return true;
            }
            // El caja_id en sesión ya no está abierto — buscar otra
        }

        // Buscar caja abierta entre las que maneja el usuario
        $cajasDisponibles = self::cajasUsuario();
        if ($cajasDisponibles->isNotEmpty()) {
            $cajaAbierta = Caja::whereIn('id', $cajasDisponibles->pluck('id'))
                ->where('esta_abierta', true)
                ->orderBy('id')
                ->first();

            if ($cajaAbierta) {
                session(['caja_id' => $cajaAbierta->id]);
                return true;
            }
        }

        return false;
    }

    /**
     * Abrir caja (permite múltiples aperturas/cierres en el día).
     * Única restricción: no abrir si ya hay una sesión activa.
     */
    public static function abrirCaja(Caja $caja, float $montoApertura, ?string $observaciones = null): CajaSesion
    {
        if ($caja->esta_abierta) {
            throw new \RuntimeException('La caja ya se encuentra abierta. Debe cerrarla antes de realizar una nueva apertura.');
        }

        $sesion = CajaSesion::create([
            'empresa'        => $caja->empresa,
            'caja_id'        => $caja->id,
            'fecha'          => now()->toDateString(),
            'monto_apertura' => $montoApertura,
            'estado'         => 'abierta',
            'observaciones'  => $observaciones,
        ]);

        // Marcar la caja como abierta directamente en la tabla
        $caja->update(['esta_abierta' => true]);

        return $sesion;
    }

    /**
     * Cerrar caja.
     */
    public static function cerrarCaja(Caja $caja, float $montoCierre): CajaSesion
    {
        $sesion = $caja->sesionAbierta();
        if (!$sesion) {
            throw new \RuntimeException('No hay ninguna sesión abierta para esta caja.');
        }

        $sesion->update([
            'monto_cierre' => $montoCierre,
            'estado'       => 'cerrada',
        ]);

        // Marcar la caja como cerrada directamente en la tabla
        $caja->update(['esta_abierta' => false]);

        return $sesion;
    }

    /**
     * Cajas disponibles para el selector (HTML).
     */
    public static function selectorOptions(): array
    {
        $cajas = self::cajasUsuario();
        return $cajas->map(fn ($c) => [
            'id'      => $c->id,
            'codigo'  => $c->codigo,
            'nombre'  => $c->nombre,
            'empresa' => $c->empresa,
            'abierta' => (bool) $c->esta_abierta,
        ])->toArray();
    }
}
