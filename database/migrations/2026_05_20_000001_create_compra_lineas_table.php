<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compra_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->string('producto')->nullable();
            $table->decimal('cantidad', 10, 2)->default(1);
            $table->decimal('monto_unitario', 10, 2)->default(0);
            $table->decimal('monto_total', 10, 2)->default(0);
            $table->timestamps();
        });

        // Migrar compras existentes que tienen datos en el campo legado 'producto'
        $compras = DB::table('compras')->whereNotNull('producto')->get();
        foreach ($compras as $c) {
            DB::table('compra_lineas')->insert([
                'compra_id'      => $c->id,
                'producto'       => $c->producto,
                'cantidad'       => $c->cantidad ?? 1,
                'monto_unitario' => $c->monto_unitario ?? 0,
                'monto_total'    => $c->monto_total ?? 0,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_lineas');
    }
};
