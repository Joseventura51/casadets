<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movimientos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('producto_id')
                ->constrained('productos')
                ->restrictOnDelete();

            // 'entrada' | 'salida' | 'ajuste'
            $table->string('tipo', 20);

            // Siempre positivo — el tipo determina la dirección
            $table->decimal('cantidad', 10, 2);

            // Costo y precio en el momento del movimiento (kardex)
            $table->decimal('costo_unitario', 10, 2)->nullable();
            $table->decimal('precio_unitario', 10, 2)->nullable();

            // Referencia polimórfica al origen: 'venta' | 'compra' | 'ajuste_manual'
            $table->string('referencia_tipo', 30)->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();

            $table->date('fecha');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['producto_id', 'tipo']);
            $table->index(['referencia_tipo', 'referencia_id']);
            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movimientos');
    }
};
