<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedores_cxp', function (Blueprint $table) {
            $table->id();
            $table->string('razon_social');
            $table->string('ruc', 20)->nullable()->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('cuentas_por_pagar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('proveedores_cxp')->cascadeOnDelete();
            $table->string('tipo_documento', 30)->default('factura');
            $table->string('serie', 20)->nullable();
            $table->string('correlativo', 30)->nullable();
            $table->string('documento_numero', 60);
            $table->date('fecha_emision')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->string('moneda', 5)->default('PEN');
            $table->decimal('total', 14, 2)->default(0);
            $table->string('condicion_pago', 30)->nullable();
            $table->string('origen', 80)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->unique(['proveedor_id', 'tipo_documento', 'documento_numero'], 'cxp_documento_unico');
            $table->index(['fecha_vencimiento', 'moneda']);
            $table->index('serie');
        });

        Schema::create('letras_proveedor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_por_pagar_id')->constrained('cuentas_por_pagar')->cascadeOnDelete();
            $table->string('numero', 60);
            $table->date('fecha_emision')->nullable();
            $table->date('fecha_vencimiento');
            $table->decimal('monto', 14, 2);
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->unique(['cuenta_por_pagar_id', 'numero'], 'letra_cxp_unica');
            $table->index('fecha_vencimiento');
        });

        Schema::create('pagos_proveedores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('proveedores_cxp')->cascadeOnDelete();
            $table->foreignId('caja_id')->nullable()->constrained('cajas')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha');
            $table->decimal('monto', 14, 2);
            $table->string('moneda', 5)->default('PEN');
            $table->string('metodo_pago', 50);
            $table->string('referencia', 120)->nullable();
            $table->text('observaciones')->nullable();
            $table->string('estado', 20)->default('activo');
            $table->timestamps();
            $table->index(['fecha', 'estado']);
        });

        Schema::create('aplicaciones_pago_proveedor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_proveedor_id')->constrained('pagos_proveedores')->cascadeOnDelete();
            $table->foreignId('cuenta_por_pagar_id')->constrained('cuentas_por_pagar')->cascadeOnDelete();
            $table->foreignId('letra_proveedor_id')->nullable()->constrained('letras_proveedor')->nullOnDelete();
            $table->decimal('monto_aplicado', 14, 2);
            $table->timestamps();
            $table->index('cuenta_por_pagar_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aplicaciones_pago_proveedor');
        Schema::dropIfExists('pagos_proveedores');
        Schema::dropIfExists('letras_proveedor');
        Schema::dropIfExists('cuentas_por_pagar');
        Schema::dropIfExists('proveedores_cxp');
    }
};