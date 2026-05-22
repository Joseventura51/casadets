<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── ventas: agregar columna pagado para tener estado financiero real ──
        Schema::table('ventas', function (Blueprint $table) {
            if (!Schema::hasColumn('ventas', 'pagado')) {
                $table->decimal('pagado', 10, 2)->default(0)->after('ajuste');
            }
            // Agregar estado 'parcial' ya es soportado como string — no hay cambio de tipo
        });

        // ── movimientos: extender para ser la tabla unificada de caja ─────────
        Schema::table('movimientos', function (Blueprint $table) {
            if (!Schema::hasColumn('movimientos', 'subtipo')) {
                // pago_venta, gasto, devolucion, ajuste, saldo_favor_usado, etc.
                $table->string('subtipo', 50)->nullable()->after('tipo');
            }
            if (!Schema::hasColumn('movimientos', 'metodo_pago')) {
                $table->string('metodo_pago', 100)->nullable()->after('subtipo');
            }
            if (!Schema::hasColumn('movimientos', 'referencia_tipo')) {
                $table->string('referencia_tipo', 50)->nullable()->after('metodo_pago');
            }
            if (!Schema::hasColumn('movimientos', 'referencia_id')) {
                $table->unsignedBigInteger('referencia_id')->nullable()->after('referencia_tipo');
            }
            if (!Schema::hasColumn('movimientos', 'cliente_id')) {
                $table->foreignId('cliente_id')->nullable()->after('referencia_id')
                      ->constrained('clientes')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn('pagado');
        });

        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropColumn(['subtipo', 'metodo_pago', 'referencia_tipo', 'referencia_id']);
            if (Schema::hasColumn('movimientos', 'cliente_id')) {
                $table->dropForeign(['cliente_id']);
                $table->dropColumn('cliente_id');
            }
        });
    }
};
