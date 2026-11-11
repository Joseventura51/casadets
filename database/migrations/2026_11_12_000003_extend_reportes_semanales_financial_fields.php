<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reportes_semanales', function (Blueprint $table) {
            $table->string('empresa', 30)->nullable()->after('id');
            $table->decimal('utilidad_conocida', 12, 2)->nullable();
            $table->decimal('utilidad_despachada', 12, 2)->nullable();
            $table->decimal('comision_liberada', 12, 2)->nullable();
            $table->decimal('comision_pendiente', 12, 2)->nullable();
            $table->decimal('comision_despachada_potencial', 12, 2)->nullable();
            $table->unique(
                ['empresa', 'periodo_inicio', 'periodo_fin'],
                'reportes_semanales_empresa_periodo_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('reportes_semanales', function (Blueprint $table) {
            $table->dropUnique('reportes_semanales_empresa_periodo_unique');
            $table->dropColumn([
                'empresa',
                'utilidad_conocida',
                'utilidad_despachada',
                'comision_liberada',
                'comision_pendiente',
                'comision_despachada_potencial',
            ]);
        });
    }
};
