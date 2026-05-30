<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->unsignedBigInteger('vendedor_id')->nullable()->after('cliente_id');
            $table->foreign('vendedor_id')->references('id')->on('vendedores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropForeign(['vendedor_id']);
            $table->dropColumn('vendedor_id');
        });
    }
};
