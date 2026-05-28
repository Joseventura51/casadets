<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pago_metodos', function (Blueprint $table) {
            $table->string('descripcion')->nullable()->after('metodo');
        });
    }

    public function down(): void
    {
        Schema::table('pago_metodos', function (Blueprint $table) {
            $table->dropColumn('descripcion');
        });
    }
};
