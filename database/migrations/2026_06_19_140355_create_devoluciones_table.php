<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devoluciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('tipo', ['parcial', 'total'])->default('parcial');
            $table->decimal('monto_devuelto', 12, 2)->default(0);
            $table->decimal('saldo_generado', 12, 2)->default(0);
            $table->string('motivo')->nullable();
            $table->date('fecha');
            $table->string('empresa')->default('casadets');
            $table->foreignId('caja_id')->nullable()->constrained('cajas')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('devolucion_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devolucion_id')->constrained('devoluciones')->onDelete('cascade');
            $table->foreignId('venta_detalle_id')->constrained('venta_detalles')->onDelete('cascade');
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->decimal('cantidad_devuelta', 12, 2);
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devolucion_detalles');
        Schema::dropIfExists('devoluciones');
    }
};
