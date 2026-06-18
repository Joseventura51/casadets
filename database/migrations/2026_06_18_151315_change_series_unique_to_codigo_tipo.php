<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->dropUnique(['codigo']);
            $table->unique(['codigo', 'tipo_documento']);
        });
    }

    public function down(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->dropUnique(['codigo', 'tipo_documento']);
            $table->unique(['codigo']);
        });
    }
};
