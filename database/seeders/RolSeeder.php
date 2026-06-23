<?php

namespace Database\Seeders;

use App\Models\Caja;
use App\Models\CajaSesion;
use App\Models\Rol;
use App\Models\User;
use App\Support\PermisoCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RolSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'Administrador' => 'Acceso total al sistema',
            'Supervisor'    => 'Lectura amplia, sin modificar configuracion',
            'Cajero'        => 'Caja, ventas y cobranza',
            'Vendedor'      => 'Solo sus ventas, clientes y reportes',
        ];

        foreach ($roles as $nombre => $descripcion) {
            $defaults = $this->defaultsParaRol($nombre);

            $rol = Rol::firstOrNew(['nombre' => $nombre]);
            $rol->descripcion = $descripcion;
            $rol->modulos = $this->mergeUnico($rol->modulos ?? [], $defaults['modulos']);
            $rol->permisos = $this->mergeUnico($rol->permisos ?? [], $defaults['permisos']);
            $rol->save();
        }

        $adminRol = Rol::where('nombre', 'Administrador')->firstOrFail();

        User::updateOrCreate(
            ['email' => 'admin@sistema.com'],
            [
                'name'     => 'Administrador',
                'password' => Hash::make('12345678'),
                'rol_id'   => $adminRol->id,
                'activo'   => true,
            ]
        );

        $caja = Caja::firstOrCreate(
            ['codigo' => 'CAJA01'],
            [
                'nombre'  => 'Caja Principal',
                'empresa' => 'casadets',
                'activa'  => true,
            ]
        );

        CajaSesion::firstOrCreate(
            [
                'caja_id' => $caja->id,
                'fecha'   => now()->toDateString(),
            ],
            [
                'empresa'        => 'casadets',
                'monto_apertura' => 0,
                'estado'         => 'abierta',
            ]
        );
    }

    private function defaultsParaRol(string $nombre): array
    {
        if ($nombre === 'Administrador') {
            return [
                'modulos'  => PermisoCatalog::allModuloKeys(),
                'permisos' => PermisoCatalog::allPermisoKeys(),
            ];
        }

        return PermisoCatalog::DEFAULTS[$nombre] ?? ['modulos' => [], 'permisos' => []];
    }

    private function mergeUnico(array $actuales, array $requeridos): array
    {
        return array_values(array_unique(array_merge($actuales, $requeridos)));
    }
}
