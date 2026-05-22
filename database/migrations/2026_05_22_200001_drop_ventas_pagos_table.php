<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ventas_pagos');
    }

    public function down(): void
    {
        // Tabla eliminada intencionalmente — no restaurar
    }
};
