<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->decimal('total', 10, 2)->default(0)->after('vendedor_id');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['producto', 'monto']);
        });

        Schema::create('venta_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->onDelete('cascade');
            $table->string('producto');
            $table->decimal('cantidad', 10, 2)->default(1);
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_detalles');

        Schema::table('ventas', function (Blueprint $table) {
            $table->string('producto')->nullable();
            $table->decimal('monto', 10, 2)->default(0);
            $table->dropColumn('total');
        });
    }
};
