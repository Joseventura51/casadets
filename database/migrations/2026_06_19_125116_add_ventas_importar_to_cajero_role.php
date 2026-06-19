<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rol = DB::table('roles')->where('nombre', 'Cajero')->first();

        if (!$rol) return;

        $permisos = json_decode($rol->permisos ?? '[]', true) ?? [];

        $agregar = ['ventas.editar', 'ventas.importar'];

        $actualizado = false;
        foreach ($agregar as $p) {
            if (!in_array($p, $permisos)) {
                $permisos[] = $p;
                $actualizado = true;
            }
        }

        if ($actualizado) {
            DB::table('roles')
                ->where('nombre', 'Cajero')
                ->update(['permisos' => json_encode(array_values($permisos))]);
        }
    }

    public function down(): void
    {
        $rol = DB::table('roles')->where('nombre', 'Cajero')->first();

        if (!$rol) return;

        $permisos = json_decode($rol->permisos ?? '[]', true) ?? [];
        $permisos = array_values(array_diff($permisos, ['ventas.importar']));

        DB::table('roles')
            ->where('nombre', 'Cajero')
            ->update(['permisos' => json_encode($permisos)]);
    }
};
