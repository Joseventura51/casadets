<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saldos_favor', function (Blueprint $table) {
            $table->foreignId('venta_origen_id')
                  ->nullable()
                  ->after('pago_id')
                  ->constrained('ventas')
                  ->nullOnDelete();

            $table->index('venta_origen_id');
        });

        // ── Backfill histórico: parsear "NC #X (…)" → venta_origen_id ──
        // Sólo rellena registros donde venta_origen_id es NULL y
        // la descripción contiene "NC #<id>", siempre que esa venta exista.
        $saldos = DB::table('saldos_favor')
            ->whereNull('venta_origen_id')
            ->whereNotNull('descripcion')
            ->where('descripcion', 'like', '%NC #%')
            ->get(['id', 'descripcion']);

        foreach ($saldos as $s) {
            // Extraer el número después de "NC #"
            if (preg_match('/NC #(\d+)/', $s->descripcion, $m)) {
                $ventaId = (int) $m[1];
                $existe  = DB::table('ventas')
                    ->where('id', $ventaId)
                    ->where('documento_tipo', 'nota_credito')
                    ->exists();

                if ($existe) {
                    DB::table('saldos_favor')
                        ->where('id', $s->id)
                        ->update(['venta_origen_id' => $ventaId]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('saldos_favor', function (Blueprint $table) {
            $table->dropForeign(['venta_origen_id']);
            $table->dropIndex(['venta_origen_id']);
            $table->dropColumn('venta_origen_id');
        });
    }
};
