<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('caja_id')->constrained('cajas')->cascadeOnDelete();
            $table->boolean('principal')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'caja_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_caja');
    }
};
