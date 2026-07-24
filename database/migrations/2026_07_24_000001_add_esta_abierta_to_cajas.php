<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cajas', function (Blueprint $table) {
            $table->boolean('esta_abierta')->default(false)->after('activa');
        });

        // Sincronizar el estado real desde caja_sesiones
        DB::table('cajas')->update(['esta_abierta' => false]);

        $abiertas = DB::table('caja_sesiones')
            ->where('estado', 'abierta')
            ->pluck('caja_id');

        if ($abiertas->isNotEmpty()) {
            DB::table('cajas')
                ->whereIn('id', $abiertas)
                ->update(['esta_abierta' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('cajas', function (Blueprint $table) {
            $table->dropColumn('esta_abierta');
        });
    }
};
