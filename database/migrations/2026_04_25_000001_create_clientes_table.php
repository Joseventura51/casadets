<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('documento', 20)->nullable();   // DNI / RUC
            $table->string('telefono', 20)->nullable();
            $table->string('direccion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete()->after('vendedor_id');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Cliente::class);
            $table->dropColumn('cliente_id');
        });
        Schema::dropIfExists('clientes');
    }
};
