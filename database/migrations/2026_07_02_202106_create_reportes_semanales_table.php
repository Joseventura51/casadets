<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reportes_semanales', function (Blueprint $table) {
            $table->id();
            $table->date('periodo_inicio');
            $table->date('periodo_fin');
            $table->decimal('total_ventas', 12, 2)->default(0);
            $table->unsignedInteger('cantidad_ventas')->default(0);
            $table->decimal('total_compras', 12, 2)->default(0);
            $table->unsignedInteger('cantidad_compras')->default(0);
            $table->decimal('total_costo', 12, 2)->default(0);
            $table->decimal('utilidad', 12, 2)->default(0);
            $table->decimal('margen', 6, 2)->default(0);
            $table->decimal('comision_utilidad', 12, 2)->default(0);
            $table->unsignedInteger('ventas_pendientes')->default(0);
            $table->foreignId('cerrado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->foreignId('reporte_semanal_id')->nullable()->after('id')
                ->constrained('reportes_semanales')->nullOnDelete();
        });

        Schema::table('compras', function (Blueprint $table) {
            $table->foreignId('reporte_semanal_id')->nullable()->after('id')
                ->constrained('reportes_semanales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reporte_semanal_id');
        });

        Schema::table('compras', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reporte_semanal_id');
        });

        Schema::dropIfExists('reportes_semanales');
    }
};
