<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->foreignId('producto_id')
                ->nullable()
                ->after('id')
                ->constrained('productos')
                ->nullOnDelete();

            $table->index('producto_id');
        });

        Schema::table('compra_lineas', function (Blueprint $table) {
            $table->foreignId('producto_id')
                ->nullable()
                ->after('id')
                ->constrained('productos')
                ->nullOnDelete();

            $table->index('producto_id');
        });
    }

    public function down(): void
    {
        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->dropForeign(['producto_id']);
            $table->dropColumn('producto_id');
        });

        Schema::table('compra_lineas', function (Blueprint $table) {
            $table->dropForeign(['producto_id']);
            $table->dropColumn('producto_id');
        });
    }
};
