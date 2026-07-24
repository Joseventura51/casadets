<?php

namespace App\Providers;

use App\Models\Caja;
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
                    // Leer el booleano directamente de la tabla cajas
                    $cajaAbierta = Caja::where('id', $cajaId)
                        ->where('esta_abierta', true)
                        ->exists();
                } else {
                    // Sin caja seleccionada: verificar si alguna caja de la empresa está abierta
                    $empresa     = session('empresa', 'casadets');
                    $cajaAbierta = Caja::where('empresa', $empresa)
                        ->where('esta_abierta', true)
                        ->exists();
                }
            } catch (\Throwable $e) {
                $cajaAbierta = true; // si la tabla no existe aún, no bloquear
            }
            $view->with('cajaAbierta', $cajaAbierta);
        });
    }
}
