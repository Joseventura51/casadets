<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saldos_favor', function (Blueprint $table) {
            $table->timestamp('anulado_at')->nullable()->after('estado');
            $table->unsignedBigInteger('anulado_por_id')->nullable()->after('anulado_at');
            $table->string('motivo_anulacion')->nullable()->after('anulado_por_id');
        });
    }

    public function down(): void
    {
        Schema::table('saldos_favor', function (Blueprint $table) {
            $table->dropColumn(['anulado_at', 'anulado_por_id', 'motivo_anulacion']);
        });
    }
};
