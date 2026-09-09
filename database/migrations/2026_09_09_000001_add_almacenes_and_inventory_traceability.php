<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('almacenes')) {
            Schema::create('almacenes', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 30)->unique();
                $table->string('nombre');
                $table->string('empresa')->default('zendy');
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        if (DB::table('almacenes')->count() === 0) {
            $ahora = now();
            $filas = [];
            for ($i = 1; $i <= 6; $i++) {
                $filas[] = [
                    'codigo'    => 'ALM-' . $i,
                    'nombre'    => 'Almacén ' . $i,
                    'empresa'   => 'zendy',
                    'activo'    => true,
                    'created_at'=> $ahora,
                    'updated_at'=> $ahora,
                ];
            }
            DB::table('almacenes')->insert($filas);
        }

        foreach ([
            'stock_movimientos' => 'producto_id',
            'compras'           => 'id',
            'ventas'            => 'id',
            'devoluciones'      => 'id',
        ] as $tabla => $despuesDe) {
            if (!Schema::hasColumn($tabla, 'almacen_id')) {
                Schema::table($tabla, function (Blueprint $table) use ($despuesDe) {
                    $table->foreignId('almacen_id')
                        ->nullable()
                        ->constrained('almacenes')
                        ->nullOnDelete()
                        ->after($despuesDe);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['devoluciones', 'ventas', 'compras', 'stock_movimientos'] as $tabla) {
            if (Schema::hasColumn($tabla, 'almacen_id')) {
                Schema::table($tabla, function (Blueprint $table) {
                    $table->dropForeign(['almacen_id']);
                    $table->dropColumn('almacen_id');
                });
            }
        }

        Schema::dropIfExists('almacenes');
    }
};