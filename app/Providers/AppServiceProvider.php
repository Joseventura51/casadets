<?php

namespace App\Providers;

use App\Models\CajaSesion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (config('database.default') === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');
        }

        // Compartir estado de caja con todas las vistas
        View::composer('*', function ($view) {
            try {
                $cajaId = session('caja_id');
                if ($cajaId) {
                    // Verificar por caja_id de la sesión actual
                    $cajaAbierta = CajaSesion::where('caja_id', $cajaId)
                        ->whereDate('fecha', now()->toDateString())
                        ->where('estado', 'abierta')
                        ->exists();
                } else {
                    // Sin caja seleccionada: verificar si existe alguna caja abierta hoy
                    $cajaAbierta = CajaSesion::whereNotNull('caja_id')
                        ->whereDate('fecha', now()->toDateString())
                        ->where('estado', 'abierta')
                        ->exists();
                }
            } catch (\Throwable $e) {
                $cajaAbierta = true; // si la tabla no existe aún, no bloquear
            }
            $view->with('cajaAbierta', $cajaAbierta);
        });
    }
}
