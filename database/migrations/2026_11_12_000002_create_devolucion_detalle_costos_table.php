<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devolucion_detalle_costos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devolucion_detalle_id')
                ->constrained('devolucion_detalles')->cascadeOnDelete();
            $table->foreignId('despacho_detalle_costo_id')
                ->nullable()->constrained('despacho_detalle_costos')->restrictOnDelete();
            $table->foreignId('compra_venta_detalle_id')
                ->nullable()->constrained('compra_venta_detalle')->restrictOnDelete();
            $table->decimal('cantidad_devuelta', 12, 2);
            $table->decimal('costo_unitario', 12, 4)->nullable();
            $table->string('metodo_asignacion', 40);
            $table->timestamps();

            $table->index(
                ['devolucion_detalle_id', 'despacho_detalle_costo_id'],
                'devol_detalle_costo_origen_idx'
            );
            $table->index('compra_venta_detalle_id', 'devol_detalle_cvd_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devolucion_detalle_costos');
    }
};
