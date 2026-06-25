<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conciliacion_auditorias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('compra_id');
            $table->unsignedBigInteger('venta_detalle_id');
            $table->string('accion', 20);                          // crear | actualizar | eliminar
            $table->decimal('cantidad_anterior',       12, 4)->nullable();
            $table->decimal('cantidad_nueva',          12, 4)->nullable();
            $table->decimal('costo_unitario_anterior', 12, 4)->nullable();
            $table->decimal('costo_unitario_nuevo',    12, 4)->nullable();
            $table->decimal('costo_total_anterior',    12, 4)->nullable();
            $table->decimal('costo_total_nuevo',       12, 4)->nullable();
            $table->unsignedBigInteger('compra_linea_id_anterior')->nullable();
            $table->unsignedBigInteger('compra_linea_id_nuevo')->nullable();
            $table->string('producto_nombre')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('compra_id');
            $table->index('venta_detalle_id');
            $table->index('usuario_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conciliacion_auditorias');
    }
};
