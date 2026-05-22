<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saldos_favor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('pago_id')->nullable()->constrained('pagos')->nullOnDelete();
            $table->decimal('monto_original', 10, 2);
            $table->decimal('monto_disponible', 10, 2);
            // disponible | usado | parcialmente_usado
            $table->string('estado', 30)->default('disponible');
            $table->text('descripcion')->nullable();
            $table->date('fecha');
            $table->timestamps();

            $table->index(['cliente_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saldos_favor');
    }
};
