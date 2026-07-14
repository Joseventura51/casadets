<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->string('tipo_gasto', 30)->nullable()->after('es_supuesto');
            $table->unsignedBigInteger('venta_asignada_id')->nullable()->after('tipo_gasto');
            $table->foreign('venta_asignada_id')->references('id')->on('ventas')->nullOnDelete();
        });

        Schema::table('reportes_semanales', function (Blueprint $table) {
            $table->decimal('gastos_operativos', 10, 2)->default(0)->after('ajuste_supuestos');
        });
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropForeign(['venta_asignada_id']);
            $table->dropColumn(['tipo_gasto', 'venta_asignada_id']);
        });
        Schema::table('reportes_semanales', function (Blueprint $table) {
            $table->dropColumn('gastos_operativos');
        });
    }
};
