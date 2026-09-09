<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('almacenes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre');
            $table->string('empresa')->default('zendy');
            $table->boolean('activo')->default(true);
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        Schema::create('producto_almacen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('almacen_id')->constrained('almacenes')->restrictOnDelete();
            $table->decimal('stock_actual', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['producto_id', 'almacen_id']);
        });

        Schema::table('stock_movimientos', function (Blueprint $table) {
            $table->foreignId('almacen_id')
                ->nullable()
                ->after('producto_id')
                ->constrained('almacenes')
                ->nullOnDelete();
            $table->foreignId('usuario_id')
                ->nullable()
                ->after('referencia_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->decimal('saldo_anterior', 12, 2)->nullable()->after('cantidad');
            $table->decimal('saldo_posterior', 12, 2)->nullable()->after('saldo_anterior');
        });

        Schema::create('salidas_almacen', function (Blueprint $table) {
            $table->id();
            $table->string('documento', 100);
            $table->date('fecha');
            $table->foreignId('almacen_origen_id')->constrained('almacenes')->restrictOnDelete();
            $table->foreignId('almacen_destino_id')->constrained('almacenes')->restrictOnDelete();
            $table->string('estado', 40)->default('realizada');
            $table->text('observaciones')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('archivo_referencia')->nullable();
            $table->timestamps();
            $table->index(['fecha', 'estado']);
            $table->index('documento');
        });

        Schema::create('salida_almacen_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salida_almacen_id')->constrained('salidas_almacen')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->string('codigo_producto', 50)->nullable();
            $table->string('descripcion_original')->nullable();
            $table->string('unidad_medida', 20)->nullable();
            $table->decimal('cantidad', 12, 2);
            $table->decimal('precio_unitario', 12, 2)->default(0);
            $table->decimal('monto', 12, 2)->default(0);
            $table->string('lote')->nullable();
            $table->timestamps();
        });

        Schema::create('devoluciones_salida', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salida_almacen_id')->constrained('salidas_almacen')->restrictOnDelete();
            $table->string('documento', 100);
            $table->date('fecha');
            $table->string('motivo')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('almacen_recepcion_id')->constrained('almacenes')->restrictOnDelete();
            $table->string('estado', 20)->default('registrada');
            $table->timestamps();
            $table->index(['salida_almacen_id', 'estado']);
        });

        Schema::create('devolucion_salida_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devolucion_salida_id')->constrained('devoluciones_salida')->cascadeOnDelete();
            $table->foreignId('salida_almacen_detalle_id')->constrained('salida_almacen_detalles')->restrictOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->decimal('cantidad_devuelta', 12, 2);
            $table->decimal('precio_unitario', 12, 2)->default(0);
            $table->decimal('monto', 12, 2)->default(0);
            $table->timestamps();
        });

        // Catálogo operativo inicial de ZENDY.
        // No se crea ningún almacén para CASADETS y no se copia stock global:
        // la base comienza sin inventario físico inicial.
        $ahora = now();
        DB::table('almacenes')->insert([
            [
                'codigo' => 'PIMPON',
                'nombre' => 'Pimpon',
                'empresa' => 'zendy',
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            [
                'codigo' => 'CASA',
                'nombre' => 'Casa',
                'empresa' => 'zendy',
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            [
                'codigo' => 'IMPACTO',
                'nombre' => 'Impacto',
                'empresa' => 'zendy',
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            [
                'codigo' => 'ZAPALLAL',
                'nombre' => 'Zapallal',
                'empresa' => 'zendy',
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            [
                'codigo' => 'LUNA',
                'nombre' => 'Luna',
                'empresa' => 'zendy',
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            [
                'codigo' => 'PIZARRO',
                'nombre' => 'Pizarro',
                'empresa' => 'zendy',
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('devolucion_salida_detalles');
        Schema::dropIfExists('devoluciones_salida');
        Schema::dropIfExists('salida_almacen_detalles');
        Schema::dropIfExists('salidas_almacen');
        Schema::table('stock_movimientos', function (Blueprint $table) {
            $table->dropForeign(['almacen_id']);
            $table->dropForeign(['usuario_id']);
            $table->dropColumn(['almacen_id', 'usuario_id', 'saldo_anterior', 'saldo_posterior']);
        });
        Schema::dropIfExists('producto_almacen');
        Schema::dropIfExists('almacenes');
    }
};