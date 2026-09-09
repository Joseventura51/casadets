<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cajas', function (Blueprint $table) {
            $table->foreignId('almacen_id')->nullable()->after('esta_abierta')
                ->constrained('almacenes')->nullOnDelete();
        });

        Schema::table('series', function (Blueprint $table) {
            $table->foreignId('almacen_id')->nullable()->after('caja_id')
                ->constrained('almacenes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->dropForeign(['almacen_id']);
            $table->dropColumn('almacen_id');
        });
        Schema::table('cajas', function (Blueprint $table) {
            $table->dropForeign(['almacen_id']);
            $table->dropColumn('almacen_id');
        });
    }
};