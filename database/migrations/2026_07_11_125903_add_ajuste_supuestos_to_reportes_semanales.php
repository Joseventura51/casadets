<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reportes_semanales', function (Blueprint $table) {
            // Suma de ajustes por supuestos aplicados en este cierre.
            // = -SUM(diferencia_total) → se SUMA a la utilidad del cierre.
            // Si real fue más caro (diferencia > 0): ajuste < 0 → reduce utilidad.
            // Si real fue más barato (diferencia < 0): ajuste > 0 → aumenta utilidad.
            $table->decimal('ajuste_supuestos', 10, 2)->default(0)->after('comision_utilidad');
        });
    }

    public function down(): void
    {
        Schema::table('reportes_semanales', function (Blueprint $table) {
            $table->dropColumn('ajuste_supuestos');
        });
    }
};
