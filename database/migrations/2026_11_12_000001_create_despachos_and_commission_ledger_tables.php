<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('despachos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->dateTime('fecha_hora');
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->index(['venta_id', 'fecha_hora']);
        });

        Schema::create('despacho_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('despacho_id')->constrained('despachos')->cascadeOnDelete();
            $table->foreignId('venta_detalle_id')->constrained('venta_detalles')->restrictOnDelete();
            $table->decimal('cantidad', 10, 2);
            $table->timestamps();
            $table->unique(['despacho_id', 'venta_detalle_id'], 'despacho_detalle_unico');
            $table->index('venta_detalle_id');
        });

        Schema::create('regularizaciones_costo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_venta_detalle_id')
                ->constrained('compra_venta_detalle')
                ->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->decimal('costo_anterior', 12, 4)->nullable();
            $table->decimal('costo_nuevo', 12, 4);
            $table->decimal('diferencia_unitaria', 12, 4)->nullable();
            $table->string('referencia', 150)->nullable();
            $table->text('motivo')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['compra_venta_detalle_id', 'id']);
        });

        Schema::create('despacho_detalle_costos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('despacho_detalle_id')->constrained('despacho_detalles')->cascadeOnDelete();
            $table->foreignId('compra_venta_detalle_id')
                ->constrained('compra_venta_detalle')
                ->restrictOnDelete();
            $table->decimal('cantidad', 10, 2);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(
                ['despacho_detalle_id', 'compra_venta_detalle_id'],
                'despacho_costo_origen_unico'
            );
            $table->index('compra_venta_detalle_id');
        });

        Schema::create('comision_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendedor_id')->constrained('vendedores')->restrictOnDelete();
            $table->foreignId('venta_id')->constrained('ventas')->restrictOnDelete();
            $table->foreignId('venta_detalle_id')->nullable()->constrained('venta_detalles')->nullOnDelete();
            $table->foreignId('despacho_id')->nullable()->constrained('despachos')->nullOnDelete();
            $table->foreignId('despacho_detalle_costo_id')->nullable()
                ->constrained('despacho_detalle_costos')
                ->nullOnDelete();
            $table->foreignId('regularizacion_costo_id')->nullable()
                ->constrained('regularizaciones_costo')
                ->nullOnDelete();
            $table->foreignId('movimiento_original_id')->nullable()
                ->constrained('comision_movimientos')
                ->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 40);
            $table->decimal('importe', 14, 2);
            $table->decimal('porcentaje', 5, 2)->nullable();
            $table->decimal('utilidad_base', 14, 2)->nullable();
            $table->date('fecha_origen');
            $table->date('fecha_efectiva');
            $table->string('origen_clave', 190)->unique();
            $table->text('observaciones')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['vendedor_id', 'fecha_efectiva']);
            $table->index(['venta_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comision_movimientos');
        Schema::dropIfExists('despacho_detalle_costos');
        Schema::dropIfExists('regularizaciones_costo');
        Schema::dropIfExists('despacho_detalles');
        Schema::dropIfExists('despachos');
    }
};
