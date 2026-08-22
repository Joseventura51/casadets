<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class DocumentoCxp extends Model
{
    protected $table = 'documentos_cxp';
    protected $fillable = [
        'cuenta_por_pagar_id', 'pago_proveedor_id', 'tipo', 'nombre_original',
        'ruta', 'mime_type', 'tamano', 'usuario_id',
    ];

    public function cuenta() { return $this->belongsTo(CuentaPorPagar::class, 'cuenta_por_pagar_id'); }
    public function pago() { return $this->belongsTo(PagoProveedor::class, 'pago_proveedor_id'); }
    public function existeArchivo(): bool { return Storage::disk('public')->exists($this->ruta); }
    public function url(): string { return url('/zendy/documentos-cxp/' . $this->id . '/ver'); }
}