<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reporte_cajas', function (Blueprint $table) {
            $table->decimal('total_cobradas',    12, 2)->nullable()->after('archivo');
            $table->decimal('total_otros',       12, 2)->nullable()->after('total_cobradas');
            $table->decimal('total_salidas',     12, 2)->nullable()->after('total_otros');
            $table->decimal('balance',           12, 2)->nullable()->after('total_salidas');
            $table->decimal('efectivo_esperado', 12, 2)->nullable()->after('balance');
        });
    }

    public function down(): void
    {
        Schema::table('reporte_cajas', function (Blueprint $table) {
            $table->dropColumn(['total_cobradas', 'total_otros', 'total_salidas', 'balance', 'efectivo_esperado']);
        });
    }
};
