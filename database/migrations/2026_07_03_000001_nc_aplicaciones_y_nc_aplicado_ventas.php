<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Columna en ventas: monto total de NC aplicado a ese vale (para utilidad)
        Schema::table('ventas', function (Blueprint $table) {
            $table->decimal('nc_aplicado', 10, 2)->default(0)->after('ajuste');
        });

        // Tabla que registra qué NC fue aplicada a qué vale y por cuánto
        Schema::create('nota_credito_aplicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nota_credito_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->decimal('monto', 10, 2);
            $table->foreignId('registrado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['nota_credito_id', 'venta_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nota_credito_aplicaciones');

        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn('nc_aplicado');
        });
    }
};
