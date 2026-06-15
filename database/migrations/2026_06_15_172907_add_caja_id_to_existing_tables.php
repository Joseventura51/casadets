<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Caja sesiones: ahora una caja puede tener múltiples sesiones
        Schema::table('caja_sesiones', function (Blueprint $table) {
            if (!Schema::hasColumn('caja_sesiones', 'caja_id')) {
                $table->foreignId('caja_id')->nullable()->constrained('cajas')->nullOnDelete()->after('empresa');
            }
        });

        // Ventas: se asocia a una caja
        Schema::table('ventas', function (Blueprint $table) {
            if (!Schema::hasColumn('ventas', 'caja_id')) {
                $table->foreignId('caja_id')->nullable()->constrained('cajas')->nullOnDelete()->after('vendedor_id');
            }
        });

        // Movimientos: se asocia a una caja
        Schema::table('movimientos', function (Blueprint $table) {
            if (!Schema::hasColumn('movimientos', 'caja_id')) {
                $table->foreignId('caja_id')->nullable()->constrained('cajas')->nullOnDelete()->after('empresa');
            }
        });

        // Compras: se asocia a una caja
        Schema::table('compras', function (Blueprint $table) {
            if (!Schema::hasColumn('compras', 'caja_id')) {
                $table->foreignId('caja_id')->nullable()->constrained('cajas')->nullOnDelete()->after('empresa');
            }
        });

        // Saldos a favor: se asocia a una caja
        Schema::table('saldos_favor', function (Blueprint $table) {
            if (!Schema::hasColumn('saldos_favor', 'caja_id')) {
                $table->foreignId('caja_id')->nullable()->constrained('cajas')->nullOnDelete()->after('pago_id');
            }
        });

        // User: caja principal (de login)
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'caja_id')) {
                $table->foreignId('caja_id')->nullable()->constrained('cajas')->nullOnDelete()->after('rol_id');
            }
        });
    }

    public function down(): void
    {
        foreach (['caja_sesiones', 'ventas', 'movimientos', 'compras', 'saldos_favor', 'users'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                if (Schema::hasColumn($tabla, 'caja_id')) {
                    $table->dropForeign(['caja_id']);
                    $table->dropColumn('caja_id');
                }
            });
        }
    }
};
