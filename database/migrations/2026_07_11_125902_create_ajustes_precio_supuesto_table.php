<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajustes_precio_supuesto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_supuesta_id')->constrained('compras');
            $table->foreignId('compra_real_id')->nullable()->constrained('compras');
            // diferencia_total = costo_real - costo_supuesto
            // positivo → real fue más caro → utilidad había sido sobreestimada
            // negativo → real fue más barato → utilidad había sido subestimada
            $table->decimal('diferencia_total', 10, 2)->nullable();
            $table->boolean('aplicado')->default(false);
            $table->foreignId('reporte_semanal_id')->nullable()->constrained('reportes_semanales');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes_precio_supuesto');
    }
};
