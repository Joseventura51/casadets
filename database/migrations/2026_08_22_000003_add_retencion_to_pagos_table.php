<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->boolean('retencion_aplicada')->default(false)->after('monto_total');
            $table->decimal('retencion_porcentaje', 5, 2)->default(0)->after('retencion_aplicada');
            $table->decimal('retencion_monto', 14, 2)->default(0)->after('retencion_porcentaje');
            $table->decimal('monto_neto', 14, 2)->default(0)->after('retencion_monto');
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn([
                'retencion_aplicada',
                'retencion_porcentaje',
                'retencion_monto',
                'monto_neto',
            ]);
        });
    }
};