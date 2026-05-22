<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_pago_factura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('pagos')->cascadeOnDelete();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->decimal('monto_aplicado', 10, 2);
            $table->timestamps();

            $table->index('pago_id');
            $table->index('venta_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_pago_factura');
    }
};
