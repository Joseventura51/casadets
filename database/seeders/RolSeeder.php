<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\User;
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

        foreach ($roles as $rol) {
            Rol::firstOrCreate(['nombre' => $rol['nombre']], $rol);
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
