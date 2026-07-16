<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('nubefact_comprobantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->unsignedTinyInteger('tipo_comprobante');
            $table->string('serie', 10);
            $table->unsignedInteger('numero');
            $table->string('estado', 20)->default('pendiente');
            $table->string('hash', 100)->nullable();
            $table->longText('xml')->nullable();
            $table->longText('cdr')->nullable();
            $table->string('pdf_url', 500)->nullable();
            $table->string('enlace_pdf', 500)->nullable();
            $table->json('respuesta_completa')->nullable();
            $table->string('nubefact_id', 100)->nullable();
            $table->text('error_mensaje')->nullable();
            $table->foreignId('venta_referencia_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->timestamps();

            $table->index('venta_id');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nubefact_comprobantes');
    }
};
