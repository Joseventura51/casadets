<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ventas_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')
            ->constrained()
            ->onDelete('cascade');
            $table->string('metodo_pago');
            $table->decimal('monto', 10,2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventas_pagos');
    }
};
