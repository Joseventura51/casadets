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
        Schema::table('vendedores', function (Blueprint $table) {
            $table->decimal('comision_porcentaje', 5, 2)->default(0)->after('activo')
                  ->comment('Porcentaje de comisión sobre el total a cobrar (ej: 5.00 = 5%)');
        });
    }

    public function down(): void
    {
        Schema::table('vendedores', function (Blueprint $table) {
            $table->dropColumn('comision_porcentaje');
        });
    }
};
