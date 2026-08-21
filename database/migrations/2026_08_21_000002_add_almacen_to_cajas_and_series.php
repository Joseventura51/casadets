<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        $principal = DB::table('almacenes')->where('codigo', 'PRINCIPAL')->value('id');
        if ($principal) {
            DB::table('cajas')->whereNull('almacen_id')->update(['almacen_id' => $principal]);
        }
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