<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Serie extends Model
{
    protected $fillable = [
        'codigo',
        'tipo_documento',
        'correlativo_actual',
        'activa',
        'caja_id',
    ];

    protected $casts = [
        'correlativo_actual' => 'integer',
        'activa'             => 'boolean',
    ];

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    /**
     * Genera el siguiente número correlativo y lo incrementa.
     */
    public function siguienteCorrelativo(): string
    {
        $this->increment('correlativo_actual');
        return str_pad((string) $this->correlativo_actual, 8, '0', STR_PAD_LEFT);
    }

    /**
     * Genera el número completo de documento: serie-correlativo.
     */
    public function generarNumero(): string
    {
        return $this->codigo . '-' . $this->siguienteCorrelativo();
    }
}
