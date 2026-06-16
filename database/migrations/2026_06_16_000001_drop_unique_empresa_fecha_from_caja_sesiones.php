<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caja_sesiones', function (Blueprint $table) {
            $table->dropUnique(['empresa', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::table('caja_sesiones', function (Blueprint $table) {
            $table->unique(['empresa', 'fecha']);
        });
    }
};
