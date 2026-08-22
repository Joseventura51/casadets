<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos_proveedores', function (Blueprint $table) {
            $table->boolean('retencion_aplicada')->default(false)->after('monto');
            $table->decimal('retencion_porcentaje', 5, 2)->default(0)->after('retencion_aplicada');
            $table->decimal('retencion_monto', 14, 2)->default(0)->after('retencion_porcentaje');
            $table->decimal('monto_neto', 14, 2)->default(0)->after('retencion_monto');
        });

        Schema::create('documentos_cxp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_por_pagar_id')->constrained('cuentas_por_pagar')->cascadeOnDelete();
            $table->foreignId('pago_proveedor_id')->nullable()->constrained('pagos_proveedores')->cascadeOnDelete();
            $table->string('tipo', 30); // factura_xml, factura_pdf, retencion_xml, retencion_pdf, pago_imagen
            $table->string('nombre_original');
            $table->string('ruta');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('tamano');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['cuenta_por_pagar_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_cxp');
        Schema::table('pagos_proveedores', function (Blueprint $table) {
            $table->dropColumn(['retencion_aplicada', 'retencion_porcentaje', 'retencion_monto', 'monto_neto']);
        });
    }
};