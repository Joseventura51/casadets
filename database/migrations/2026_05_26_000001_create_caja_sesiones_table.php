<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caja_sesiones', function (Blueprint $table) {
            $table->id();
            $table->string('empresa')->default('casadets');
            $table->date('fecha');
            $table->decimal('monto_apertura', 10, 2)->default(0);
            $table->decimal('monto_cierre', 10, 2)->nullable();
            $table->string('estado')->default('abierta'); // abierta | cerrada
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->unique(['empresa', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caja_sesiones');
    }
};
