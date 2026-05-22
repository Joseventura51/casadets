<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->decimal('monto_total', 10, 2);
            $table->string('metodo_pago', 100);
            // aplicado: todo aplicado a facturas | parcial: queda algo sin aplicar | saldo_favor: excedente total
            $table->string('estado', 30)->default('aplicado');
            $table->date('fecha');
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index('cliente_id');
            $table->index(['fecha', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
