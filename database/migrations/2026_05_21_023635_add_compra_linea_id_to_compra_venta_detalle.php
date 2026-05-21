<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compra_venta_detalle', function (Blueprint $table) {
            $table->foreignId('compra_linea_id')
                  ->nullable()
                  ->after('cantidad')
                  ->constrained('compra_lineas')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('compra_venta_detalle', function (Blueprint $table) {
            $table->dropForeign(['compra_linea_id']);
            $table->dropColumn('compra_linea_id');
        });
    }
};
