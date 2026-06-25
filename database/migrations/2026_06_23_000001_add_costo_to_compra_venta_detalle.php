<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compra_venta_detalle', function (Blueprint $table) {
            $table->decimal('costo_unitario', 12, 4)->nullable()->after('cantidad');
            $table->decimal('costo_total',    12, 4)->nullable()->after('costo_unitario');
        });
    }

    public function down(): void
    {
        Schema::table('compra_venta_detalle', function (Blueprint $table) {
            $table->dropColumn(['costo_unitario', 'costo_total']);
        });
    }
};
