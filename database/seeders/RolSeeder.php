<?php

namespace Database\Seeders;

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
            ['nombre' => 'Administrador', 'descripcion' => 'Acceso total al sistema'],
            ['nombre' => 'Supervisor',    'descripcion' => 'Lectura amplia, sin modificar configuración'],
            ['nombre' => 'Cajero',        'descripcion' => 'Caja, ventas y cobranza'],
            ['nombre' => 'Vendedor',      'descripcion' => 'Solo sus ventas, clientes y reportes'],
        ];

        foreach ($roles as $data) {
            $defaults = PermisoCatalog::DEFAULTS[$data['nombre']] ?? ['modulos' => [], 'permisos' => []];

            $rol = Rol::firstOrCreate(
                ['nombre' => $data['nombre']],
                array_merge($data, $defaults)
            );

            // Actualizar módulos/permisos si el rol ya existía pero aún no tiene configuración
            if (empty($rol->modulos) && !empty($defaults['modulos'])) {
                $rol->update([
                    'modulos'  => $defaults['modulos'],
                    'permisos' => $defaults['permisos'],
                ]);
            }
        }

        $adminRol = Rol::where('nombre', 'Administrador')->first();

        if (!User::where('email', 'admin@sistema.com')->exists()) {
            User::create([
                'name'     => 'Administrador',
                'email'    => 'admin@sistema.com',
                'password' => Hash::make('admin1234'),
                'rol_id'   => $adminRol->id,
                'activo'   => true,
            ]);
        }
    }
}
