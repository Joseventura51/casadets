<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── BUG #1: documento_tipo / documento_numero → nullable ─────────
        Schema::table('movimientos', function (Blueprint $table) {
            $table->string('documento_tipo')->nullable()->change();
            $table->string('documento_numero')->nullable()->change();

            // estado auditado (activo / anulado / revertido)
            $table->string('estado')->default('activo')->after('origen');

            // empresa para separar CASADETS / ZENDY
            $table->string('empresa')->default('casadets')->after('estado');
        });

        // ── BUG #2: quitar DEFAULT 'efectivo' de ventas.metodo_pago ──────
        // NULL real cuando la venta es crédito (sin pago inmediato)
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('metodo_pago')->nullable()->default(null)->change();
        });

        // ── empresa en productos ─────────────────────────────────────────
        Schema::table('productos', function (Blueprint $table) {
            $table->string('empresa')->default('casadets')->after('codigo');
        });

        // ── Índices de performance ────────────────────────────────────────
        Schema::table('saldos_favor', function (Blueprint $table) {
            $table->index(['cliente_id', 'estado'], 'saldos_favor_cliente_estado_idx');
        });

        Schema::table('compra_venta_detalle', function (Blueprint $table) {
            $table->index('venta_detalle_id', 'cvd_venta_detalle_idx');
        });

        Schema::table('movimientos', function (Blueprint $table) {
            $table->index('estado', 'movimientos_estado_idx');
            $table->index(['empresa', 'tipo', 'fecha'], 'movimientos_empresa_tipo_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropColumn(['estado', 'empresa']);
            $table->dropIndex('movimientos_estado_idx');
            $table->dropIndex('movimientos_empresa_tipo_fecha_idx');
        });
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('metodo_pago')->default('efectivo')->change();
        });
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('empresa');
        });
        Schema::table('saldos_favor', function (Blueprint $table) {
            $table->dropIndex('saldos_favor_cliente_estado_idx');
        });
        Schema::table('compra_venta_detalle', function (Blueprint $table) {
            $table->dropIndex('cvd_venta_detalle_idx');
        });
    }
};
