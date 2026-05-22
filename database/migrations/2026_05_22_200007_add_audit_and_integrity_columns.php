<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── user_id en ventas ──────────────────────────────────────────
        Schema::table('ventas', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('vendedor_id');
        });

        // ── user_id en pagos ───────────────────────────────────────────
        Schema::table('pagos', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('cliente_id');
        });

        // ── user_id + origen en movimientos ────────────────────────────
        Schema::table('movimientos', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('cliente_id');
            $table->string('origen', 20)->default('manual')->after('subtipo');
            $table->index('subtipo', 'mov_subtipo_idx');
            $table->index('origen', 'mov_origen_idx');
        });

        // ── Columnas legacy de compras ya no se usan (compra_lineas las reemplazó)
        Schema::table('compras', function (Blueprint $table) {
            $table->dropColumn(['producto', 'cantidad', 'monto_unitario']);
        });
    }

    public function down(): void
    {
        Schema::table('ventas', fn (Blueprint $t) => $t->dropColumn('user_id'));

        Schema::table('pagos', fn (Blueprint $t) => $t->dropColumn('user_id'));

        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropIndex('mov_subtipo_idx');
            $table->dropIndex('mov_origen_idx');
            $table->dropColumn(['user_id', 'origen']);
        });

        Schema::table('compras', function (Blueprint $table) {
            $table->string('producto')->nullable();
            $table->decimal('cantidad', 10, 2)->default(1);
            $table->decimal('monto_unitario', 10, 2)->default(0);
        });
    }
};
