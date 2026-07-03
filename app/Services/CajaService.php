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
     * Verifica si la caja seleccionada está abierta.
     * Si no hay caja_id en sesión, intenta auto-seleccionar una y verifica por empresa como fallback.
     */
    public static function cajaAbierta(): bool
    {
        $cajaId = session('caja_id');

        // Si hay caja_id en sesión, verificar directamente
        if ($cajaId) {
            return CajaSesion::where('caja_id', $cajaId)
                ->where('estado', 'abierta')
                ->exists();
        }

        // Intentar auto-seleccionar si solo hay una caja disponible
        $cajasDisponibles = self::cajasUsuario();
        if ($cajasDisponibles->count() === 1) {
            $cajaId = $cajasDisponibles->first()->id;
            session(['caja_id' => $cajaId]);

            return CajaSesion::where('caja_id', $cajaId)
                ->where('estado', 'abierta')
                ->exists();
        }

        // Fallback: verificar si hay alguna caja abierta (sin filtro por caja_id)
        $empresa = session('empresa', 'casadets');
        return CajaSesion::where('empresa', $empresa)
            ->where('estado', 'abierta')
            ->exists();
    }

    /**
     * Abrir caja (permite múltiples aperturas/cierres en el día).
     * Única restricción: no abrir si ya hay una sesión activa.
     */
    public static function abrirCaja(Caja $caja, float $montoApertura, ?string $observaciones = null): CajaSesion
    {
        if ($caja->estaAbiertaHoy()) {
            throw new \RuntimeException('La caja ya se encuentra abierta. Debe cerrarla antes de realizar una nueva apertura.');
        }

        return CajaSesion::create([
            'empresa'        => $caja->empresa,
            'caja_id'        => $caja->id,
            'fecha'          => now()->toDateString(),
            'monto_apertura' => $montoApertura,
            'estado'         => 'abierta',
            'observaciones'  => $observaciones,
        ]);
    }

    /**
     * Cerrar caja.
     */
    public static function cerrarCaja(Caja $caja, float $montoCierre): CajaSesion
    {
        $sesion = $caja->sesionAbiertaHoy();
        if (!$sesion) {
            throw new \RuntimeException('No hay apertura registrada para hoy.');
        }

        $sesion->update([
            'monto_cierre' => $montoCierre,
            'estado'       => 'cerrada',
        ]);

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
            'abierta' => $c->estaAbiertaHoy(),
        ])->toArray();
    }
}
