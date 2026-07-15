<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reporte_cajas', function (Blueprint $table) {
            $table->boolean('cerrado')->default(false)->after('efectivo_esperado');
            $table->timestamp('cerrado_at')->nullable()->after('cerrado');
        });
    }

    public function down(): void
    {
        Schema::table('reporte_cajas', function (Blueprint $table) {
            $table->dropColumn(['cerrado', 'cerrado_at']);
        });
    }
};
