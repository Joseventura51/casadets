<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->string('unidad_medida', 10)->nullable()->default('NIU')->after('codigo');
        });
    }

    public function down(): void
    {
        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->dropColumn('unidad_medida');
        });
    }
};
