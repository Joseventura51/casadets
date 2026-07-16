<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->boolean('igv_incluido')->default(true)->after('es_referencia_fiscal');
            $table->decimal('igv_porcentaje', 5, 2)->default(18.00)->after('igv_incluido');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['igv_incluido', 'igv_porcentaje']);
        });
    }
};
