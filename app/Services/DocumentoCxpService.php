<?php

namespace App\Services;

use App\Models\CuentaPorPagar;
use App\Models\DocumentoCxp;
use App\Models\PagoProveedor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentoCxpService
{
    public function guardar(
        UploadedFile $archivo,
        CuentaPorPagar $cuenta,
        string $tipo,
        ?PagoProveedor $pago = null,
        ?int $usuarioId = null
    ): DocumentoCxp {
        $extension = strtolower($archivo->getClientOriginalExtension() ?: 'bin');
        $ruta = 'cuentas-por-pagar/' . now()->format('Y/m') . '/' . Str::uuid() . '.' . $extension;
        Storage::disk('public')->putFileAs(dirname($ruta), $archivo, basename($ruta));

        return DocumentoCxp::create([
            'cuenta_por_pagar_id' => $cuenta->id,
            'pago_proveedor_id' => $pago?->id,
            'tipo' => $tipo,
            'nombre_original' => $archivo->getClientOriginalName(),
            'ruta' => $ruta,
            'mime_type' => $archivo->getMimeType() ?: 'application/octet-stream',
            'tamano' => $archivo->getSize() ?: 0,
            'usuario_id' => $usuarioId,
        ]);
    }
}