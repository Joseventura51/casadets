<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->string('empresa');
            $table->string('documento_tipo')->nullable();
            $table->string('documento_numero')->nullable();
            $table->date('fecha');
            $table->string('producto')->nullable();
            $table->decimal('cantidad', 10, 2)->default(1);
            $table->decimal('monto_unitario', 10, 2)->default(0);
            $table->decimal('monto_total', 10, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        Schema::create('compra_venta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['compra_id', 'venta_id']);
        });

        // Limpiar duplicados existentes de facturas antes de aplicar la unicidad
        $duplicados = DB::table('ventas')
            ->select('documento_numero', DB::raw('COUNT(*) as c'))
            ->where('documento_tipo', 'factura')
            ->whereNotNull('documento_numero')
            ->groupBy('documento_numero')
            ->having('c', '>', 1)
            ->pluck('documento_numero');

        foreach ($duplicados as $num) {
            $ids = DB::table('ventas')
                ->where('documento_tipo', 'factura')
                ->where('documento_numero', $num)
                ->orderBy('id')
                ->pluck('id')
                ->slice(1);
            foreach ($ids as $idx => $id) {
                DB::table('ventas')->where('id', $id)->update([
                    'documento_numero' => $num . '-DUP' . ($idx + 1),
                ]);
            }
        }

        // Índice único compuesto (tipo + número). NULLs se permiten múltiples veces.
        Schema::table('ventas', function (Blueprint $table) {
            $table->unique(['documento_tipo', 'documento_numero'], 'ventas_doc_unique');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropUnique('ventas_doc_unique');
        });
        Schema::dropIfExists('compra_venta');
        Schema::dropIfExists('compras');
    }
};
