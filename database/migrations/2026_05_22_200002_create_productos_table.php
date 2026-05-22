<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('codigo', 50)->nullable()->unique();
            $table->decimal('precio_venta', 10, 2)->default(0);
            $table->decimal('precio_costo', 10, 2)->default(0);
            $table->decimal('stock_actual', 10, 2)->default(0)->comment('Campo cacheado — fuente de verdad es stock_movimientos');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('nombre');
            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
