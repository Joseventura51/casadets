<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago_metodos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pago_id')
                ->constrained('pagos')
                ->cascadeOnDelete();

            // 'efectivo' | 'yape' | 'plin' | 'transferencia' | 'tarjeta' | 'saldo_favor'
            $table->string('metodo', 50);

            $table->decimal('monto', 10, 2);
            $table->timestamps();

            $table->index('pago_id');
            $table->index('metodo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pago_metodos');
    }
};
