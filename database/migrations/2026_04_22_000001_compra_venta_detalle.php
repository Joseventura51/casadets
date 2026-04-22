<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('compra_venta');

        Schema::create('compra_venta_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->foreignId('venta_detalle_id')->constrained('venta_detalles')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['compra_id', 'venta_detalle_id'], 'cvd_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_venta_detalle');
        Schema::create('compra_venta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['compra_id', 'venta_id']);
        });
    }
};
