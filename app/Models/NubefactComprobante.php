<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NubefactComprobante extends Model
{
    protected $table = 'nubefact_comprobantes';

    protected $fillable = [
        'venta_id',
        'tipo_comprobante',
        'serie',
        'numero',
        'estado',
        'hash',
        'xml',
        'cdr',
        'pdf_url',
        'enlace_pdf',
        'respuesta_completa',
        'nubefact_id',
        'error_mensaje',
        'venta_referencia_id',
    ];

    protected $casts = [
        'respuesta_completa' => 'array',
        'tipo_comprobante'   => 'integer',
        'numero'             => 'integer',
    ];

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function ventaReferencia(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_referencia_id');
    }

    public function estaAceptado(): bool
    {
        return $this->estado === 'aceptado';
    }

    public function tipoLabel(): string
    {
        return match ($this->tipo_comprobante) {
            1       => 'Factura',
            2       => 'Boleta',
            3       => 'Nota de Crédito',
            default => 'Comprobante',
        };
    }

    public function numeroCompleto(): string
    {
        return $this->serie . '-' . str_pad($this->numero, 8, '0', STR_PAD_LEFT);
    }
}
