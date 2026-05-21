<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── ventas ───────────────────────────────────────────────────────────
        Schema::table('ventas', function (Blueprint $table) {
            if (!$this->hasIndex('ventas', 'ventas_fecha_index'))
                $table->index('fecha', 'ventas_fecha_index');

            if (!$this->hasIndex('ventas', 'ventas_estado_index'))
                $table->index('estado', 'ventas_estado_index');

            if (!$this->hasIndex('ventas', 'ventas_vendedor_id_index'))
                $table->index('vendedor_id', 'ventas_vendedor_id_index');

            if (!$this->hasIndex('ventas', 'ventas_cliente_id_index'))
                $table->index('cliente_id', 'ventas_cliente_id_index');

            if (!$this->hasIndex('ventas', 'ventas_documento_tipo_index'))
                $table->index('documento_tipo', 'ventas_documento_tipo_index');

            // Índices compuestos para filtros combinados frecuentes
            if (!$this->hasIndex('ventas', 'ventas_fecha_estado_index'))
                $table->index(['fecha', 'estado'], 'ventas_fecha_estado_index');

            if (!$this->hasIndex('ventas', 'ventas_vendedor_fecha_index'))
                $table->index(['vendedor_id', 'fecha'], 'ventas_vendedor_fecha_index');
        });

        // ── venta_detalles ───────────────────────────────────────────────────
        Schema::table('venta_detalles', function (Blueprint $table) {
            if (!$this->hasIndex('venta_detalles', 'venta_detalles_venta_id_index'))
                $table->index('venta_id', 'venta_detalles_venta_id_index');
        });

        // ── ventas_pagos ─────────────────────────────────────────────────────
        Schema::table('ventas_pagos', function (Blueprint $table) {
            if (!$this->hasIndex('ventas_pagos', 'ventas_pagos_venta_id_index'))
                $table->index('venta_id', 'ventas_pagos_venta_id_index');
        });

        // ── movimientos ──────────────────────────────────────────────────────
        Schema::table('movimientos', function (Blueprint $table) {
            if (!$this->hasIndex('movimientos', 'movimientos_fecha_index'))
                $table->index('fecha', 'movimientos_fecha_index');

            if (!$this->hasIndex('movimientos', 'movimientos_tipo_index'))
                $table->index('tipo', 'movimientos_tipo_index');

            // Compuesto: caja y dashboard filtran por tipo + fecha juntos
            if (!$this->hasIndex('movimientos', 'movimientos_tipo_fecha_index'))
                $table->index(['tipo', 'fecha'], 'movimientos_tipo_fecha_index');
        });

        // ── compras ──────────────────────────────────────────────────────────
        Schema::table('compras', function (Blueprint $table) {
            if (!$this->hasIndex('compras', 'compras_fecha_index'))
                $table->index('fecha', 'compras_fecha_index');
        });

        // ── compra_lineas ────────────────────────────────────────────────────
        Schema::table('compra_lineas', function (Blueprint $table) {
            if (!$this->hasIndex('compra_lineas', 'compra_lineas_compra_id_index'))
                $table->index('compra_id', 'compra_lineas_compra_id_index');
        });

        // ── vendedores ───────────────────────────────────────────────────────
        Schema::table('vendedores', function (Blueprint $table) {
            if (!$this->hasIndex('vendedores', 'vendedores_activo_index'))
                $table->index('activo', 'vendedores_activo_index');

            if (!$this->hasIndex('vendedores', 'vendedores_nombre_index'))
                $table->index('nombre', 'vendedores_nombre_index');
        });

        // ── clientes ─────────────────────────────────────────────────────────
        Schema::table('clientes', function (Blueprint $table) {
            if (!$this->hasIndex('clientes', 'clientes_activo_index'))
                $table->index('activo', 'clientes_activo_index');

            if (!$this->hasIndex('clientes', 'clientes_nombre_index'))
                $table->index('nombre', 'clientes_nombre_index');

            if (!$this->hasIndex('clientes', 'clientes_documento_index'))
                $table->index('documento', 'clientes_documento_index');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndexIfExists('ventas_fecha_index');
            $table->dropIndexIfExists('ventas_estado_index');
            $table->dropIndexIfExists('ventas_vendedor_id_index');
            $table->dropIndexIfExists('ventas_cliente_id_index');
            $table->dropIndexIfExists('ventas_documento_tipo_index');
            $table->dropIndexIfExists('ventas_fecha_estado_index');
            $table->dropIndexIfExists('ventas_vendedor_fecha_index');
        });
        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->dropIndexIfExists('venta_detalles_venta_id_index');
        });
        Schema::table('ventas_pagos', function (Blueprint $table) {
            $table->dropIndexIfExists('ventas_pagos_venta_id_index');
        });
        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropIndexIfExists('movimientos_fecha_index');
            $table->dropIndexIfExists('movimientos_tipo_index');
            $table->dropIndexIfExists('movimientos_tipo_fecha_index');
        });
        Schema::table('compras', function (Blueprint $table) {
            $table->dropIndexIfExists('compras_fecha_index');
        });
        Schema::table('compra_lineas', function (Blueprint $table) {
            $table->dropIndexIfExists('compra_lineas_compra_id_index');
        });
        Schema::table('vendedores', function (Blueprint $table) {
            $table->dropIndexIfExists('vendedores_activo_index');
            $table->dropIndexIfExists('vendedores_nombre_index');
        });
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndexIfExists('clientes_activo_index');
            $table->dropIndexIfExists('clientes_nombre_index');
            $table->dropIndexIfExists('clientes_documento_index');
        });
    }

    /**
     * Verifica si un índice ya existe (compatible con SQLite y MySQL).
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            $indexes = Schema::getConnection()
                ->getDoctrineSchemaManager()
                ->listTableIndexes($table);
            return array_key_exists(
                strtolower($indexName),
                array_change_key_case($indexes, CASE_LOWER)
            );
        } catch (\Throwable) {
            return false;
        }
    }
};
