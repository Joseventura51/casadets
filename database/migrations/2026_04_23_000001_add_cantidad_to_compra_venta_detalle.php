<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compra_venta_detalle', function (Blueprint $table) {
            $table->decimal('cantidad', 10, 2)->default(1)->after('venta_detalle_id');
        });
    }

    public function down(): void
    {
        Schema::table('compra_venta_detalle', function (Blueprint $table) {
            $table->dropColumn('cantidad');
        });
    }
};
