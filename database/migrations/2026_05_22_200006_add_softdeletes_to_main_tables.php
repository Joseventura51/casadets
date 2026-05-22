<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('compras', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('vendedores', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        foreach (['ventas', 'pagos', 'compras', 'clientes', 'vendedores'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
