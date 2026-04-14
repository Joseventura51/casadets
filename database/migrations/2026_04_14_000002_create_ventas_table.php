<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendedor_id')->constrained('vendedores')->onDelete('cascade');
            $table->string('producto');
            $table->decimal('monto', 10, 2);
            $table->string('metodo_pago')->default('efectivo');
            $table->string('documento_tipo')->nullable();
            $table->string('documento_numero')->nullable();
            $table->text('observaciones')->nullable();
            $table->date('fecha');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
