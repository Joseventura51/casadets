<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();               // F001, B002
            $table->string('tipo_documento');                   // boleta, factura, proforma, nota_credito
            $table->integer('correlativo_actual')->default(0);
            $table->boolean('activa')->default(true);
            $table->foreignId('caja_id')->nullable()->constrained('cajas')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series');
    }
};
